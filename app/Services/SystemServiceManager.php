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

    /** @var array<string, string> */
    private array $serviceMeta = [
        'nginx' => 'nginx',
        'mysql' => 'mysqld',
        'php8.4-fpm' => 'php-fpm',
        'fail2ban' => 'fail2ban',
        'cowrie' => 'twistd',
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

        $result['nginx']['connections'] = $this->getNginxConnections();
        $result['php8.4-fpm']['fpm'] = $this->getFpmPool();

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
        $process = $this->serviceMeta[$key] ?? null;

        return [
            'workers' => $process ? $this->getWorkerCount($process) : null,
            'ports' => $process ? $this->getListeningPorts($process) : [],
            'journal' => $this->getRecentJournal($key),
        ];
    }

    private function getWorkerCount(string $processName): int
    {
        return (int) trim((string) shell_exec('pgrep -c -x '.escapeshellarg($processName).' 2>/dev/null'));
    }

    /** @return int[] */
    private function getListeningPorts(string $processName): array
    {
        $output = shell_exec('ss -Htlnp 2>/dev/null');

        if (! $output) {
            return [];
        }

        $ports = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (! str_contains($line, '"'.$processName.'"')) {
                continue;
            }
            if (preg_match('/[:\[](\d+)\s/', $line, $m)) {
                $ports[] = (int) $m[1];
            }
        }

        return array_values(array_unique($ports));
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

    /** @return array<int, array{pid: int, options: string}> */
    public function getQueueWorkers(): array
    {
        $output = shell_exec('pgrep -a -f "artisan queue:work" 2>/dev/null');

        if (! $output) {
            return [];
        }

        $workers = [];
        foreach (array_filter(explode("\n", trim($output))) as $line) {
            if (preg_match('/^(\d+)\s+\S*php\S*\s.*artisan queue:work(.*)$/', $line, $m)) {
                $workers[] = ['pid' => (int) $m[1], 'options' => trim($m[2])];
            }
        }

        return $workers;
    }

    public function getSchedulerLastRun(): ?string
    {
        $ts = cache()->get('vitals_scheduler_last_run');

        if (! $ts) {
            return null;
        }

        $diff = time() - (int) $ts;

        return $diff < 120 ? $diff.'s ago' : round($diff / 60).'m ago';
    }

    private function getNginxConnections(): ?int
    {
        $output = shell_exec('curl -s --max-time 1 -H "Host: vitals.martybuilds.dev" http://127.0.0.1/nginx_status 2>/dev/null');

        if (! $output || ! preg_match('/Active connections:\s*(\d+)/', $output, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /** @return array{active: int, idle: int, total: int, slow: int}|null */
    private function getFpmPool(): ?array
    {
        $output = shell_exec('curl -s --max-time 1 -H "Host: vitals.martybuilds.dev" "http://127.0.0.1/fpm-status?json" 2>/dev/null');

        if (! $output) {
            return null;
        }

        $data = json_decode($output, true);

        if (! is_array($data) || ! isset($data['active processes'])) {
            return null;
        }

        return [
            'active' => $data['active processes'],
            'idle' => $data['idle processes'],
            'total' => $data['total processes'],
            'slow' => $data['slow requests'] ?? 0,
        ];
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
