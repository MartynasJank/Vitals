<?php

namespace App\Services;

class LogService
{
    /**
     * @return array<string, array{label: string, path: string}>
     */
    public function getSources(): array
    {
        $sources = [
            'nginx_error' => ['label' => 'Nginx Error', 'path' => '/var/log/nginx/error.log'],
            'nginx_access' => ['label' => 'Nginx Access', 'path' => '/var/log/nginx/access.log'],
            'syslog' => ['label' => 'Syslog', 'path' => '/var/log/syslog'],
        ];

        foreach (glob('/var/www/*/storage/logs/laravel.log') ?: [] as $logPath) {
            $appName = basename(dirname($logPath, 3));
            $key = 'laravel_'.$appName;
            $sources[$key] = ['label' => $appName.' (Laravel)', 'path' => $logPath];
        }

        return $sources;
    }

    /**
     * @return array<int, array{raw: string, level: string}>
     */
    public function getLines(string $source, ?string $search = null): array
    {
        $sources = $this->getSources();

        if (! array_key_exists($source, $sources)) {
            return [];
        }

        $path = escapeshellarg($sources[$source]['path']);

        if ($search && $search !== '') {
            $output = shell_exec('grep -i '.escapeshellarg($search).' '.$path.' 2>/dev/null | tail -100');
        } else {
            $output = shell_exec('tail -100 '.$path.' 2>/dev/null');
        }

        if (! $output) {
            return [];
        }

        $lines = [];

        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) {
                continue;
            }

            if (preg_match('/^\s*#\d+\s/', $line)) {
                continue;
            }

            $lines[] = [
                'raw' => $line,
                'level' => $this->detectLevel($line),
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
}
