<?php

namespace App\Console\Commands;

use App\Models\CowrieCommand;
use App\Models\CowrieDownload;
use App\Models\CowrieLogin;
use App\Models\CowrieSession;
use App\Models\Credential;
use App\Services\ThreatIntelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vitals:parse-cowrie-logs')]
#[Description('Parse Cowrie honeypot JSON log and store sessions, commands and downloads')]
class ParseCowrieLogs extends Command
{
    private const LOG_FILE = '/home/cowrie/cowrie/var/log/cowrie/cowrie.json';

    private const LOG_DIR = '/home/cowrie/cowrie/var/log/cowrie';

    private const STATE_FILE = 'cowrie_parse_state.json';

    public function handle(ThreatIntelService $threatIntel): void
    {
        if (! file_exists(self::LOG_FILE)) {
            $this->warn('Cowrie log file not found: '.self::LOG_FILE);

            return;
        }

        $stateFile = storage_path('app/'.self::STATE_FILE);
        $state = file_exists($stateFile) ? (json_decode(file_get_contents($stateFile), true) ?? []) : [];

        if (! ($state['is_interesting_backfilled'] ?? false)) {
            $this->backfillInterestingSessions();
            $state['is_interesting_backfilled'] = true;
        }

        $processed = 0;

        // Process any rotated log files that haven't been fully read yet.
        $processed += $this->processRotatedFiles($state, $threatIntel);

        // Continue incrementally from the current active log file.
        $offset = $state['offset'] ?? 0;
        $fileSize = filesize(self::LOG_FILE);

        if ($offset > $fileSize) {
            $offset = 0;
        }

        $handle = fopen(self::LOG_FILE, 'r');

        if (! $handle) {
            $this->error('Cannot open Cowrie log file.');

            return;
        }

        fseek($handle, $offset);

        while (($line = fgets($handle)) !== false) {
            $event = json_decode(trim($line), true);

            if (! $event || ! isset($event['eventid'])) {
                continue;
            }

            try {
                $this->handleEvent($event, $threatIntel);
                $processed++;
            } catch (\Exception $e) {
                $this->warn("Failed to process event {$event['eventid']}: {$e->getMessage()}");
            }
        }

        $state['offset'] = ftell($handle);
        fclose($handle);

        file_put_contents($stateFile, json_encode($state));

        $this->info("Processed {$processed} Cowrie events.");
    }

    private function processRotatedFiles(array &$state, ThreatIntelService $threatIntel): int
    {
        $done = $state['processed_rotated_files'] ?? [];
        $rotated = glob(self::LOG_DIR.'/cowrie.json.*') ?: [];
        $processed = 0;

        foreach ($rotated as $path) {
            $basename = basename($path);

            if (in_array($basename, $done, true)) {
                continue;
            }

            $isGzip = str_ends_with($path, '.gz');
            $handle = $isGzip ? gzopen($path, 'rb') : fopen($path, 'r');

            if (! $handle) {
                continue;
            }

            $readLine = $isGzip ? fn () => gzgets($handle) : fn () => fgets($handle);

            while (($line = $readLine()) !== false) {
                $event = json_decode(trim($line), true);

                if (! $event || ! isset($event['eventid'])) {
                    continue;
                }

                try {
                    $this->handleEvent($event, $threatIntel);
                    $processed++;
                } catch (\Exception $e) {
                    // continue
                }
            }

            $isGzip ? gzclose($handle) : fclose($handle);

            $done[] = $basename;
        }

        $state['processed_rotated_files'] = $done;

        return $processed;
    }

    private function handleEvent(array $event, ThreatIntelService $threatIntel): void
    {
        match ($event['eventid']) {
            'cowrie.session.connect' => $this->handleConnect($event, $threatIntel),
            'cowrie.login.success' => $this->handleLogin($event, true),
            'cowrie.login.failed' => $this->handleLogin($event, false),
            'cowrie.command.input' => $this->handleCommand($event),
            'cowrie.session.file_download' => $this->handleDownload($event),
            'cowrie.session.file_upload' => $this->handleUpload($event),
            'cowrie.session.closed' => $this->handleClose($event),
            default => null,
        };
    }

