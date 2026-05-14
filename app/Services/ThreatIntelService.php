<?php

namespace App\Services;

use App\Models\CowrieCommand;
use App\Models\CowrieDownload;
use App\Models\CowrieLogin;
use App\Models\CowrieSession;
use App\Models\MalwareFile;
use App\Models\NginxHit;
use App\Models\SshAttempt;
use App\Models\ThreatIp;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ThreatIntelService
{
    const FINGERPRINT_PATTERNS = [
        '/uname/', '/whoami/', '/^id$/', '/hostname/', '/^pwd$/',
        '/cpuinfo/', '/proc\/uptime/', '/proc\/version/', '/ifconfig/',
        '/^echo/', '/^exit$/', '/^ls$/', '/os-release/', '/nproc/',
        '/lscpu/', '/PATH=.*uname/', '/ip cloud/', '/^cat --help/',
        '/^ls --help/', '/\/ip cloud print/',
    ];

    const THREAT_TAGS = [
        'crypto miner' => '/[Mm]iner|xmrig|monero|stratum\+|D877F|mining/',
        'telegram stealer' => '/TelegramDesktop|tdata/',
        'malware download' => '/wget|curl.*http|fetch.*http/',
        'persistence' => '/crontab|authorized_keys|systemctl enable|rc\.local/',
        'secret harvesting' => '/\benv\b|\.env|AWS_|SECRET|TOKEN|PASSWORD/',
        'credential dump' => '/\/etc\/shadow|\/etc\/passwd/',
        'backdoor' => '/useradd|adduser|passwd.*root/',
        'lateral movement' => '/ssh-keyscan|known_hosts|\.ssh\//',
        'shell execution' => '/chmod.*\+x|bash.*http|sh.*http|\.\/[a-z]/',
        'recon' => '/netstat|ss -|ps aux|ps -ef|mount|df -/',
    ];

    /** @var array<int, int>|null */
    private ?array $cachedIgnoredIpIds = null;

    private function localTime(Carbon $dt): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d H:i:s'), 'UTC')
            ->setTimezone(config('app.timezone'));
    }

    /** @return array<int, string> */
    private function ignoredIps(): array
    {
        return config('security.ignored_ips', []);
    }

    /** @return array<int, int> */
    private function ignoredIpIds(): array
    {
        if ($this->cachedIgnoredIpIds !== null) {
            return $this->cachedIgnoredIpIds;
        }

        $ips = $this->ignoredIps();

        if (empty($ips)) {
            return $this->cachedIgnoredIpIds = [];
        }

        return $this->cachedIgnoredIpIds = ThreatIp::whereIn('ip', $ips)->pluck('id')->all();
    }

    public function upsertIpWithEnrichment(string $ip): int
    {
        $record = ThreatIp::where('ip', $ip)->first();

        if ($record) {
            $record->increment('total_hits');
            $record->update(['last_seen' => now()]);

            return $record->id;
        }

        $geo = $this->fetchGeoData($ip);
        $privacy = $this->fetchPrivacyData($ip);

        [$isVpn, $isProxy, $isTor] = $this->resolvePrivacyFlags($privacy, $geo);

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
            'is_proxy' => $isProxy,
            'is_vpn' => $isVpn,
            'is_tor' => $isTor,
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
     * Fetches VPN/proxy/Tor flags from ipinfo.io.
     * Returns an empty array on failure or missing token — caller should fall back.
     *
     * @return array<string, mixed>
     */
    public function fetchPrivacyData(string $ip): array
    {
        $token = config('services.ipinfo.token');

        if (! $token) {
            return [];
        }

        try {
            $response = Http::timeout(5)->get("https://ipinfo.io/{$ip}/privacy", [
                'token' => $token,
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Exception) {
        }

        return [];
    }

    /**
     * Resolves is_vpn / is_proxy / is_tor from ipinfo.io privacy data.
     * Falls back to ip-api.com's combined `proxy` boolean when ipinfo.io is unavailable,
     * setting only is_proxy since the fallback cannot distinguish VPN from proxy.
     *
     * @param  array<string, mixed>  $privacy  ipinfo.io privacy response (may be empty)
     * @param  array<string, mixed>  $geo  ip-api.com geo response
     * @return array{bool, bool, bool} [is_vpn, is_proxy, is_tor]
     */
    public function resolvePrivacyFlags(array $privacy, array $geo): array
    {
        if (! empty($privacy)) {
            return [
                (bool) ($privacy['vpn'] ?? false),
                (bool) ($privacy['proxy'] ?? false),
                (bool) ($privacy['tor'] ?? false),
            ];
        }

        // Fallback: ip-api.com `proxy` covers all anonymisers as one signal
        return [false, (bool) ($geo['proxy'] ?? false), false];
    }

    /**
     * @return array<int, array{time: string, user: string, ip: string, country: string|null, country_code: string|null, isp: string|null, asn: string|null, is_proxy: bool, total_hits: int}>
     */
    public function getRecentSshdAttempts(int $limit = 20): array
    {
        return SshAttempt::with('ip')
            ->whereNotIn('ip_id', $this->ignoredIpIds())
            ->orderByDesc('timestamp')
            ->limit($limit)
            ->get()
            ->map(fn ($attempt) => [
                'time' => $attempt->timestamp ? $this->localTime($attempt->timestamp)->format('H:i:s') : null,
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
            '7d' => [now('UTC')->subDays(7), '%Y-%m-%d', 'Y-m-d'],
            '30d' => [now('UTC')->subDays(30), '%Y-%m-%d', 'Y-m-d'],
            default => [now('UTC')->subHours(24), '%Y-%m-%d %H:00', 'Y-m-d H:00'],
        };

        $tz = now()->format('P');

        $ignoredIpIds = $this->ignoredIpIds();

        $ssh = SshAttempt::select(DB::raw("DATE_FORMAT(CONVERT_TZ(timestamp, '+00:00', '{$tz}'), '{$groupFormat}') as label, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->groupBy('label')
            ->pluck('count', 'label');

        $cowrie = CowrieLogin::select(DB::raw("DATE_FORMAT(CONVERT_TZ(timestamp, '+00:00', '{$tz}'), '{$groupFormat}') as label, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))
            ->groupBy('label')
            ->pluck('count', 'label');

        $nginx = NginxHit::select(DB::raw("DATE_FORMAT(CONVERT_TZ(timestamp, '+00:00', '{$tz}'), '{$groupFormat}') as label, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->whereNotIn('ip_id', $ignoredIpIds)
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
            ->whereNotIn('ip', $this->ignoredIps())
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
            ->whereNotIn('ip', $this->ignoredIps())
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
            ->whereNotIn('ip', $this->ignoredIps())
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
        $ignoredIpIds = $this->ignoredIpIds();

        $ssh = SshAttempt::select('username', DB::raw('COUNT(*) as count'))
            ->whereNotNull('username')
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->groupBy('username')
            ->pluck('count', 'username');

        $cowrie = CowrieLogin::select('username', DB::raw('COUNT(*) as count'))
            ->whereNotNull('username')
            ->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))
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
            ->whereNotIn('ip_id', $this->ignoredIpIds())
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
            ->whereNotIn('ip_id', $this->ignoredIpIds())
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
        $ignoredIps = $this->ignoredIps();
        $total = ThreatIp::whereNotIn('ip', $ignoredIps)->count();

        if ($total === 0) {
            return 0.0;
        }

        $repeat = ThreatIp::where('total_hits', '>', 1)->whereNotIn('ip', $ignoredIps)->count();

        return round(($repeat / $total) * 100, 1);
    }

    /**
     * @return array<int, array{ip: string, country: string|null, country_code: string|null, isp: string|null, ssh_count: int, nginx_count: int, total_hits: int}>
     */
    public function getCrossSourceIps(): array
    {
        $ignoredIpIds = $this->ignoredIpIds();

        $sshIpIds = SshAttempt::select('ip_id')->whereNotIn('ip_id', $ignoredIpIds)->distinct()->pluck('ip_id');
        $cowrieIpIds = CowrieSession::select('ip_id')->whereNotIn('ip_id', $ignoredIpIds)->distinct()->pluck('ip_id');
        $allSshIpIds = $sshIpIds->merge($cowrieIpIds)->unique()->values();
        $nginxIpIds = NginxHit::select('ip_id')->whereNotIn('ip_id', $ignoredIpIds)->distinct()->pluck('ip_id');
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
            '7d' => now('UTC')->subDays(7),
            '30d' => now('UTC')->subDays(30),
            default => now('UTC')->subHours(24),
        };

        $tz = now()->format('P');

        $ignoredIpIds = $this->ignoredIpIds();

        $ssh = SshAttempt::select(DB::raw("HOUR(CONVERT_TZ(timestamp, '+00:00', '{$tz}')) as hour, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->groupBy('hour')
            ->pluck('count', 'hour');

        $cowrie = CowrieLogin::select(DB::raw("HOUR(CONVERT_TZ(timestamp, '+00:00', '{$tz}')) as hour, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))
            ->groupBy('hour')
            ->pluck('count', 'hour');

        $nginx = NginxHit::select(DB::raw("HOUR(CONVERT_TZ(timestamp, '+00:00', '{$tz}')) as hour, COUNT(*) as count"))
            ->where('timestamp', '>=', $since)
            ->whereNotIn('ip_id', $ignoredIpIds)
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
            DB::raw('SUM(total_hits) as count'),
            DB::raw('MAX(country) as country'),
            DB::raw('MAX(country_code) as country_code')
        )
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->whereNotIn('ip', $this->ignoredIps())
            ->groupBy('lat', 'lon')
            ->orderByDesc('count')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'lat' => (float) $row->lat,
                'lon' => (float) $row->lon,
                'count' => (int) $row->count,
                'country' => $row->country,
                'country_code' => $row->country_code,
            ])
            ->all();
    }

    public function getTotalAttacksLast24h(): int
    {
        $since = now('UTC')->subHours(24);
        $ignoredIpIds = $this->ignoredIpIds();

        return SshAttempt::where('timestamp', '>=', $since)->whereNotIn('ip_id', $ignoredIpIds)->count()
            + CowrieLogin::where('timestamp', '>=', $since)->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))->count()
            + NginxHit::where('timestamp', '>=', $since)->whereNotIn('ip_id', $ignoredIpIds)->count();
    }

    public function getTotalAttacksLastHour(): int
    {
        $since = now('UTC')->subHour();
        $ignoredIpIds = $this->ignoredIpIds();

        return SshAttempt::where('timestamp', '>=', $since)->whereNotIn('ip_id', $ignoredIpIds)->count()
            + CowrieLogin::where('timestamp', '>=', $since)->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))->count()
            + NginxHit::where('timestamp', '>=', $since)->whereNotIn('ip_id', $ignoredIpIds)->count();
    }

    /**
     * @return array<int, array{time: string, ip: string, country: string|null, country_code: string|null, method: string, path: string, status_code: int, scan_type: string}>
     */
    public function getRecentNginxHits(int $limit = 20): array
    {
        return NginxHit::with('ip')
            ->whereNotIn('ip_id', $this->ignoredIpIds())
            ->orderByDesc('timestamp')
            ->limit($limit)
            ->get()
            ->map(fn ($hit) => [
                'time' => $hit->timestamp ? $this->localTime($hit->timestamp)->format('H:i:s') : null,
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
            ->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $this->ignoredIpIds()))
            ->orderByDesc('timestamp')
            ->limit($limit)
            ->get()
            ->map(fn ($login) => [
                'time' => $login->timestamp ? $this->localTime($login->timestamp)->format('H:i:s') : null,
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
     * @return array{total_sessions: int, unique_ips: int, total_commands: int, total_downloads: int, interesting_sessions: int}
     */
    public function getCowrieStats(): array
    {
        $ignoredIpIds = $this->ignoredIpIds();

        return [
            'total_sessions' => CowrieSession::whereNotIn('ip_id', $ignoredIpIds)->count(),
            'unique_ips' => CowrieSession::whereNotIn('ip_id', $ignoredIpIds)->distinct('ip_id')->count('ip_id'),
            'total_commands' => CowrieCommand::whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))->count(),
            'total_downloads' => CowrieDownload::whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))->count(),
            'interesting_sessions' => $this->getInterestingSessionsCount(),
        ];
    }

    public function getInterestingSessionsCount(): int
    {
        $ignoredIpIds = $this->ignoredIpIds();
        $count = 0;

        CowrieSession::has('commands')
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->with(['commands:cowrie_session_id,input'])
            ->select('id')
            ->each(function ($session) use (&$count) {
                $commands = $session->commands->pluck('input')->filter(fn ($c) => $c !== '')->values()->all();
                if ($this->sessionIsInteresting($commands)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return array<int, array{session: string, ip: string|null, country: string|null, country_code: string|null, isp: string|null, username: string|null, password: string|null, duration_seconds: float|null, started_at: string|null, tags: array<int, string>, commands: array<int, array{input: string, class: string}>}>
     */
    public function getInterestingSessions(int $limit = 20): array
    {
        $ignoredIpIds = $this->ignoredIpIds();
        $results = [];

        CowrieSession::has('commands')
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->with(['ip', 'login', 'commands'])
            ->orderByDesc('started_at')
            ->each(function ($s) use (&$results, $limit) {
                if (count($results) >= $limit) {
                    return false;
                }

                $rawCommands = $s->commands->pluck('input')->filter(fn ($c) => $c !== '')->values()->all();

                if (! $this->sessionIsInteresting($rawCommands)) {
                    return true;
                }

                $tags = $this->detectTags($rawCommands);

                $classifiedCommands = array_map(function (string $cmd) {
                    if ($this->isFingerprinting($cmd)) {
                        return ['input' => $cmd, 'class' => 'fingerprint'];
                    }
                    foreach (self::THREAT_TAGS as $pattern) {
                        if (preg_match($pattern, $cmd)) {
                            return ['input' => $cmd, 'class' => 'threat'];
                        }
                    }

                    return ['input' => $cmd, 'class' => 'interesting'];
                }, $rawCommands);

                $results[] = [
                    'session' => $s->session,
                    'ip' => $s->ip?->ip,
                    'country' => $s->ip?->country,
                    'country_code' => $s->ip?->country_code ? strtolower($s->ip->country_code) : null,
                    'isp' => $s->ip?->isp,
                    'username' => $s->login?->username,
                    'password' => $s->login?->password,
                    'duration_seconds' => $s->duration_seconds,
                    'started_at' => $s->started_at ? $this->localTime($s->started_at)->toDateTimeString() : null,
                    'tags' => $tags,
                    'commands' => $classifiedCommands,
                ];
            });

        return $results;
    }

    /**
     * @return array<int, array{username: string, count: int}>
     */
    public function getTopUsernames(int $limit = 15): array
    {
        $ignoredIpIds = $this->ignoredIpIds();

        return CowrieLogin::select('username', DB::raw('COUNT(*) as count'))
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))
            ->groupBy('username')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'username' => $row->username,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{password: string, count: int}>
     */
    public function getTopPasswords(int $limit = 15): array
    {
        $ignoredIpIds = $this->ignoredIpIds();

        return CowrieLogin::select('password', DB::raw('COUNT(*) as count'))
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))
            ->groupBy('password')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'password' => $row->password,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    private function isFingerprinting(string $command): bool
    {
        foreach (self::FINGERPRINT_PATTERNS as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $commands */
    private function sessionIsInteresting(array $commands): bool
    {
        if (empty($commands)) {
            return false;
        }

        foreach ($commands as $cmd) {
            if (! $this->isFingerprinting($cmd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $commands
     * @return array<int, string>
     */
    private function detectTags(array $commands): array
    {
        $tags = [];
        $allCommands = implode(' ', $commands);

        foreach (self::THREAT_TAGS as $tag => $pattern) {
            if (preg_match($pattern, $allCommands)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @return array<int, array{session: string, ip: string, country: string|null, country_code: string|null, isp: string|null, username: string|null, password: string|null, duration_seconds: float|null, started_at: string, commands: array<int, string>}>
     */
    public function getRecentCowrieSessions(int $limit = 20): array
    {
        return CowrieSession::with(['ip', 'login', 'commands'])
            ->whereNotIn('ip_id', $this->ignoredIpIds())
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
                'started_at' => $s->started_at ? $this->localTime($s->started_at)->toDateTimeString() : null,
                'commands' => $s->commands->pluck('input')->filter(fn ($c) => $c !== '')->values()->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{username: string, password: string, hit_count: int}>
     */
    public function getTopCredentials(int $limit = 20): array
    {
        $ignoredIpIds = $this->ignoredIpIds();

        return CowrieLogin::select('username', 'password', DB::raw('COUNT(*) as hit_count'))
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))
            ->groupBy('username', 'password')
            ->orderByDesc('hit_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'username' => $row->username,
                'password' => $row->password,
                'hit_count' => (int) $row->hit_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{input: string, count: int}>
     */
    public function getTopCowrieCommands(int $limit = 20): array
    {
        $ignoredIpIds = $this->ignoredIpIds();

        return CowrieCommand::select('input', DB::raw('COUNT(*) as count'))
            ->where('input', '!=', '')
            ->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))
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
        $ignoredIpIds = $this->ignoredIpIds();

        return CowrieDownload::select('url', 'filename', 'file_hash', DB::raw('COUNT(*) as count'))
            ->whereHas('session', fn ($q) => $q->whereNotIn('ip_id', $ignoredIpIds))
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

    /**
     * @return array{vpn: int, proxy: int, tor: int, clean: int, total: int}
     */
    public function getAnonymiserBreakdown(): array
    {
        $base = ThreatIp::whereNotIn('ip', $this->ignoredIps());

        $total = (clone $base)->count();
        $vpn = (clone $base)->where('is_vpn', true)->count();
        $proxy = (clone $base)->where('is_proxy', true)->where('is_vpn', false)->count();
        $tor = (clone $base)->where('is_tor', true)->count();
        $clean = (clone $base)->where('is_vpn', false)->where('is_proxy', false)->where('is_tor', false)->count();

        return compact('vpn', 'proxy', 'tor', 'clean', 'total');
    }

    /**
     * @return array<int, array{asn: string, org: string|null, count: int}>
     */
    public function getTopAsns(int $limit = 10): array
    {
        return ThreatIp::select('asn', 'org', DB::raw('COUNT(*) as count'))
            ->whereNotNull('asn')
            ->whereNotIn('ip', $this->ignoredIps())
            ->groupBy('asn', 'org')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'asn' => $row->asn,
                'org' => $row->org,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{ip: string, country: string|null, country_code: string|null, isp: string|null, ssh: int, nginx: int, total: int}>
     */
    public function getTopIpsByHits(string $range, int $limit = 10): array
    {
        $since = match ($range) {
            '7d' => now('UTC')->subDays(7),
            '30d' => now('UTC')->subDays(30),
            default => now('UTC')->subHours(24),
        };

        $ignoredIpIds = $this->ignoredIpIds();

        $ssh = SshAttempt::select('ip_id', DB::raw('COUNT(*) as count'))
            ->where('timestamp', '>=', $since)
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->groupBy('ip_id')
            ->pluck('count', 'ip_id');

        $cowrie = CowrieSession::select('ip_id', DB::raw('COUNT(*) as count'))
            ->where('started_at', '>=', $since)
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->groupBy('ip_id')
            ->pluck('count', 'ip_id');

        $nginx = NginxHit::select('ip_id', DB::raw('COUNT(*) as count'))
            ->where('timestamp', '>=', $since)
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->groupBy('ip_id')
            ->pluck('count', 'ip_id');

        $ipIds = $ssh->keys()->merge($cowrie->keys())->merge($nginx->keys())->unique();

        $totals = $ipIds->mapWithKeys(fn ($id) => [
            $id => [
                'ssh' => (int) ($ssh[$id] ?? 0) + (int) ($cowrie[$id] ?? 0),
                'nginx' => (int) ($nginx[$id] ?? 0),
                'total' => (int) ($ssh[$id] ?? 0) + (int) ($cowrie[$id] ?? 0) + (int) ($nginx[$id] ?? 0),
            ],
        ])->sortByDesc('total')->take($limit);

        if ($totals->isEmpty()) {
            return [];
        }

        $threatIps = ThreatIp::whereIn('id', $totals->keys())->get()->keyBy('id');

        return $totals->map(fn ($counts, $id) => [
            'ip' => $threatIps[$id]?->ip ?? '—',
            'country' => $threatIps[$id]?->country,
            'country_code' => $threatIps[$id]?->country_code ? strtolower($threatIps[$id]->country_code) : null,
            'isp' => $threatIps[$id]?->isp,
            'ssh' => $counts['ssh'],
            'nginx' => $counts['nginx'],
            'total' => $counts['total'],
        ])->values()->all();
    }

    /**
     * @return array{date: string, hour: int, label: string, ssh_count: int, nginx_count: int, cowrie_count: int, unique_ips: int, ssh_attempts: array, nginx_hits: array, cowrie_sessions: array}
     */
    public function getHourlyBreakdown(string $date, int $hour): array
    {
        $tz = config('app.timezone');
        $start = Carbon::createFromFormat('Y-m-d H', "$date $hour", $tz)->startOfHour();
        $end = $start->copy()->addHour();
        $startUtc = $start->copy()->setTimezone('UTC');
        $endUtc = $end->copy()->setTimezone('UTC');

        $ignoredIpIds = $this->ignoredIpIds();

        $sshAttempts = SshAttempt::with('ip')
            ->whereBetween('timestamp', [$startUtc, $endUtc])
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->orderBy('timestamp')
            ->limit(200)
            ->get()
            ->map(fn ($a) => [
                'timestamp' => $a->timestamp ? $this->localTime($a->timestamp)->format('H:i:s') : null,
                'ip' => $a->ip?->ip ?? '—',
                'country' => $a->ip?->country,
                'country_code' => $a->ip?->country_code ? strtolower($a->ip->country_code) : null,
                'username' => $a->username,
                'isp' => $a->ip?->isp,
            ])
            ->all();

        $nginxHits = NginxHit::with('ip')
            ->whereBetween('timestamp', [$startUtc, $endUtc])
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->orderBy('timestamp')
            ->limit(200)
            ->get()
            ->map(fn ($h) => [
                'timestamp' => $h->timestamp ? $this->localTime($h->timestamp)->format('H:i:s') : null,
                'ip' => $h->ip?->ip ?? '—',
                'country' => $h->ip?->country,
                'country_code' => $h->ip?->country_code ? strtolower($h->ip->country_code) : null,
                'method' => $h->method,
                'path' => $h->path,
                'status_code' => $h->status_code,
                'scan_type' => $h->scan_type,
            ])
            ->all();

        $cowrieSessions = CowrieSession::with(['ip', 'login', 'commands'])
            ->whereBetween('started_at', [$startUtc, $endUtc])
            ->whereNotIn('ip_id', $ignoredIpIds)
            ->orderBy('started_at')
            ->limit(100)
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
                'started_at' => $s->started_at ? $this->localTime($s->started_at)->format('H:i:s') : null,
                'commands' => $s->commands->pluck('input')->filter()->values()->all(),
            ])
            ->all();

        $uniqueIps = collect($sshAttempts)->pluck('ip')
            ->merge(collect($nginxHits)->pluck('ip'))
            ->merge(collect($cowrieSessions)->pluck('ip'))
            ->filter(fn ($ip) => $ip && $ip !== '—')
            ->unique()
            ->count();

        $hourPadded = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
        $nextHour = str_pad((string) ($hour + 1), 2, '0', STR_PAD_LEFT);

        return [
            'date' => $date,
            'hour' => $hour,
            'label' => "$date {$hourPadded}:00 – {$nextHour}:00",
            'ssh_count' => count($sshAttempts),
            'nginx_count' => count($nginxHits),
            'cowrie_count' => count($cowrieSessions),
            'unique_ips' => $uniqueIps,
            'ssh_attempts' => $sshAttempts,
            'nginx_hits' => $nginxHits,
            'cowrie_sessions' => $cowrieSessions,
        ];
    }

    /**
     * @return array{profile: ThreatIp, ssh_count: int, nginx_count: int, cowrie_count: int, malware_count: int, ssh_attempts: array, nginx_hits: array, cowrie_sessions: array, malware_files: array}|null
     */
    public function getIpProfile(string $ip): ?array
    {
        $threatIp = ThreatIp::where('ip', $ip)->first();
        if (! $threatIp) {
            return null;
        }

        $sshAttempts = SshAttempt::where('ip_id', $threatIp->id)
            ->orderByDesc('timestamp')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'username' => $a->username,
                'timestamp' => $a->timestamp?->format('Y-m-d H:i'),
            ])
            ->all();

        $nginxHits = NginxHit::where('ip_id', $threatIp->id)
            ->orderByDesc('timestamp')
            ->limit(50)
            ->get()
            ->map(fn ($h) => [
                'path' => $h->path,
                'method' => $h->method,
                'status_code' => $h->status_code,
                'scan_type' => $h->scan_type,
                'user_agent' => $h->user_agent,
                'timestamp' => $h->timestamp?->format('Y-m-d H:i'),
            ])
            ->all();

        $sessionIds = CowrieSession::where('ip_id', $threatIp->id)->pluck('id');

        $cowrieSessions = CowrieSession::whereIn('id', $sessionIds)
            ->with(['login', 'commands', 'downloads'])
            ->orderByDesc('started_at')
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'started_at' => $s->started_at?->format('Y-m-d H:i'),
                'duration_seconds' => $s->duration_seconds,
                'is_interesting' => $s->is_interesting,
                'username' => $s->login?->username,
                'password' => $s->login?->password,
                'is_success' => $s->login?->is_success ?? false,
                'commands' => $s->commands->map(fn ($c) => $c->input)->filter()->values()->all(),
                'downloads' => $s->downloads->map(fn ($d) => [
                    'type' => $d->url ? 'download' : 'upload',
                    'label' => $d->url ?: ($d->filename ?? $d->file_hash),
                ])->values()->all(),
            ])
            ->all();

        $fileHashes = CowrieDownload::whereIn('cowrie_session_id', $sessionIds)
            ->whereNotNull('file_hash')
            ->pluck('file_hash')
            ->unique()
            ->values();

        $malwareFiles = MalwareFile::with('highlights')
            ->whereIn('sha256', $fileHashes)
            ->get()
            ->all();

        $tz = now()->format('P');

        $sshUsernames = SshAttempt::select('username', DB::raw('COUNT(*) as count'))
            ->where('ip_id', $threatIp->id)
            ->whereNotNull('username')
            ->groupBy('username')
            ->pluck('count', 'username');

        $cowrieUsernames = CowrieLogin::select('username', DB::raw('COUNT(*) as count'))
            ->whereIn('cowrie_session_id', $sessionIds)
            ->whereNotNull('username')
            ->groupBy('username')
            ->pluck('count', 'username');

        $topSshUsernames = $sshUsernames->mergeRecursive($cowrieUsernames)
            ->map(fn ($v) => is_array($v) ? array_sum($v) : (int) $v)
            ->sortDesc()
            ->take(10)
            ->map(fn ($count, $username) => ['username' => $username, 'count' => (int) $count])
            ->values()
            ->all();

        $nginxScanTypes = NginxHit::select('scan_type', DB::raw('COUNT(*) as count'))
            ->where('ip_id', $threatIp->id)
            ->groupBy('scan_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['scan_type' => $r->scan_type ?? 'other', 'count' => (int) $r->count])
            ->all();

        $topNginxPaths = NginxHit::select('path', 'scan_type', DB::raw('COUNT(*) as count'))
            ->where('ip_id', $threatIp->id)
            ->whereNotNull('path')
            ->groupBy('path', 'scan_type')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->map(fn ($r) => ['path' => $r->path, 'scan_type' => $r->scan_type, 'count' => (int) $r->count])
            ->all();

        $sshByHour = SshAttempt::select(DB::raw("HOUR(CONVERT_TZ(timestamp, '+00:00', '{$tz}')) as hour, COUNT(*) as count"))
            ->where('ip_id', $threatIp->id)
            ->groupBy('hour')
            ->pluck('count', 'hour');

        $cowrieByHour = CowrieSession::select(DB::raw("HOUR(CONVERT_TZ(started_at, '+00:00', '{$tz}')) as hour, COUNT(*) as count"))
            ->where('ip_id', $threatIp->id)
            ->groupBy('hour')
            ->pluck('count', 'hour');

        $nginxByHour = NginxHit::select(DB::raw("HOUR(CONVERT_TZ(timestamp, '+00:00', '{$tz}')) as hour, COUNT(*) as count"))
            ->where('ip_id', $threatIp->id)
            ->groupBy('hour')
            ->pluck('count', 'hour');

        $activityByHour = array_fill(0, 24, 0);
        for ($h = 0; $h < 24; $h++) {
            $activityByHour[$h] = (int) ($sshByHour[$h] ?? 0) + (int) ($cowrieByHour[$h] ?? 0) + (int) ($nginxByHour[$h] ?? 0);
        }

        return [
            'profile' => $threatIp,
            'ssh_count' => SshAttempt::where('ip_id', $threatIp->id)->count(),
            'nginx_count' => NginxHit::where('ip_id', $threatIp->id)->count(),
            'cowrie_count' => count($sessionIds),
            'malware_count' => count($malwareFiles),
            'ssh_attempts' => $sshAttempts,
            'nginx_hits' => $nginxHits,
            'cowrie_sessions' => $cowrieSessions,
            'malware_files' => $malwareFiles,
            'top_ssh_usernames' => $topSshUsernames,
            'nginx_scan_types' => $nginxScanTypes,
            'top_nginx_paths' => $topNginxPaths,
            'activity_by_hour' => $activityByHour,
        ];
    }
}
