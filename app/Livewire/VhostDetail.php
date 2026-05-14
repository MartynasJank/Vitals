<?php

namespace App\Livewire;

use App\Services\ThreatIntelService;
use Illuminate\View\View;
use Livewire\Component;

class VhostDetail extends Component
{
    public string $vhost = '';

    public int $totalHits = 0;

    public int $uniqueIps = 0;

    public ?string $firstHit = null;

    public ?string $lastHit = null;

    /** @var array<int, array{scan_type: string, count: int}> */
    public array $scanTypes = [];

    /** @var array<int, array{path: string, scan_type: string|null, count: int}> */
    public array $topPaths = [];

    /** @var array<int, array{ip: string, country: string|null, country_code: string|null, isp: string|null, count: int}> */
    public array $topIps = [];

    /** @var array<int, int> */
    public array $activityByHour = [];

    public function mount(string $vhost): void
    {
        $this->vhost = $vhost;

        $data = app(ThreatIntelService::class)->getVhostProfile($vhost);

        $this->totalHits = $data['total_hits'];
        $this->uniqueIps = $data['unique_ips'];
        $this->firstHit = $data['first_hit'];
        $this->lastHit = $data['last_hit'];
        $this->scanTypes = $data['scan_types'];
        $this->topPaths = $data['top_paths'];
        $this->topIps = $data['top_ips'];
        $this->activityByHour = $data['activity_by_hour'];
    }

    public function render(): View
    {
        return view('livewire.vhost-detail');
    }
}
