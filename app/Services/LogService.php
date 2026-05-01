<?php

namespace App\Services;

class LogService
{
    /** @var array<string, array{label: string, path: string}> */
    private array $sources = [
        'nginx_error' => [
            'label' => 'Nginx Error',
            'path' => '/var/log/nginx/error.log',
        ],
        'nginx_access' => [
            'label' => 'Nginx Access',
            'path' => '/var/log/nginx/access.log',
        ],
        'laravel' => [
            'label' => 'Laravel',
            'path' => '/var/www/moviepicker/storage/logs/laravel.log',
        ],
        'syslog' => [
            'label' => 'Syslog',
            'path' => '/var/log/syslog',
        ],
    ];

    /**
     * @return array<string, array{label: string, path: string}>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * @return array<int, array{raw: string, level: string}>
     */
    public function getLines(string $source, ?string $search = null): array
    {
        if (! array_key_exists($source, $this->sources)) {
            return [];
        }

        $path = escapeshellarg($this->sources[$source]['path']);

        if ($search && $search !== '') {
            $grep = 'grep -i '.escapeshellarg($search).' '.$path.' 2>/dev/null | tail -100';
            $output = shell_exec($grep);
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
