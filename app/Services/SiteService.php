<?php

namespace App\Services;

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
                    if ($domain === '_' || str_starts_with($domain, '.')) {
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

    /**
     * @return array{url: string, status: string, status_code: int|null, response_ms: int|null}
     */
    public function checkSite(string $url): array
    {
        $start = hrtime(true);

        try {
            $response = Http::timeout(10)->get($url);
            $ms = (int) ((hrtime(true) - $start) / 1_000_000);

            return [
                'url' => $url,
                'status' => $response->successful() ? 'up' : 'down',
                'status_code' => $response->status(),
                'response_ms' => $ms,
            ];
        } catch (\Exception) {
            return [
                'url' => $url,
                'status' => 'down',
                'status_code' => null,
                'response_ms' => null,
            ];
        }
    }
}
