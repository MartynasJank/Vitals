<?php

namespace App\Services;

use Carbon\Carbon;

class SecurityService
{
    /**
     * @return array<int, array{ip: string, jail: string}>
     */
    public function getBannedIps(): array
    {
        $output = shell_exec('sudo fail2ban-client status 2>/dev/null');

        if (! $output) {
            return [];
        }

        preg_match_all('/Jail list:\s+(.+)/i', $output, $jailMatches);
        $jails = isset($jailMatches[1][0])
            ? array_map('trim', explode(',', $jailMatches[1][0]))
            : [];

        $banned = [];

        foreach ($jails as $jail) {
            if (empty($jail)) {
                continue;
            }

            $jailOutput = shell_exec('sudo fail2ban-client status '.escapeshellarg($jail).' 2>/dev/null');

            if (! $jailOutput) {
                continue;
            }

            preg_match('/Banned IP list:\s*(.+)/i', $jailOutput, $ipMatch);

            if (empty($ipMatch[1]) || trim($ipMatch[1]) === '') {
                continue;
            }

            foreach (preg_split('/\s+/', trim($ipMatch[1])) as $ip) {
                if ($ip !== '') {
                    $banned[] = ['ip' => $ip, 'jail' => $jail];
                }
            }
        }

        $ignoredIps = config('security.ignored_ips', []);

        return array_values(array_filter($banned, fn ($entry) => ! in_array($entry['ip'], $ignoredIps, true)));
    }

    /**
     * @return array<int, array{time: string, user: string, ip: string}>
     */
    public function getFailedLogins(): array
    {
        $output = shell_exec('grep "Failed password" /var/log/auth.log 2>/dev/null | tail -20');

        if (! $output) {
            return [];
        }

        $ignoredIps = config('security.ignored_ips', []);
        $entries = [];

        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) {
                continue;
            }

            preg_match('/^(\d{4}-\d{2}-\d{2}T[\d:]+).*for (?:invalid user )?(\S+) from ([\d.a-fA-F:]+)/', $line, $m);

            if (isset($m[1], $m[2], $m[3]) && ! in_array($m[3], $ignoredIps, true)) {
                $entries[] = ['time' => Carbon::parse($m[1], 'UTC')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'), 'user' => $m[2], 'ip' => $m[3]];
            }
        }

        return array_reverse($entries);
    }

    /**
     * @return array<int, array{time: string, user: string, ip: string}>
     */
    public function getSuccessfulLogins(): array
    {
        $output = shell_exec('grep "Accepted" /var/log/auth.log 2>/dev/null | tail -10');

        if (! $output) {
            return [];
        }

        $ignoredIps = config('security.ignored_ips', []);
        $entries = [];

        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) {
                continue;
            }

            preg_match('/^(\d{4}-\d{2}-\d{2}T[\d:]+).*for (\S+) from ([\d.a-fA-F:]+)/', $line, $m);

            if (isset($m[1], $m[2], $m[3]) && ! in_array($m[3], $ignoredIps, true)) {
                $entries[] = ['time' => Carbon::parse($m[1], 'UTC')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'), 'user' => $m[2], 'ip' => $m[3]];
            }
        }

        return array_reverse($entries);
    }

    /**
     * @return array<int, string>
     */
    public function getFirewallRules(): array
    {
        $output = shell_exec('sudo iptables -L INPUT -n --line-numbers 2>/dev/null');

        if (! $output) {
            return [];
        }

        $rules = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if (! preg_match('/^\d+\s+(ACCEPT|DROP|REJECT)/', $line)) {
                continue;
            }

            $rules[] = $line;
        }

        return $rules;
    }

    public function unbanIp(string $ip, string $jail = 'sshd'): bool
    {
        $output = shell_exec(
            'sudo fail2ban-client set '.escapeshellarg($jail).' unbanip '.escapeshellarg($ip).' 2>&1'
        );

        return str_contains((string) $output, '1');
    }

    public function banIp(string $ip, string $jail = 'cowrie-connect'): bool
    {
        $cmd = "sudo fail2ban-client set $jail banip $ip 2>&1";

        $output = shell_exec($cmd);

        return $output !== null && stripos($output, 'error') === false;
    }
}
