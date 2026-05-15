<?php

namespace App\Livewire;

use App\Models\SiteCheck;
use App\Services\SiteService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Sites extends Component
{
    /** @var array<int, array{name: string, url: string, status: string, status_code: int|null, response_ms: int|null, ssl_days: int|null}> */
    public array $sites = [];

    public ?string $selectedSite = null;

    /** @var array<int, array{time: string, response_ms: int|null, status: string}> */
    public array $siteHistory = [];

    /** @var array<int, array{started_at: string, duration_min: int, resolved: bool}> */
    public array $siteIncidents = [];

    public ?string $nginxConfig = null;

    public function mount(): void
    {
        $this->check();
    }

    #[Poll('5m')]
    public function check(): void
    {
        $service = app(SiteService::class);

        $this->sites = collect($service->discoverSites())
            ->map(function ($site) use ($service) {
                $result = $service->checkSite($site['url']);

                SiteCheck::create([
                    'site_name' => $site['name'],
                    'url' => $site['url'],
                    'status' => $result['status'],
                    'response_ms' => $result['response_ms'],
                    'status_code' => $result['status_code'],
                    'checked_at' => now(),
                ]);

                return array_merge($site, $result, [
                    'ssl_days' => $service->getSslExpiry(parse_url($site['url'], PHP_URL_HOST)),
                ]);
            })
            ->all();

        $this->refreshStats();

        if ($this->selectedSite) {
            $this->loadSiteDetail($this->selectedSite);
        }
    }

    public function checkNow(string $url): void
    {
        $service = app(SiteService::class);
        $result = $service->checkSite($url);

        SiteCheck::create([
            'site_name' => collect($this->sites)->firstWhere('url', $url)['name'] ?? $url,
            'url' => $url,
            'status' => $result['status'],
            'response_ms' => $result['response_ms'],
            'status_code' => $result['status_code'],
            'checked_at' => now(),
        ]);

        $this->sites = collect($this->sites)
            ->map(fn ($site) => $site['url'] === $url ? array_merge($site, $result) : $site)
            ->all();

        $this->refreshStats();

        if ($this->selectedSite === $url) {
            $this->loadSiteDetail($url);
        }
    }

    private function refreshStats(): void
    {
        $urls = collect($this->sites)->pluck('url')->all();
        $stats = app(SiteService::class)->getSiteStats($urls);

        $this->sites = collect($this->sites)
            ->map(fn ($site) => array_merge($site, $stats[$site['url']] ?? []))
            ->all();
    }

    public function selectSite(string $url): void
    {
        if ($this->selectedSite === $url) {
            $this->selectedSite = null;
            $this->siteHistory = [];
            $this->siteIncidents = [];
            $this->nginxConfig = null;

            return;
        }

        $this->selectedSite = $url;
        $this->loadSiteDetail($url);
    }

    public function render(): View
    {
        return view('livewire.sites');
    }

    private function loadSiteDetail(string $url): void
    {
        $this->siteHistory = SiteCheck::where('url', $url)
            ->orderBy('checked_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($check) => [
                'time' => $check->checked_at->format('H:i'),
                'response_ms' => $check->response_ms,
                'status' => $check->status,
            ])
            ->all();

        $service = app(SiteService::class);
        $this->siteIncidents = $service->getDowntimeIncidents($url);

        $domain = parse_url($url, PHP_URL_HOST);
        $this->nginxConfig = $service->getNginxConfig($domain);
    }
}
