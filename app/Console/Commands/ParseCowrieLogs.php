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

    private const STATE_FILE = 'cowrie_parse_state.json';

    public function handle(ThreatIntelService $threatIntel): void
    {
        if (! file_exists(self::LOG_FILE)) {
            $this->warn('Cowrie log file not found: '.self::LOG_FILE);

            return;
        }

        $stateFile = storage_path('app/'.self::STATE_FILE);
        $state = file_exists($stateFile) ? (json_decode(file_get_contents($stateFile), true) ?? []) : [];
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

        $processed = 0;

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

    private function handleEvent(array $event, ThreatIntelService $threatIntel): void
    {
        match ($event['eventid']) {
            'cowrie.session.connect' => $this->handleConnect($event, $threatIntel),
            'cowrie.login.success' => $this->handleLogin($event),
            'cowrie.command.input' => $this->handleCommand($event),
            'cowrie.session.file_download' => $this->handleDownload($event),
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

    private function handleLogin(array $event): void
    {
        $session = CowrieSession::where('session', $event['session'])->first();

        if (! $session) {
            return;
        }

        CowrieLogin::create([
            'cowrie_session_id' => $session->id,
            'username' => $event['username'],
            'password' => $event['password'],
            'timestamp' => $this->parseTimestamp($event['timestamp']),
        ]);

        Credential::upsert(
            [['username' => $event['username'], 'password' => $event['password'], 'hit_count' => 1, 'first_seen' => now(), 'last_seen' => now()]],
            ['username', 'password'],
            ['hit_count' => \DB::raw('hit_count + 1'), 'last_seen' => now()]
        );
    }

    private function handleCommand(array $event): void
    {
        $session = CowrieSession::where('session', $event['session'])->first();

        if (! $session) {
            return;
        }

        CowrieCommand::create([
            'cowrie_session_id' => $session->id,
            'input' => $event['input'],
            'timestamp' => $this->parseTimestamp($event['timestamp']),
        ]);
    }

    private function handleDownload(array $event): void
    {
        $session = CowrieSession::where('session', $event['session'])->first();

        if (! $session) {
            return;
        }

        CowrieDownload::create([
            'cowrie_session_id' => $session->id,
            'url' => $event['url'] ?? null,
            'filename' => isset($event['outfile']) ? basename($event['outfile']) : null,
            'file_hash' => $event['shasum'] ?? null,
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
        return date('Y-m-d H:i:s', strtotime($timestamp));
    }
}
