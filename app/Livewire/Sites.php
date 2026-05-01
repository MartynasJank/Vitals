<?php

namespace App\Livewire;

use App\Services\SiteService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Sites extends Component
{
    /** @var array<int, array{name: string, url: string, status: string, status_code: int|null, response_ms: int|null}> */
    public array $sites = [];

    public function mount(): void
    {
        $this->check();
    }

    #[Poll('5m')]
    public function check(): void
    {
        $service = app(SiteService::class);

        $this->sites = collect($service->discoverSites())
            ->map(fn ($site) => array_merge(
                $site,
                $service->checkSite($site['url']),
                ['ssl_days' => $service->getSslExpiry(parse_url($site['url'], PHP_URL_HOST))]
            ))
            ->all();
    }

    public function checkNow(string $url): void
    {
        $service = app(SiteService::class);
        $result = $service->checkSite($url);

        $this->sites = collect($this->sites)
            ->map(fn ($site) => $site['url'] === $url ? array_merge($site, $result) : $site)
            ->all();
    }

    public function render(): View
    {
        return view('livewire.sites');
    }
}
