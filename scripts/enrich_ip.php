<?php

/**
 * Fail2ban IP enrichment script.
 *
 * Called by Fail2ban on ban:
 *   actionban = /usr/bin/php /var/www/vitals/scripts/enrich_ip.php <ip> ssh
 *
 * Server setup (manual steps on the server):
 *   1. Create /etc/fail2ban/action.d/mysql-enrich.conf:
 *      [Definition]
 *      actionban = /usr/bin/php /var/www/vitals/scripts/enrich_ip.php <ip> ssh
 *      actionunban =
 *
 *   2. Add to [sshd] in /etc/fail2ban/jail.local:
 *      action = iptables-multiport
 *               mysql-enrich
 *
 *   3. systemctl restart fail2ban
 *   4. Test: php /var/www/vitals/scripts/enrich_ip.php 1.2.3.4 ssh
 *
 * This script is intentionally standalone (no Laravel bootstrap) to keep it
 * fast and avoid any dependency issues when called from Fail2ban.
 * Always exits 0 — Fail2ban must never be blocked by enrichment failures.
 */
$ip = $argv[1] ?? null;
$source = $argv[2] ?? 'ssh';

if (! $ip) {
    exit(0);
}

$envFile = __DIR__.'/../.env';

if (! file_exists($envFile)) {
    exit(0);
}

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

$host = $env['THREAT_DB_HOST'] ?? '127.0.0.1';
$port = $env['THREAT_DB_PORT'] ?? '3306';
$dbName = $env['THREAT_DB_DATABASE'] ?? 'vitals_threat';
$user = $env['THREAT_DB_USERNAME'] ?? 'vitals';
$pass = $env['THREAT_DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    exit(0);
}

try {
    $stmt = $pdo->prepare('SELECT id, total_hits FROM threat_ips WHERE ip = ?');
    $stmt->execute([$ip]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->prepare('UPDATE threat_ips SET total_hits = total_hits + 1, last_seen = NOW() WHERE id = ?')
            ->execute([$existing['id']]);
        $ipId = $existing['id'];
    } else {
        $geo = fetchGeoData($ip);

        $stmt = $pdo->prepare('
            INSERT INTO threat_ips (ip, country, country_code, city, isp, asn, is_proxy, is_vpn, is_tor, total_hits, first_seen, last_seen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ');
        $stmt->execute([
            $ip,
            $geo['country'] ?? null,
            $geo['countryCode'] ?? null,
            $geo['city'] ?? null,
            $geo['isp'] ?? null,
            $geo['as'] ?? null,
            (int) ($geo['proxy'] ?? false),
            (int) ($geo['proxy'] ?? false),
            (int) ($geo['hosting'] ?? false),
        ]);
        $ipId = (int) $pdo->lastInsertId();
    }

    if ($source === 'ssh') {
        $pdo->prepare('INSERT INTO ssh_attempts (ip_id, timestamp) VALUES (?, NOW())')
            ->execute([$ipId]);
    } elseif ($source === 'nginx') {
        $pdo->prepare('INSERT INTO nginx_hits (ip_id, scan_type, timestamp) VALUES (?, ?, NOW())')
            ->execute([$ipId, 'other']);
    }
} catch (PDOException $e) {
    exit(0);
}

exit(0);

function fetchGeoData(string $ip): array
{
    $url = "http://ip-api.com/json/{$ip}?fields=country,countryCode,city,isp,as,proxy,hosting";

    $ctx = stream_context_create(['http' => ['timeout' => 5]]);

    $json = @file_get_contents($url, false, $ctx);

    if (! $json) {
        return [];
    }

    return json_decode($json, true) ?? [];
}
