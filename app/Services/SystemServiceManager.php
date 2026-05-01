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

            $result[$service] = array_merge(['label' => $label, 'restarting' => $restarting], $status);
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
