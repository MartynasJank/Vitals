<?php

namespace App\Services;

class LogService
{
    /**
     * @return array<string, array{label: string, path: string, size: string, modified: string}>
     */
    public function getSources(): array
    {
        $candidates = [
            'nginx_error' => ['label' => 'Nginx Error', 'path' => '/var/log/nginx/error.log'],
            'nginx_access' => ['label' => 'Nginx Access', 'path' => '/var/log/nginx/access.log'],
            'syslog' => ['label' => 'Syslog', 'path' => '/var/log/syslog'],
            'fail2ban' => ['label' => 'Fail2ban', 'path' => '/var/log/fail2ban.log'],
            'mysql_error' => ['label' => 'MySQL Error', 'path' => '/var/log/mysql/error.log'],
        ];

        foreach (glob('/var/www/*/storage/logs/laravel.log') ?: [] as $logPath) {
            $appName = basename(dirname($logPath, 3));
            $key = 'laravel_'.$appName;
            $candidates[$key] = ['label' => $appName.' (Laravel)', 'path' => $logPath];
        }

        $sources = [];
        foreach ($candidates as $key => $source) {
            if (! file_exists($source['path'])) {
                continue;
            }
            $sources[$key] = array_merge($source, $this->fileInfo($source['path']));
        }

        return $sources;
    }

    /**
     * @return array{size: string, modified: string}
     */
    private function fileInfo(string $path): array
    {
        return [
            'size' => $this->formatBytes((int) (@filesize($path) ?: 0)),
            'modified' => $this->formatAge((int) (@filemtime($path) ?: 0)),
        ];
    }

    /**
     * @return array<int, array{raw: string, level: string, type: string, timestamp?: string, channel?: string, level_name?: string, message?: string, exception?: string|null}>
     */
    public function getLines(string $source, ?string $search = null, int $lineCount = 100): array
    {
        $sources = $this->getSources();

        if (! array_key_exists($source, $sources)) {
            return [];
        }

        $path = escapeshellarg($sources[$source]['path']);
        $isLaravel = str_starts_with($source, 'laravel_');
        $fetchLines = $isLaravel ? max($lineCount * 20, 2000) : $lineCount;

        if ($search && $search !== '') {
            $output = shell_exec('grep -i '.escapeshellarg($search).' '.$path.' 2>/dev/null | tail -'.$fetchLines);
        } else {
            $output = shell_exec('tail -'.$fetchLines.' '.$path.' 2>/dev/null');
        }

        if (! $output) {
            return [];
        }

        $rawLines = explode("\n", trim($output));

        return $isLaravel
            ? $this->parseLaravelLines($rawLines, $lineCount)
            : $this->parseRawLines($rawLines);
    }

    /**
     * @param  string[]  $rawLines
     * @return array<int, array{raw: string, level: string, type: string, timestamp: string, channel: string, level_name: string, message: string, exception: string|null}>
     */
    private function parseLaravelLines(array $rawLines, int $limit): array
    {
        $entries = [];
        $current = null;

        foreach ($rawLines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/', $line, $m)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = $this->parseLaravelEntry($m[1], $m[2], $m[3], $m[4]);
            }
            // Skip noise: }, ", [stacktrace], blank lines
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return array_slice(array_reverse($entries), 0, $limit);
    }

    /**
     * @return array{raw: string, level: string, type: string, timestamp: string, channel: string, level_name: string, message: string, exception: string|null}
     */
    private function parseLaravelEntry(string $timestamp, string $channel, string $levelName, string $rest): array
    {
        $exception = null;
        if (preg_match('/\[object\] \(([^\(]+)\(.*?\): .+? at ((?:\/[^\s]+):\d+)\)/', $rest, $em)) {
            $location = preg_replace('#^/var/www/[^/]+/#', '', $em[2]);
            $exception = trim($em[1]).' at '.$location;
        }

        $message = trim((string) preg_replace('/\s*\{.*$/s', '', $rest));

        $level = match (strtolower($levelName)) {
            'error', 'critical', 'alert', 'emergency' => 'error',
            'warning' => 'warning',
            default => 'info',
        };

        return [
            'raw' => "[{$timestamp}] {$channel}.{$levelName}: {$message}",
            'level' => $level,
            'type' => 'laravel',
            'timestamp' => $timestamp,
            'channel' => $channel,
            'level_name' => strtoupper($levelName),
            'message' => $message,
            'exception' => $exception,
        ];
    }

    /**
     * @param  string[]  $rawLines
     * @return array<int, array{raw: string, level: string, type: string}>
     */
    private function parseRawLines(array $rawLines): array
    {
        $lines = [];

        foreach ($rawLines as $line) {
            if (empty($line) || preg_match('/^\s*#\d+\s/', $line)) {
                continue;
            }

            $lines[] = [
                'raw' => $line,
                'level' => $this->detectLevel($line),
                'type' => 'raw',
            ];
        }

        return array_reverse($lines);
    }

    public function getPath(string $source): ?string
    {
        return $this->getSources()[$source]['path'] ?? null;
    }

    private function detectLevel(string $line): string
    {
        $lower = strtolower($line);

        if (str_contains($lower, 'error') || str_contains($lower, 'crit') || str_contains($lower, 'alert') || str_contains($lower, 'emerg')) {
            return 'error';
        }

        if (str_contains($lower, 'warn')) {
            return 'warning';
        }

        return 'info';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    private function formatAge(int $timestamp): string
    {
        if ($timestamp === 0) {
            return 'unknown';
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            return (int) ($diff / 60).'m ago';
        }

        if ($diff < 86400) {
            return (int) ($diff / 3600).'h ago';
        }

        return (int) ($diff / 86400).'d ago';
    }
}
