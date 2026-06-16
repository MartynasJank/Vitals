<?php

namespace App\Services;

use App\Models\SiteCheck;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SiteService
{
    /**
     * @return array<int, array{name: string, url: string}>
     */
    public function discoverSites(): array
    {
        $sites = [];
        $dir = '/etc/nginx/sites-enabled';

        if (! is_dir($dir)) {
            return $sites;
        }

        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = "$dir/$file";
            $content = file_get_contents(is_link($path) ? readlink($path) : $path);

            if (! $content) {
                continue;
            }

            preg_match_all('/server_name\s+([^;]+);/', $content, $matches);

            foreach ($matches[1] as $serverNames) {
                foreach (preg_split('/\s+/', trim($serverNames)) as $domain) {
                    if ($domain === '_' || str_starts_with($domain, '.') || str_starts_with($domain, 'www.')) {
                        continue;
                    }

                    $sites[] = [
                        'name' => $domain,
                        'url' => 'https://'.$domain,
                    ];
                }
            }
        }

        return $sites;
    }

    public function getSslExpiry(string $domain): ?int
    {
        if (! preg_match('/^[a-zA-Z0-9.\-]+$/', $domain)) {
            return null;
        }

        return Cache::remember("ssl_expiry_{$domain}", 3600, function () use ($domain) {
            $output = shell_exec(
                'echo | openssl s_client -connect '.escapeshellarg($domain.':443').
                ' -servername '.escapeshellarg($domain).' 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null'
            );

            if (! $output) {
                return null;
            }

            preg_match('/notAfter=(.+)/', $output, $matches);

            if (! isset($matches[1])) {
                return null;
            }

            $expiry = strtotime(trim($matches[1]));

            return $expiry ? (int) ceil(($expiry - time()) / 86400) : null;
        });
    }

    public function getNginxConfig(string $domain): ?string
    {
        if (! preg_match('/^[a-zA-Z0-9.\-]+$/', $domain)) {
            return null;
        }

        $dir = '/etc/nginx/sites-enabled';

        if (! is_dir($dir)) {
            return null;
        }

        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = "$dir/$file";
            $realPath = is_link($path) ? readlink($path) : $path;
            $content = file_get_contents($realPath);

            if (! $content) {
                continue;
            }

            if (preg_match('/server_name[^;]*\b'.preg_quote($domain, '/').'(\s|;)/', $content)) {
                return $content;
            }
        }

        return null;
    }

    /**
     * @param  string[]  $urls
     * @return array<string, array{uptime_24h: float, avg_ms: int|null, p95_ms: int|null, max_ms: int|null, last_down_at: string|null, check_count: int, recent_statuses: string[]}>
     */
    public function getSiteStats(array $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        $since = now()->subHours(24);

        $agg = SiteCheck::selectRaw('url, COUNT(*) as cnt, SUM(status = "up") as up_cnt, ROUND(AVG(response_ms)) as avg_ms, MAX(response_ms) as max_ms')
            ->whereIn('url', $urls)
            ->where('checked_at', '>=', $since)
            ->groupBy('url')
            ->get()
            ->keyBy('url');

        $totals = SiteCheck::selectRaw('url, COUNT(*) as cnt')
            ->whereIn('url', $urls)
            ->groupBy('url')
            ->get()
            ->keyBy('url');

        $lastDown = SiteCheck::selectRaw('url, MAX(checked_at) as ts')
            ->whereIn('url', $urls)
            ->where('status', 'down')
            ->groupBy('url')
            ->get()
            ->keyBy('url');

        $recent = SiteCheck::whereIn('url', $urls)
            ->orderByDesc('checked_at')
            ->get(['url', 'status'])
            ->groupBy('url')
            ->map(fn ($g) => $g->take(20)->reverse()->values()->pluck('status')->all());

        $p95 = SiteCheck::whereIn('url', $urls)
            ->where('checked_at', '>=', $since)
            ->whereNotNull('response_ms')
            ->orderBy('response_ms')
            ->get(['url', 'response_ms'])
            ->groupBy('url')
            ->map(function ($checks) {
                $values = $checks->pluck('response_ms')->values();
                $count = $values->count();

                return $count > 0 ? $values[(int) ceil(0.95 * $count) - 1] : null;
            });

        $stats = [];
        foreach ($urls as $url) {
            $a = $agg->get($url);
            $t = $totals->get($url);
            $d = $lastDown->get($url);

            $stats[$url] = [
                'uptime_24h' => $a && $a->cnt > 0 ? round(($a->up_cnt / $a->cnt) * 100, 1) : 100.0,
                'avg_ms' => $a ? (int) $a->avg_ms : null,
                'p95_ms' => $p95->has($url) ? (int) $p95->get($url) : null,
                'max_ms' => $a ? (int) $a->max_ms : null,
                'last_down_at' => $d?->ts ? Carbon::parse($d->ts)->diffForHumans() : null,
                'check_count' => $t ? (int) $t->cnt : 0,
                'recent_statuses' => $recent->get($url, []),
            ];
        }

        return $stats;
    }

    /**
     * @return array<int, array{started_at: string, duration_min: int, resolved: bool}>
     */
    public function getDowntimeIncidents(string $url, int $limit = 10): array
    {
        $checks = SiteCheck::where('url', $url)
            ->where('checked_at', '>=', now()->subDays(30))
            ->orderBy('checked_at')
            ->get(['checked_at', 'status']);

        if ($checks->isEmpty()) {
            return [];
        }

        $incidents = [];
        $downStart = null;

        foreach ($checks as $check) {
            if ($check->status === 'down' && $downStart === null) {
                $downStart = $check->checked_at;
            } elseif ($check->status === 'up' && $downStart !== null) {
                $incidents[] = [
                    'started_at' => $downStart->diffForHumans(),
                    'duration_min' => (int) $downStart->diffInMinutes($check->checked_at),
                    'resolved' => true,
                ];
                $downStart = null;
            }
        }

        if ($downStart !== null) {
            $incidents[] = [
                'started_at' => $downStart->diffForHumans(),
                'duration_min' => (int) $downStart->diffInMinutes(now()),
                'resolved' => false,
            ];
        }

        return array_slice(array_reverse($incidents), 0, $limit);
    }

    /**
     * @return array{url: string, status: string, status_code: int|null, response_ms: int|null, security_headers: array{hsts: bool, xcto: bool, xfo: bool, csp: bool}|null, redirects_to_https: bool|null}
     */
    public function checkSite(string $url): array
    {
        $start = hrtime(true);

        try {
            $response = Http::timeout(10)->withHeaders(['User-Agent' => 'Vitals-Monitor/1.0'])->get($url);
            $ms = (int) ((hrtime(true) - $start) / 1_000_000);

            $h = $response->headers();
            $securityHeaders = [
                'hsts' => ! empty($h['strict-transport-security']),
                'xcto' => ! empty($h['x-content-type-options']),
                'xfo' => ! empty($h['x-frame-options']),
                'csp' => ! empty($h['content-security-policy']),
            ];

            $redirectsToHttps = null;
            $parsed = parse_url($url);
            if (($parsed['scheme'] ?? '') === 'https' && ! empty($parsed['host'])) {
                $httpUrl = 'http://'.$parsed['host'].($parsed['path'] ?? '/');
                try {
                    $redirect = Http::timeout(5)->withHeaders(['User-Agent' => 'Vitals-Monitor/1.0'])->withoutRedirecting()->get($httpUrl);
                    $location = $redirect->header('Location') ?? '';
                    $redirectsToHttps = str_starts_with($location, 'https://');
                } catch (\Exception) {
                    $redirectsToHttps = false;
                }
            }

            return [
                'url' => $url,
                'status' => $response->successful() ? 'up' : 'down',
                'status_code' => $response->status(),
                'response_ms' => $ms,
                'security_headers' => $securityHeaders,
                'redirects_to_https' => $redirectsToHttps,
            ];
        } catch (\Exception) {
            return [
                'url' => $url,
                'status' => 'down',
                'status_code' => null,
                'response_ms' => null,
                'security_headers' => null,
                'redirects_to_https' => null,
            ];
        }
    }
}
