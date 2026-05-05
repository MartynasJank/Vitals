<?php

namespace App\Console\Commands;

use App\Models\NginxHit;
use App\Services\ThreatIntelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vitals:parse-nginx-logs {--log=/var/log/nginx/access.log}')]
#[Description('Parse Nginx access log for bot hits and store in threat intel database')]
class ParseNginxLogs extends Command
{
    private const STATE_FILE = 'nginx_parse_state.txt';

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
                        'scan_type' => $hit['scan_type'],
                        'timestamp' => $hit['timestamp'],
                    ]);

                    $processed++;
                } catch (\Exception $e) {
                    $this->warn("Failed to process hit: {$e->getMessage()}");
                }
            }
        }

        $newOffset = ftell($handle);
        fclose($handle);

        file_put_contents($stateFile, (string) $newOffset);

        $this->info("Processed {$processed} bot hits. Offset: {$newOffset}.");
    }

    /**
     * @return array{ip: string, method: string, path: string, status_code: int, user_agent: string, scan_type: string, timestamp: string}|null
     */
    private function parseLine(string $line): ?array
    {
        // Standard Nginx combined log format
        $pattern = '/^([\d.a-fA-F:]+) - \S+ \[([^\]]+)\] "(\S+) (\S+) [^"]*" (\d{3}) \d+ "[^"]*" "([^"]*)"/';

        if (! preg_match($pattern, $line, $m)) {
            return null;
        }

        [, $ip, $timeStr, $method, $path, $status, $userAgent] = $m;

        $statusCode = (int) $status;

        $isBotPath = preg_match(self::BOT_PATH_PATTERN, $path);
        $isErrorStatus = $statusCode >= 400;

        if (! $isBotPath && ! $isErrorStatus) {
            return null;
        }

        $scanType = 'other';

        foreach (self::SCAN_PATTERNS as $pattern => $type) {
            if (preg_match($pattern, $path)) {
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
            'scan_type' => $scanType,
            'timestamp' => $timestamp,
        ];
    }
}