    private function handleConnect(array $event, ThreatIntelService $threatIntel): void
    {
        if (CowrieSession::where('session', $event['session'])->exists()) {
            return;
        }

        $ipId = $threatIntel->upsertIpWithEnrichment($event['src_ip']);

        CowrieSession::create([
            'ip_id' => $ipId,
            'session' => $event['session'],
            'started_at' => $this->parseTimestamp($event['timestamp']),
        ]);
    }

    private function handleLogin(array $event, bool $success): void
    {
        $session = CowrieSession::where('session', $event['session'])->first();

        if (! $session) {
            return;
        }

        $timestamp = $this->parseTimestamp($event['timestamp']);

        if (CowrieLogin::where('cowrie_session_id', $session->id)
            ->where('username', $event['username'])
            ->where('password', $event['password'])
            ->where('timestamp', $timestamp)
            ->exists()) {
            return;
        }

        CowrieLogin::create([
            'cowrie_session_id' => $session->id,
            'username' => $event['username'],
            'password' => $event['password'],
            'is_success' => $success,
            'timestamp' => $timestamp,
        ]);

        if ($success) {
            Credential::upsert(
                [['username' => $event['username'], 'password' => $event['password'], 'hit_count' => 1, 'first_seen' => now(), 'last_seen' => now()]],
                ['username', 'password'],
                ['hit_count' => \DB::raw('hit_count + 1'), 'last_seen' => now()]
            );
        }
    }

    private function handleCommand(array $event): void
    {
        $session = CowrieSession::where('session', $event['session'])->first();

        if (! $session) {
            return;
        }

        $timestamp = $this->parseTimestamp($event['timestamp']);

        if (CowrieCommand::where('cowrie_session_id', $session->id)
            ->where('input', $event['input'])
            ->where('timestamp', $timestamp)
            ->exists()) {
            return;
        }

        CowrieCommand::create([
            'cowrie_session_id' => $session->id,
            'input' => $event['input'],
            'timestamp' => $timestamp,
        ]);

        if (! $session->is_interesting && $event['input'] !== '' && ! $this->isFingerprinting($event['input'])) {
            $session->update(['is_interesting' => true]);
        }
    }

    private function isFingerprinting(string $command): bool
    {
        foreach (ThreatIntelService::FINGERPRINT_PATTERNS as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }

        return false;
    }

    private function backfillInterestingSessions(): void
    {
        CowrieSession::has('commands')
            ->where('is_interesting', false)
            ->with(['commands:cowrie_session_id,input'])
            ->each(function (CowrieSession $session) {
                foreach ($session->commands as $command) {
                    if ($command->input !== '' && ! $this->isFingerprinting($command->input)) {
                        $session->update(['is_interesting' => true]);

                        return;
                    }
                }
            });
    }

    private function handleDownload(array $event): void
    {
        $session = CowrieSession::where('session', $event['session'])->first();

        if (! $session) {
            return;
        }

        $shasum = $event['shasum'] ?? null;

        if ($shasum) {
            $already = CowrieDownload::where('cowrie_session_id', $session->id)
                ->where('file_hash', $shasum)
                ->exists();

            if ($already) {
                return;
            }
        }

        CowrieDownload::create([
            'cowrie_session_id' => $session->id,
            'url' => $event['url'] ?? null,
            'filename' => isset($event['outfile']) ? basename($event['outfile']) : null,
            'file_hash' => $shasum,
            'timestamp' => $this->parseTimestamp($event['timestamp']),
        ]);
    }

    private function handleUpload(array $event): void
    {
        $session = CowrieSession::where('session', $event['session'])->first();

        if (! $session) {
            return;
        }

        $shasum = $event['shasum'] ?? null;

        if (! $shasum) {
            return;
        }

        $already = CowrieDownload::where('cowrie_session_id', $session->id)
            ->where('file_hash', $shasum)
            ->exists();

        if ($already) {
            return;
        }

        CowrieDownload::create([
            'cowrie_session_id' => $session->id,
            'url' => '',
            'filename' => $event['filename'] ?? null,
            'file_hash' => $shasum,
            'timestamp' => $this->parseTimestamp($event['timestamp']),
        ]);
    }

    private function handleClose(array $event): void
    {
        CowrieSession::where('session', $event['session'])->update([
            'ended_at' => $this->parseTimestamp($event['timestamp']),
            'duration_seconds' => isset($event['duration']) ? (float) $event['duration'] : null,
        ]);
    }

    private function parseTimestamp(string $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', strtotime($timestamp));
    }
}
