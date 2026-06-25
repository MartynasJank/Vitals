<?php

namespace App\Livewire;

use App\Services\ThreatIntelService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class ThreatIntel extends Component
{
    public string $timeRange = '24h';

    public string $countryFilter = '';

    /** @var array<int, array{country: string, country_code: string}> */
    public array $availableCountries = [];

    /** @var array<int, array{label: string, ssh: int, nginx: int}> */
    public array $attackVolume = [];

    /** @var array<int, array{country: string, country_code: string, count: int}> */
    public array $topCountries = [];

    /** @var array<int, array{isp: string, count: int}> */
    public array $topIsps = [];

    /** @var array<int, array{org: string, count: int}> */
    public array $topOrgs = [];

    /** @var array<int, array{referer: string, count: int}> */
    public array $topReferers = [];

    /** @var array<int, array{username: string, count: int}> */
    public array $topUsernames = [];

    /** @var array<int, array{path: string, count: int, scan_type: string}> */
    public array $topPaths = [];

    public float $repeatOffenderRate = 0.0;

    /** @var array<int, array{ip: string, country: string|null, country_code: string|null, isp: string|null, ssh_count: int, nginx_count: int, total_hits: int}> */
    public array $crossSourceIps = [];

    /** @var array<int, int> */
    public array $attackHeatmap = [];

    /** @var array<int, array{lat: float, lon: float, count: int}> */
    public array $attackOrigins = [];

    /** @var array{vpn: int, proxy: int, tor: int, clean: int, total: int} */
    public array $anonymiserBreakdown = ['vpn' => 0, 'proxy' => 0, 'tor' => 0, 'clean' => 0, 'total' => 0];

    /** @var array<int, array{asn: string, org: string|null, count: int}> */
    public array $topAsns = [];

    /** @var array<int, array{vhost: string, count: int}> */
    public array $topVhosts = [];

    public function mount(): void
    {
        $this->availableCountries = app(ThreatIntelService::class)->getDistinctCountries();
        $this->loadData();
    }

    #[Poll('60s')]
    public function loadData(): void
    {
        try {
            $service = app(ThreatIntelService::class);
            $country = $this->countryFilter ?: null;

            $this->attackVolume = $service->getAttackVolumeOverTime($this->timeRange, $country);
            $this->topCountries = $service->getTopSourceCountries();
            $this->topIsps = $service->getTopIsps(countryCode: $country);
            $this->topOrgs = $service->getTopOrgs(countryCode: $country);
            $this->topReferers = $service->getTopReferers(countryCode: $country);
            $this->topUsernames = $service->getTopSshUsernames(countryCode: $country);
            $this->topPaths = $service->getTopNginxPaths(countryCode: $country);
            $this->repeatOffenderRate = $service->getRepeatOffenderRate($country);
            $this->crossSourceIps = $service->getCrossSourceIps($country);
            $this->attackHeatmap = $service->getAttackHeatmap($this->timeRange, $country);
            $this->attackOrigins = $service->getAttackOrigins();
            $this->anonymiserBreakdown = $service->getAnonymiserBreakdown($country);
            $this->topAsns = $service->getTopAsns(countryCode: $country);
            $this->topVhosts = $service->getTopTargetedVhosts($this->timeRange, $country);
        } catch (\Exception) {
            // Degrade gracefully if the threat DB is not yet configured
        }
    }

    public function setRange(string $range): void
    {
        $this->timeRange = $range;
        $this->loadData();
    }

    public function updatedCountryFilter(): void
    {
        $this->loadData();
    }

    public function render(): View
    {
        return view('livewire.threat_intel');
    }
}
