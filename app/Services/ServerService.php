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
