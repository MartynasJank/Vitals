<?php

namespace App\Services;

class ServerService
{
    public function getCpuPercent(): float
    {
        $output = shell_exec("top -bn1 | grep 'Cpu(s)'");

        if (! $output) {
            return 0.0;
        }

        preg_match('/(\d+\.\d+)\s+us/', $output, $matches);

        return isset($matches[1]) ? (float) $matches[1] : 0.0;
    }

    /**
     * @return array{one: float, five: float, fifteen: float}
     */
    public function getLoadAverage(): array
    {
        $output = shell_exec('cat /proc/loadavg');

        if (! $output) {
            return ['one' => 0.0, 'five' => 0.0, 'fifteen' => 0.0];
        }

        $parts = explode(' ', trim($output));

        return [
            'one' => (float) ($parts[0] ?? 0),
            'five' => (float) ($parts[1] ?? 0),
            'fifteen' => (float) ($parts[2] ?? 0),
        ];
    }

    public function getCoreCount(): int
    {
        $output = shell_exec('nproc');

        return $output ? (int) trim($output) : 1;
    }

    /**
     * @return array{used_mb: int, total_mb: int, free_mb: int, cached_mb: int}
     */
    public function getRamStats(): array
    {
        $output = shell_exec('free -m');

        if (! $output) {
            return ['used_mb' => 0, 'total_mb' => 0, 'free_mb' => 0, 'cached_mb' => 0];
        }

        preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)\s+\d+\s+(\d+)/', $output, $matches);

        return [
            'total_mb' => (int) ($matches[1] ?? 0),
            'used_mb' => (int) ($matches[2] ?? 0),
            'free_mb' => (int) ($matches[3] ?? 0),
            'cached_mb' => (int) ($matches[4] ?? 0),
        ];
    }

    /**
     * @return array{used_mb: int, total_mb: int}
     */
    public function getSwapStats(): array
    {
        $output = shell_exec('free -m');

        if (! $output) {
            return ['used_mb' => 0, 'total_mb' => 0];
        }

        preg_match('/Swap:\s+(\d+)\s+(\d+)/', $output, $matches);

        return [
            'total_mb' => (int) ($matches[1] ?? 0),
            'used_mb' => (int) ($matches[2] ?? 0),
        ];
    }

    /**
     * @return array{used_gb: float, total_gb: float, percent: float}
     */
    public function getDiskStats(): array
    {
        $output = shell_exec('df -BG / | tail -1');

        if (! $output) {
            return ['used_gb' => 0.0, 'total_gb' => 0.0, 'percent' => 0.0];
        }

        preg_match('/\s+(\d+)G\s+(\d+)G\s+(\d+)G\s+(\d+)%/', $output, $matches);

        $total = (float) ($matches[1] ?? 0);
        $used = (float) ($matches[2] ?? 0);
        $percent = $total > 0 ? round($used / $total * 100, 1) : 0.0;

        return [
            'total_gb' => $total,
            'used_gb' => $used,
            'percent' => $percent,
        ];
    }

    /**
     * @return array{interface: string, rx_rate_kbps: float, tx_rate_kbps: float, rx_total_gb: float, tx_total_gb: float}
     */
    public function getNetworkStats(): array
    {
        $interface = trim(shell_exec("ip route show default 2>/dev/null | awk 'NR==1 {print $5}'") ?? '');

        if (! preg_match('/^[a-z0-9]+$/', $interface)) {
            $interface = 'eth0';
        }

        $empty = ['interface' => $interface, 'rx_rate_kbps' => 0.0, 'tx_rate_kbps' => 0.0, 'rx_total_gb' => 0.0, 'tx_total_gb' => 0.0];
        $output = shell_exec('cat /proc/net/dev');

        if (! $output) {
            return $empty;
        }

        foreach (explode("\n", $output) as $line) {
            if (! preg_match('/^\s*'.preg_quote($interface, '/').':\s*(.+)/', $line, $m)) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($m[1]));
            $rxBytes = (int) ($parts[0] ?? 0);
            $txBytes = (int) ($parts[8] ?? 0);

            $cacheKey = "network_stats_{$interface}";
            $prev = cache()->get($cacheKey);
            $now = time();
            $rxRateKbps = 0.0;
            $txRateKbps = 0.0;

            if ($prev && isset($prev['time'], $prev['rx'], $prev['tx'])) {
                $elapsed = $now - $prev['time'];

                if ($elapsed > 0) {
                    $rxRateKbps = max(0.0, round(($rxBytes - $prev['rx']) / $elapsed / 1024, 1));
                    $txRateKbps = max(0.0, round(($txBytes - $prev['tx']) / $elapsed / 1024, 1));
                }
            }

            cache()->put($cacheKey, ['time' => $now, 'rx' => $rxBytes, 'tx' => $txBytes], 60);

            return [
                'interface' => $interface,
                'rx_rate_kbps' => $rxRateKbps,
                'tx_rate_kbps' => $txRateKbps,
                'rx_total_gb' => round($rxBytes / 1_073_741_824, 2),
                'tx_total_gb' => round($txBytes / 1_073_741_824, 2),
            ];
        }

        return $empty;
    }

    /**
     * @return array<int, array{device: string, mount: string, total_gb: int, used_gb: int, avail_gb: int, percent: int}>
     */
    public function getAllDiskPartitions(): array
    {
        $output = shell_exec('df -BG --output=source,size,used,avail,pcent,target 2>/dev/null | tail -n +2');

        if (! $output) {
            return [];
        }

        $partitions = [];

        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));

            if (count($parts) < 6 || ! str_starts_with($parts[0], '/dev/')) {
                continue;
            }

            $partitions[] = [
                'device' => $parts[0],
                'total_gb' => (int) rtrim($parts[1], 'G'),
                'used_gb' => (int) rtrim($parts[2], 'G'),
                'avail_gb' => (int) rtrim($parts[3], 'G'),
                'percent' => (int) rtrim($parts[4], '%'),
                'mount' => $parts[5],
            ];
        }

        return $partitions;
    }

    /**
     * @return array<int, array{pid: int, user: string, cpu: float, memory: float, command: string}>
     */
    public function getTopProcesses(): array
    {
        $output = shell_exec('ps aux --sort=-%cpu | head -16');

        if (! $output) {
            return [];
        }

        $lines = explode("\n", trim($output));
        array_shift($lines); // remove header

        $processes = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line), 11);

            if (count($parts) < 11) {
                continue;
            }

            $processes[] = [
                'pid' => (int) $parts[1],
                'user' => $parts[0],
                'cpu' => (float) $parts[2],
                'memory' => (float) $parts[3],
                'command' => $parts[10],
            ];
        }

        return $processes;
    }
}
