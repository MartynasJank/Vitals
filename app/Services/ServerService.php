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

        // Format: %Cpu(s):  3.1 us,  0.5 sy, ...
        preg_match('/(\d+\.\d+)\s+us/', $output, $matches);

        return isset($matches[1]) ? (float) $matches[1] : 0.0;
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

        // Mem:  total  used  free  shared  buff/cache  available
        preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)\s+\d+\s+(\d+)/', $output, $matches);

        return [
            'total_mb' => (int) ($matches[1] ?? 0),
            'used_mb' => (int) ($matches[2] ?? 0),
            'free_mb' => (int) ($matches[3] ?? 0),
            'cached_mb' => (int) ($matches[4] ?? 0),
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

        // /dev/sda1  160G  20G  140G  13% /
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
}
