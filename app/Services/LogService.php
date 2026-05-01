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
            'syslog' => ['label' => 'Syslog', 'path' => '/var/log/syslog'],
        ];

        $dir = '/etc/nginx/sites-enabled';

        if (! is_dir($dir)) {
            return $sources;
        }

        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = "$dir/$file";
            $content = file_get_contents(is_link($path) ? readlink($path) : $path);

            if (! $content) {
                continue;
            }

            preg_match('/server_name\s+([^;]+);/', $content, $nameMatch);
            $domain = isset($nameMatch[1]) ? trim(preg_split('/\s+/', trim($nameMatch[1]))[0]) : $file;

            if ($domain === '_') {
                $domain = $file;
            }

            preg_match_all('/access_log\s+(\S+)/', $content, $accessMatches);
            foreach ($accessMatches[1] as $logPath) {
                if ($logPath === 'off') {
                    continue;
                }
                $key = 'access_'.md5($logPath);
                $sources[$key] = ['label' => $domain.' access', 'path' => $logPath];
            }

            preg_match_all('/error_log\s+(\S+)/', $content, $errorMatches);
            foreach ($errorMatches[1] as $logPath) {
                if ($logPath === 'off') {
                    continue;
                }
                $key = 'error_'.md5($logPath);
                $sources[$key] = ['label' => $domain.' error', 'path' => $logPath];
            }
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

            $lines[] = [
                'raw' => $line,
                'level' => $this->detectLevel($line),
            ];
        }

        return array_reverse($lines);
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
