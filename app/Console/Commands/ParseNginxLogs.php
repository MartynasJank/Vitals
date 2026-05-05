<?php

namespace App\Console\Commands;

use App\Models\NginxHit;
use App\Services\ThreatIntelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vitals:parse-nginx-logs')]
#[Description('Parse all Nginx access logs for bot hits and store in threat intel database')]
class ParseNginxLogs extends Command
{
    private const STATE_FILE = 'nginx_parse_state.json';

    private const SCAN_PATTERNS = [
        '/\.env/i' => 'env_probe',
        '/\.git/i' => 'git_exposure',
        '/wp-admin|xmlrpc\.php/i' => 'wp_admin',
        '/phpmyadmin/i' => 'phpmyadmin',
        '/\/actuator/i' => 'spring_boot',
        '/jndi:|log4j|\$\{/i' => 'log4shell',
    ];

    private const BOT_PATH_PATTERN = '/\.php$|\.env|\.git|wp-admin|xmlrpc|phpmyadmin|actuator|jndi:|log4j|\$\{/i';

    public function handle(ThreatIntelService $threatIntel): void
    {
        $logFiles = glob('/var/log/nginx/*access*.log') ?: [];

        if (empty($logFiles)) {
            $this->warn('No Nginx access log files found.');

            return;
        }

        $stateFile = storage_path('app/'.self::STATE_FILE);
        $state = file_exists($stateFile) ? (json_decode(file_get_contents($stateFile), true) ?? []) : [];

        $totalProcessed = 0;

        foreach ($logFiles as $logFile) {
            $offset = $state[$logFile] ?? 0;
            $fileSize = filesize($logFile);

            if ($offset > $fileSize) {
                $offset = 0;
            }

            $handle = fopen($logFile, 'r');

            if (! $handle) {
                $this->warn("Cannot open log file: {$logFile}");

                continue;
            }

            fseek($handle, $offset);

            $processed = 0;

            while (($line = fgets($handle)) !== false) {
                $hit = $this->parseLine(trim($line));

                if ($hit) {
                    try {
                        $ipId = $threatIntel->upsertIpWithEnrichment($hit['ip']);

                        NginxHit::create([
                            'ip_id' => $ipId,
                            'path' => $hit['path'],
                            'method' => $hit['method'],
                            'status_code' => $hit['status_code'],
                            'user_agent' => $hit['user_agent'],
                            'referer' => $hit['referer'],
                            'scan_type' => $hit['scan_type'],
                            'timestamp' => $hit['timestamp'],
                        ]);

                        $processed++;
                    } catch (\Exception $e) {
                        $this->warn("Failed to process hit: {$e->getMessage()}");
                    }
                }
            }

            $state[$logFile] = ftell($handle);
            fclose($handle);

            $totalProcessed += $processed;

            if ($processed > 0) {
                $this->line("  {$logFile}: {$processed} hits");
            }
        }

        file_put_contents($stateFile, json_encode($state));

        $this->info("Processed {$totalProcessed} bot hits across ".count($logFiles).' log file(s).');
    }

    /**
     * @return array{ip: string, method: string, path: string, status_code: int, user_agent: string, scan_type: string, timestamp: string}|null
     */
    private function parseLine(string $line): ?array
    {
        $pattern = '/^([\d.a-fA-F:]+) - \S+ \[([^\]]+)\] "(\S+) (\S+) [^"]*" (\d{3}) \d+ "([^"]*)" "([^"]*)"/';

        if (! preg_match($pattern, $line, $m)) {
            return null;
        }

        [, $ip, $timeStr, $method, $path, $status, $referer, $userAgent] = $m;

        $statusCode = (int) $status;

        $isBotPath = preg_match(self::BOT_PATH_PATTERN, $path);
        $isErrorStatus = $statusCode >= 400;

        if (! $isBotPath && ! $isErrorStatus) {
            return null;
        }

        $scanType = 'other';

        foreach (self::SCAN_PATTERNS as $scanPattern => $type) {
            if (preg_match($scanPattern, $path)) {
                $scanType = $type;
                break;
            }
        }

        try {
            $timestamp = date('Y-m-d H:i:s', strtotime($timeStr));
        } catch (\Exception) {
            $timestamp = now()->toDateTimeString();
        }

        return [
            'ip' => $ip,
            'method' => strtoupper($method),
            'path' => substr($path, 0, 2048),
            'status_code' => $statusCode,
            'user_agent' => substr($userAgent, 0, 512),
            'referer' => $referer !== '-' ? substr($referer, 0, 512) : null,
            'scan_type' => $scanType,
            'timestamp' => $timestamp,
        ];
    }
}
