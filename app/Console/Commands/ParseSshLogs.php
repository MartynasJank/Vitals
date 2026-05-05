<?php

namespace App\Console\Commands;

use App\Models\SshAttempt;
use App\Services\ThreatIntelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vitals:parse-ssh-logs {--log=/var/log/auth.log}')]
#[Description('Parse auth.log for failed SSH login attempts and store in threat intel database')]
class ParseSshLogs extends Command
{
    private const STATE_FILE = 'ssh_parse_state.txt';

    public function handle(ThreatIntelService $threatIntel): void
    {
        $logFile = $this->option('log');
        $stateFile = storage_path('app/'.self::STATE_FILE);

        if (! file_exists($logFile)) {
            $this->warn("Log file not found: {$logFile}");

            return;
        }

        $offset = file_exists($stateFile) ? (int) file_get_contents($stateFile) : 0;
        $fileSize = filesize($logFile);

        if ($offset > $fileSize) {
            $offset = 0;
        }

        $handle = fopen($logFile, 'r');

        if (! $handle) {
            $this->error("Cannot open log file: {$logFile}");

            return;
        }

        fseek($handle, $offset);

        $processed = 0;

        while (($line = fgets($handle)) !== false) {
            $attempt = $this->parseLine(trim($line));

            if ($attempt) {
                try {
                    $ipId = $threatIntel->upsertIpWithEnrichment($attempt['ip']);

                    SshAttempt::create([
                        'ip_id' => $ipId,
                        'username' => $attempt['username'],
                        'timestamp' => $attempt['timestamp'],
                    ]);

                    $processed++;
                } catch (\Exception $e) {
                    $this->warn("Failed to process attempt: {$e->getMessage()}");
                }
            }
        }

        $newOffset = ftell($handle);
        fclose($handle);

        file_put_contents($stateFile, (string) $newOffset);

        $this->info("Processed {$processed} SSH attempts. Offset: {$newOffset}.");
    }

    /**
     * @return array{ip: string, username: string, timestamp: string}|null
     */
    private function parseLine(string $line): ?array
    {
        if (! str_contains($line, 'Failed password')) {
            return null;
        }

        preg_match(
            '/^(\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})\s+.*Failed password for (?:invalid user )?(\S+) from ([\d.a-fA-F:]+)/',
            $line,
            $m
        );

        if (! isset($m[1], $m[2], $m[3])) {
            return null;
        }

        $ts = strtotime(date('Y').' '.$m[1]);

        // If the parsed date is in the future the log entry is from last year (e.g. Dec log parsed in Jan)
        if ($ts > time() + 86400) {
            $ts = strtotime((date('Y') - 1).' '.$m[1]);
        }

        $timestamp = $ts ? date('Y-m-d H:i:s', $ts) : now()->toDateTimeString();

        return [
            'ip' => $m[3],
            'username' => $m[2],
            'timestamp' => $timestamp,
        ];
    }
}
