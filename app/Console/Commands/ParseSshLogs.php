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
            '/^(\S+)\s+.*Failed password for (?:invalid user )?(\S+) from ([\d.a-fA-F:]+)/',
            $line,
            $m
        );

        if (! isset($m[1], $m[2], $m[3])) {
            return null;
        }

        try {
            $timestamp = date('Y-m-d H:i:s', strtotime($m[1]));
        } catch (\Exception) {
            $timestamp = now()->toDateTimeString();
        }

        return [
            'ip' => $m[3],
            'username' => $m[2],
            'timestamp' => $timestamp,
        ];
    }
}
