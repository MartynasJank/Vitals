<?php

namespace App\Console\Commands;

use App\Models\SshAttempt;
use App\Services\ThreatIntelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vitals:parse-ssh-logs {--log=/var/log/auth.log} {--fresh}')]
#[Description('Parse auth.log for failed SSH login attempts and store in threat intel database')]
class ParseSshLogs extends Command
{
    public function handle(ThreatIntelService $threatIntel): void
    {
        $logFile = $this->option('log');

        if (! file_exists($logFile)) {
            $this->warn("Log file not found: {$logFile}");

            return;
        }

        $handle = fopen($logFile, 'r');

        if (! $handle) {
            $this->error("Cannot open log file: {$logFile}");

            return;
        }

        $since = $this->option('fresh')
            ? '1970-01-01 00:00:00'
            : (SshAttempt::max('timestamp') ?? '1970-01-01 00:00:00');

        $processed = 0;
        $skipped = 0;

        while (($line = fgets($handle)) !== false) {
            $attempt = $this->parseLine(trim($line));

            if (! $attempt) {
                continue;
            }

            if ($attempt['timestamp'] <= $since) {
                $skipped++;

                continue;
            }

            try {
                $ipId = $threatIntel->upsertIpWithEnrichment($attempt['ip']);

                SshAttempt::create([
                    'ip_id' => $ipId,
                    'username' => $attempt['username'],
                    'timestamp' => $attempt['timestamp'],
                ]);

                $processed++;
            } catch (\Exception $e) {
                $this->warn("Failed to process attempt from {$attempt['ip']}: {$e->getMessage()}");
            }
        }

        fclose($handle);

        $this->info("Processed {$processed} new SSH attempts. Skipped {$skipped} already seen.");
    }

    /**
     * @return array{ip: string, username: string, timestamp: string}|null
     */
    private function parseLine(string $line): ?array
    {
        if (! str_contains($line, 'Failed password')) {
            return null;
        }

        // ISO 8601 format: 2026-05-06T07:56:11.416038+00:00 (Ubuntu 24.04+, modern rsyslog)
        if (preg_match(
            '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})\.\d+([+-]\d{2}:\d{2}|Z)\s+\S+\s+sshd\[\d+\]:\sFailed password for (?:invalid user )?(\S+) from ([\d.a-fA-F:]+)/',
            $line,
            $m
        )) {
            return [
                'ip' => $m[4],
                'username' => $m[3],
                'timestamp' => gmdate('Y-m-d H:i:s', strtotime($m[1])),
            ];
        }

        // Legacy syslog format: May  6 07:56:11 (older Ubuntu / Debian)
        if (preg_match(
            '/^(\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})\s+\S+\s+sshd\[\d+\]:\sFailed password for (?:invalid user )?(\S+) from ([\d.a-fA-F:]+)/',
            $line,
            $m
        )) {
            $ts = strtotime(gmdate('Y').' '.$m[1]);

            if ($ts > time() + 86400) {
                $ts = strtotime((gmdate('Y') - 1).' '.$m[1]);
            }

            return [
                'ip' => $m[3],
                'username' => $m[2],
                'timestamp' => $ts ? gmdate('Y-m-d H:i:s', $ts) : now()->utc()->toDateTimeString(),
            ];
        }

        return null;
    }
}
