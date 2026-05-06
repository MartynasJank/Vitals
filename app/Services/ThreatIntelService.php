<?php

namespace App\Services;

use App\Models\CowrieCommand;
use App\Models\CowrieDownload;
use App\Models\CowrieLogin;
use App\Models\CowrieSession;
use App\Models\Credential;
use App\Models\NginxHit;
use App\Models\SshAttempt;
use App\Models\ThreatIp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ThreatIntelService
{
    public function upsertIpWithEnrichment(string $ip): int
    {
        $record = ThreatIp::where('ip', $ip)->first();

        if ($record) {
            $record->increment('total_hits');
            $record->update(['last_seen' => now()]);

            return $record->id;
        }

        $geo = $this->fetchGeoData($ip);

        $record = ThreatIp::create([
            'ip' => $ip,
            'country' => $geo['country'] ?? null,
            'country_code' => $geo['countryCode'] ?? null,
            'city' => $geo['city'] ?? null,
            'isp' => $geo['isp'] ?? null,
            'asn' => $geo['as'] ?? null,
            'lat' => isset($geo['lat']) ? (float) $geo['lat'] : null,
            'lon' => isset($geo['lon']) ? (float) $geo['lon'] : null,
            'org' => $geo['org'] ?? null,
            'is_proxy' => (bool) ($geo['proxy'] ?? false),
            'is_vpn' => (bool) ($geo['proxy'] ?? false),
            'is_tor' => false,
            'total_hits' => 1,
            'first_seen' => now(),
            'last_seen' => now(),
        ]);

        return $record->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchGeoForBackfill(string $ip): array
    {
        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'lat,lon,org',
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Exception) {
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchGeoData(string $ip): array
    {
        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'country,countryCode,city,isp,as,org,lat,lon,proxy,hosting',
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Exception) {
        }

        return [];
    }

    /**
     * @return array<int, array{time: string, user: string, ip: string, country: string|null, country_code: string|null, isp: string|null, asn: string|null, is_proxy: bool, total_hits: int}>
     */
    public function getRecentSshdAttempts(int $limit = 20): array
    {
        return SshAttempt::with('ip')
            ->orderByDesc('timestamp')
            ->limit($limit)
            ->get()
            ->map(fn ($attempt) => [
                'time' => $attempt->timestamp?->format('H:i:s'),
                'user' => $attempt->username,
                'ip' => $attempt->ip?->ip ?? '—',
                'country' => $attempt->ip?->country,
                'country_code' => $attempt->ip?->country_code ? strtolower($attempt->ip->country_code) : null,
                'isp' => $attempt->ip?->isp,
                'asn' => $attempt->ip?->asn,
                'is_proxy' => (bool) ($attempt->ip?->is_proxy ?? false),
                'total_hits' => $attempt->ip?->total_hits ?? 1,
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, ssh: int, nginx: int}>
     */
    public function getAttackVolumeOverTime(string $range): array
    {
        [$since, $groupFormat, $labelFormat] = match ($range) {
            '7d' => [now()->subDays(7), '%Y-%m-%d', 'Y-m-d'],
            '30d' => [now()->subDays(30), '%Y-%m-%d', 'Y-m-d'],
            default => [now()->subHours(24), '%Y-%m-%d %H:00', 'Y-m-d H:00'],
        };

        $tz = now()->format('P');

        $ssh = SshAttempt::select(DB::raw("DATE_FORMAT(CONVERT_TZ(timestamp, '+00:00', '{$tz}'), '{$groupFormat}') as label, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->groupBy('label')
            ->pluck('count', 'label');

        $cowrie = CowrieLogin::select(DB::raw("DATE_FORMAT(CONVERT_TZ(timestamp, '+00:00', '{$tz}'), '{$groupFormat}') as label, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->groupBy('label')
            ->pluck('count', 'label');

        $nginx = NginxHit::select(DB::raw("DATE_FORMAT(CONVERT_TZ(timestamp, '+00:00', '{$tz}'), '{$groupFormat}') as label, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->groupBy('label')
            ->pluck('count', 'label');

        $labels = $ssh->keys()->merge($cowrie->keys())->merge($nginx->keys())->unique()->sort()->values();

        return $labels->map(fn ($label) => [
            'label' => $label,
            'ssh' => (int) ($ssh[$label] ?? 0) + (int) ($cowrie[$label] ?? 0),
            'nginx' => (int) ($nginx[$label] ?? 0),
        ])->values()->all();
    }

    /**
     * @return array<int, array{country: string, country_code: string, count: int}>
     */
    public function getTopSourceCountries(int $limit = 10): array
    {
        return ThreatIp::select('country', 'country_code', DB::raw('SUM(total_hits) as count'))
            ->whereNotNull('country')
            ->groupBy('country', 'country_code')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'country' => $row->country,
                'country_code' => strtolower($row->country_code ?? ''),
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{org: string, count: int}>
     */
    public function getTopOrgs(int $limit = 10): array
    {
        return ThreatIp::select('org', DB::raw('COUNT(*) as count'))
            ->whereNotNull('org')
            ->groupBy('org')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'org' => $row->org,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{isp: string, count: int}>
     */
    public function getTopIsps(int $limit = 10): array
    {
        return ThreatIp::select('isp', DB::raw('COUNT(*) as count'))
            ->whereNotNull('isp')
            ->groupBy('isp')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'isp' => $row->isp,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{username: string, count: int}>
     */
    public function getTopSshUsernames(int $limit = 20): array
    {
        $ssh = SshAttempt::select('username', DB::raw('COUNT(*) as count'))
            ->whereNotNull('username')
            ->groupBy('username')
            ->pluck('count', 'username');

        $cowrie = CowrieLogin::select('username', DB::raw('COUNT(*) as count'))
            ->whereNotNull('username')
            ->groupBy('username')
            ->pluck('count', 'username'); // all attempts, success + failed

        return $ssh->mergeRecursive($cowrie)
            ->map(fn ($v) => is_array($v) ? array_sum($v) : (int) $v)
            ->sortDesc()
            ->take($limit)
            ->map(fn ($count, $username) => [
                'username' => $username,
                'count' => (int) $count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{referer: string, count: int}>
     */
    public function getTopReferers(int $limit = 15): array
    {
        return NginxHit::select('referer', DB::raw('COUNT(*) as count'))
            ->whereNotNull('referer')
            ->groupBy('referer')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'referer' => $row->referer,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{path: string, count: int, scan_type: string}>
     */
    public function getTopNginxPaths(int $limit = 20): array
    {
        return NginxHit::select('path', 'scan_type', DB::raw('COUNT(*) as count'))
            ->whereNotNull('path')
            ->groupBy('path', 'scan_type')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'path' => $row->path,
                'scan_type' => $row->scan_type,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    public function getRepeatOffenderRate(): float
    {
        $total = ThreatIp::count();

        if ($total === 0) {
            return 0.0;
        }

        $repeat = ThreatIp::where('total_hits', '>', 1)->count();

        return round(($repeat / $total) * 100, 1);
    }

    /**
     * @return array<int, array{ip: string, country: string|null, country_code: string|null, isp: string|null, ssh_count: int, nginx_count: int, total_hits: int}>
     */
    public function getCrossSourceIps(): array
    {
        $sshIpIds = SshAttempt::select('ip_id')->distinct()->pluck('ip_id');
        $cowrieIpIds = CowrieSession::select('ip_id')->distinct()->pluck('ip_id');
        $allSshIpIds = $sshIpIds->merge($cowrieIpIds)->unique()->values();
        $nginxIpIds = NginxHit::select('ip_id')->distinct()->pluck('ip_id');
        $crossIds = $allSshIpIds->intersect($nginxIpIds)->values();

        if ($crossIds->isEmpty()) {
            return [];
        }

        return ThreatIp::whereIn('id', $crossIds)
            ->withCount(['sshAttempts', 'nginxHits', 'cowrieSessions'])
            ->orderByDesc('total_hits')
            ->limit(20)
            ->get()
            ->map(fn ($ip) => [
                'ip' => $ip->ip,
                'country' => $ip->country,
                'country_code' => $ip->country_code ? strtolower($ip->country_code) : null,
                'isp' => $ip->isp,
                'ssh_count' => $ip->ssh_attempts_count + $ip->cowrie_sessions_count,
                'nginx_count' => $ip->nginx_hits_count,
                'total_hits' => $ip->total_hits,
            ])
            ->all();
    }

    /**
     * Returns attack counts indexed by hour 0–23.
     *
     * @return array<int, int>
     */
    public function getAttackHeatmap(string $range): array
    {
        $since = match ($range) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };

        $tz = now()->format('P');

        $ssh = SshAttempt::select(DB::raw("HOUR(CONVERT_TZ(timestamp, '+00:00', '{$tz}')) as hour, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->groupBy('hour')
            ->pluck('count', 'hour');

        $cowrie = CowrieLogin::select(DB::raw("HOUR(CONVERT_TZ(timestamp, '+00:00', '{$tz}')) as hour, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->groupBy('hour')
            ->pluck('count', 'hour');

        $nginx = NginxHit::select(DB::raw("HOUR(CONVERT_TZ(timestamp, '+00:00', '{$tz}')) as hour, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->groupBy('hour')
            ->pluck('count', 'hour');

        $heatmap = array_fill(0, 24, 0);

        for ($h = 0; $h < 24; $h++) {
            $heatmap[$h] = (int) ($ssh[$h] ?? 0) + (int) ($cowrie[$h] ?? 0) + (int) ($nginx[$h] ?? 0);
        }

        return $heatmap;
    }

    /**
     * Returns attack origin coordinates for map visualization, grouped into ~1° cells.
     *
     * @return array<int, array{lat: float, lon: float, count: int}>
     */
    public function getAttackOrigins(): array
    {
        return ThreatIp::select(
            DB::raw('ROUND(lat, 1) as lat'),
            DB::raw('ROUND(lon, 1) as lon'),
            DB::raw('SUM(total_hits) as count')
        )
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->groupBy('lat', 'lon')
            ->orderByDesc('count')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'lat' => (float) $row->lat,
                'lon' => (float) $row->lon,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    public function getTotalAttacksLast24h(): int
    {
        $since = now()->subHours(24);

        return SshAttempt::where('timestamp', '>=', $since)->count()
            + CowrieLogin::where('timestamp', '>=', $since)->count()
            + NginxHit::where('timestamp', '>=', $since)->count();
    }

    public function getTotalAttacksLastHour(): int
    {
        $since = now()->subHour();

        return SshAttempt::where('timestamp', '>=', $since)->count()
            + CowrieLogin::where('timestamp', '>=', $since)->count()
            + NginxHit::where('timestamp', '>=', $since)->count();
    }

    /**
     * @return array<int, array{time: string, ip: string, country: string|null, country_code: string|null, method: string, path: string, status_code: int, scan_type: string}>
     */
    public function getRecentNginxHits(int $limit = 20): array
    {
        return NginxHit::with('ip')
            ->orderByDesc('timestamp')
            ->limit($limit)
            ->get()
            ->map(fn ($hit) => [
                'time' => $hit->timestamp?->format('H:i:s'),
                'ip' => $hit->ip?->ip ?? '—',
                'country' => $hit->ip?->country,
                'country_code' => $hit->ip?->country_code ? strtolower($hit->ip->country_code) : null,
                'method' => $hit->method,
                'path' => $hit->path,
                'status_code' => $hit->status_code,
                'scan_type' => $hit->scan_type,
            ])
            ->all();
    }

    /**
     * @return array<int, array{time: string, user: string, password: string, ip: string, country: string|null, country_code: string|null, isp: string|null, asn: string|null, total_hits: int, is_proxy: bool, is_success: bool}>
     */
    public function getRecentHoneypotLogins(int $limit = 20): array
    {
        return CowrieLogin::with(['session.ip'])
            ->orderByDesc('timestamp')
            ->limit($limit)
            ->get()
            ->map(fn ($login) => [
                'time' => $login->timestamp?->format('H:i:s'),
                'user' => $login->username,
                'password' => $login->password,
                'ip' => $login->session?->ip?->ip ?? '—',
                'country' => $login->session?->ip?->country,
                'country_code' => $login->session?->ip?->country_code ? strtolower($login->session->ip->country_code) : null,
                'isp' => $login->session?->ip?->isp,
                'asn' => $login->session?->ip?->asn,
                'total_hits' => $login->session?->ip?->total_hits ?? 1,
                'is_proxy' => (bool) ($login->session?->ip?->is_proxy ?? false),
                'is_success' => (bool) $login->is_success,
            ])
            ->all();
    }

    /**
     * @return array{total_sessions: int, unique_ips: int, total_commands: int, total_downloads: int}
     */
    public function getCowrieStats(): array
    {
        return [
            'total_sessions' => CowrieSession::count(),
            'unique_ips' => CowrieSession::distinct('ip_id')->count('ip_id'),
            'total_commands' => CowrieCommand::count(),
            'total_downloads' => CowrieDownload::count(),
        ];
    }

    /**
     * @return array<int, array{session: string, ip: string, country: string|null, country_code: string|null, isp: string|null, username: string|null, password: string|null, duration_seconds: float|null, started_at: string, commands: array<int, string>}>
     */
    public function getRecentCowrieSessions(int $limit = 20): array
    {
        return CowrieSession::with(['ip', 'login', 'commands'])
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'session' => $s->session,
                'ip' => $s->ip?->ip,
                'country' => $s->ip?->country,
                'country_code' => $s->ip?->country_code ? strtolower($s->ip->country_code) : null,
                'isp' => $s->ip?->isp,
                'username' => $s->login?->username,
                'password' => $s->login?->password,
                'duration_seconds' => $s->duration_seconds,
                'started_at' => $s->started_at?->toDateTimeString(),
                'commands' => $s->commands->pluck('input')->filter(fn ($c) => $c !== '')->values()->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{username: string, password: string, hit_count: int}>
     */
    public function getTopCredentials(int $limit = 20): array
    {
        return Credential::orderByDesc('hit_count')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'username' => $c->username,
                'password' => $c->password,
                'hit_count' => (int) $c->hit_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{input: string, count: int}>
     */
    public function getTopCowrieCommands(int $limit = 20): array
    {
        return CowrieCommand::select('input', DB::raw('COUNT(*) as count'))
            ->where('input', '!=', '')
            ->groupBy('input')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'input' => $row->input,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{url: string, filename: string|null, file_hash: string|null, count: int}>
     */
    public function getTopCowrieDownloads(int $limit = 20): array
    {
        return CowrieDownload::select('url', 'filename', 'file_hash', DB::raw('COUNT(*) as count'))
            ->groupBy('url', 'filename', 'file_hash')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'url' => $row->url,
                'filename' => $row->filename,
                'file_hash' => $row->file_hash,
                'count' => (int) $row->count,
            ])
            ->all();
    }
}
