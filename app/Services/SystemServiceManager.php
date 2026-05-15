<?php

namespace App\Services;

class SystemServiceManager
{
    /** @var array<string, string> */
    private array $services = [
        'nginx' => 'Nginx',
        'mysql' => 'MySQL',
        'php8.4-fpm' => 'PHP 8.4-FPM',
        'fail2ban' => 'Fail2ban',
    ];

    /** @var array<string, array{label: string, process_pattern: string}> */
    private array $processServices = [
        'cowrie' => [
            'label' => 'Cowrie Honeypot',
            'process_pattern' => 'twistd.*cowrie',
        ],
    ];

    /** @var array<string, array{ports: int[], process: string}> */
    private array $serviceMeta = [
        'nginx' => ['ports' => [80, 443], 'process' => 'nginx'],
        'mysql' => ['ports' => [3306],     'process' => 'mysqld'],
        'php8.4-fpm' => ['ports' => [],         'process' => 'php-fpm'],
        'fail2ban' => ['ports' => [],         'process' => 'fail2ban'],
        'cowrie' => ['ports' => [2222],     'process' => 'twistd'],
    ];

    /**
     * @return array<string, array{label: string, running: bool, restarting: bool, uptime: string|null, memory: string|null}>
     */
    public function getAll(): array
    {
        $result = [];

        foreach ($this->services as $service => $label) {
            $status = $this->getStatus($service);
            $restarting = (bool) cache()->get("restarting_{$service}", false);

            if ($restarting && $status['running']) {
                cache()->forget("restarting_{$service}");
                $restarting = false;
            }

            $result[$service] = array_merge(['label' => $label, 'restarting' => $restarting], $status, $this->getExtra($service));
        }

        foreach ($this->processServices as $key => $config) {
            $result[$key] = array_merge(
                ['label' => $config['label'], 'restarting' => false],
                $this->getProcessStatus($config['process_pattern']),
                $this->getExtra($key)
            );
        }

        return $result;
    }

    /**
     * @return array{running: bool, uptime: string|null, memory: string|null}
     */
    public function getStatus(string $service): array
    {
        $output = shell_exec('systemctl show '.escapeshellarg($service).' --property=ActiveState,SubState,ActiveEnterTimestamp,MemoryCurrent 2>/dev/null');

        if (! $output) {
            return ['running' => false, 'uptime' => null, 'memory' => null];
        }

        preg_match('/ActiveState=(\S+)/', $output, $stateMatch);
        preg_match('/ActiveEnterTimestamp=(.+)/', $output, $timestampMatch);
        preg_match('/MemoryCurrent=(\d+)/', $output, $memoryMatch);

        $running = ($stateMatch[1] ?? '') === 'active';

        $uptime = null;
        if ($running && ! empty($timestampMatch[1]) && $timestampMatch[1] !== 'n/a') {
            $since = strtotime($timestampMatch[1]);
            $uptime = $since ? $this->formatUptime(time() - $since) : null;
        }

        $memory = null;
        if (! empty($memoryMatch[1]) && $memoryMatch[1] !== '18446744073709551615') {
            $memory = $this->formatBytes((int) $memoryMatch[1]);
        }

        return ['running' => $running, 'uptime' => $uptime, 'memory' => $memory];
    }

    /**
     * @return array{running: bool, uptime: string|null, memory: string|null}
     */
    private function getProcessStatus(string $processPattern): array
    {
        $pid = (int) trim((string) shell_exec('pgrep -f '.escapeshellarg($processPattern).' 2>/dev/null | head -1'));

        if ($pid <= 0) {
            return ['running' => false, 'uptime' => null, 'memory' => null];
        }

        $uptime = null;
        $memory = null;

        $ps = shell_exec('ps -o etimes=,rss= -p '.escapeshellarg((string) $pid).' 2>/dev/null');
        if ($ps) {
            $parts = preg_split('/\s+/', trim($ps));
            $elapsed = (int) ($parts[0] ?? 0);
            $rssKb = (int) ($parts[1] ?? 0);
            $uptime = $elapsed > 0 ? $this->formatUptime($elapsed) : null;
            $memory = $rssKb > 0 ? $this->formatBytes($rssKb * 1024) : null;
        }

        return ['running' => true, 'uptime' => $uptime, 'memory' => $memory];
    }

    /**
     * @return array{workers: int|null, ports: int[], journal: string[]}
     */
    private function getExtra(string $key): array
    {
        $meta = $this->serviceMeta[$key] ?? null;

        return [
            'workers' => $meta ? $this->getWorkerCount($meta['process']) : null,
            'ports' => $meta['ports'] ?? [],
            'journal' => $this->getRecentJournal($key),
        ];
    }

    private function getWorkerCount(string $processName): int
    {
        return (int) trim((string) shell_exec('pgrep -c -x '.escapeshellarg($processName).' 2>/dev/null'));
    }

    /** @return string[] */
    public function getRecentJournal(string $service): array
    {
        $unit = $service === 'cowrie' ? 'cowrie' : $service;
        $output = shell_exec('journalctl -u '.escapeshellarg($unit).' -n 8 --no-pager --output=short-iso 2>/dev/null');

        if (! $output) {
            return [];
        }

        return array_values(array_filter(explode("\n", trim($output))));
    }

    public function restart(string $service): bool
    {
        if (! array_key_exists($service, $this->services)) {
            return false;
        }

        cache()->put("restarting_{$service}", true, 90);

        $output = shell_exec('sudo systemctl restart '.escapeshellarg($service).' 2>&1');

        return $output === null || trim($output) === '';
    }

    private function formatUptime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return (int) ($seconds / 60).'m';
        }

        if ($seconds < 86400) {
            return (int) ($seconds / 3600).'h '.(int) (($seconds % 3600) / 60).'m';
        }

        return (int) ($seconds / 86400).'d '.(int) (($seconds % 86400) / 3600).'h';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
