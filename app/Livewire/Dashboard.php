<?php

namespace App\Livewire;

use App\Models\ResourceSnapshot;
use App\Models\SiteCheck;
use App\Services\ServerService;
use App\Services\SystemServiceManager;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Dashboard extends Component
{
    public float $cpuPercent = 0.0;

    public int $ramUsedMb = 0;

    public int $ramTotalMb = 0;

    public float $diskUsedGb = 0.0;

    public float $diskTotalGb = 0.0;

    public float $diskPercent = 0.0;

    /** @var array{used_mb: int, total_mb: int} */
    public array $swap = ['used_mb' => 0, 'total_mb' => 0];

    /** @var array{one: float, five: float, fifteen: float} */
    public array $loadAverage = ['one' => 0.0, 'five' => 0.0, 'fifteen' => 0.0];

    public int $coreCount = 1;

    public string $uptime = '';

    /** @var array<int, array{site_name: string, url: string, status: string, response_ms: int|null}> */
    public array $siteStatuses = [];

    /** @var array<int, array{label: string, running: bool}> */
    public array $services = [];

    /** @var array<int, array{level: string, message: string}> */
    public array $alerts = [];

    #[Poll('5s')]
    public function refresh(): void
    {
        $server = app(ServerService::class);

        $this->cpuPercent = $server->getCpuPercent();

        $ram = $server->getRamStats();
        $this->ramUsedMb = $ram['used_mb'];
        $this->ramTotalMb = $ram['total_mb'];

        $disk = $server->getDiskStats();
        $this->diskUsedGb = $disk['used_gb'];
        $this->diskTotalGb = $disk['total_gb'];
        $this->diskPercent = $disk['percent'];

        $this->swap = $server->getSwapStats();
        $this->loadAverage = $server->getLoadAverage();
        $this->coreCount = $server->getCoreCount();
        $this->uptime = $server->getServerUptime();

        $this->siteStatuses = SiteCheck::whereIn('id', function ($q) {
            $q->selectRaw('MAX(id)')->from('site_checks')->groupBy('url');
        })
            ->get(['site_name', 'url', 'status', 'response_ms'])
            ->map(fn ($check) => [
                'site_name' => $check->site_name,
                'url' => $check->url,
                'status' => $check->status,
                'response_ms' => $check->response_ms,
            ])
            ->all();

        $this->services = collect(app(SystemServiceManager::class)->getAll())
            ->map(fn ($s) => ['label' => $s['label'], 'running' => $s['running'], 'restarting' => $s['restarting']])
            ->values()
            ->all();

        $this->alerts = $this->computeAlerts();
    }

    public function mount(): void
    {
        $this->refresh();
    }

    public function render(): View
    {
        $snapshots = ResourceSnapshot::orderBy('recorded_at')
            ->latest('recorded_at')
            ->limit(60)
            ->get()
            ->reverse()
            ->values();

        return view('livewire.dashboard', [
            'cpuHistory' => $snapshots->pluck('cpu_percent'),
            'ramHistory' => $snapshots->map(fn ($s) => $s->ram_total_mb > 0
                ? round($s->ram_used_mb / $s->ram_total_mb * 100, 1)
                : 0
            ),
        ]);
    }

    /** @return array<int, array{level: string, message: string}> */
    private function computeAlerts(): array
    {
        $alerts = [];

        if ($this->cpuPercent > 80) {
            $alerts[] = ['level' => 'error', 'message' => 'High CPU usage: '.number_format($this->cpuPercent, 1).'%'];
        } elseif ($this->cpuPercent > 60) {
            $alerts[] = ['level' => 'warning', 'message' => 'Elevated CPU usage: '.number_format($this->cpuPercent, 1).'%'];
        }

        $ramPercent = $this->ramTotalMb > 0 ? ($this->ramUsedMb / $this->ramTotalMb) * 100 : 0;

        if ($ramPercent > 85) {
            $alerts[] = ['level' => 'error', 'message' => 'High RAM usage: '.number_format($ramPercent, 0).'%'];
        }

        if ($this->diskPercent > 85) {
            $alerts[] = ['level' => 'error', 'message' => 'Low disk space: '.number_format($this->diskPercent, 0).'% used'];
        } elseif ($this->diskPercent > 70) {
            $alerts[] = ['level' => 'warning', 'message' => 'Disk usage at '.number_format($this->diskPercent, 0).'%'];
        }

        if ($this->loadAverage['one'] > $this->coreCount * 1.5) {
            $alerts[] = ['level' => 'error', 'message' => 'Very high load average: '.$this->loadAverage['one']];
        } elseif ($this->loadAverage['one'] > $this->coreCount) {
            $alerts[] = ['level' => 'warning', 'message' => 'High load average: '.$this->loadAverage['one']];
        }

        if ($this->swap['used_mb'] > 512) {
            $alerts[] = ['level' => 'warning', 'message' => 'Swap in use: '.number_format($this->swap['used_mb']).' MB'];
        }

        foreach (collect($this->siteStatuses)->where('status', 'down') as $site) {
            $recentChecks = SiteCheck::where('url', $site['url'])
                ->orderBy('checked_at', 'desc')
                ->limit(2)
                ->get();

            if ($recentChecks->count() >= 2 && $recentChecks->every(fn ($c) => $c->status === 'down')) {
                $alerts[] = ['level' => 'error', 'message' => $site['site_name'].' is down'];
            }
        }

        foreach (collect($this->services)->where('running', false)->where('restarting', false) as $service) {
            $alerts[] = ['level' => 'error', 'message' => $service['label'].' is stopped'];
        }

        return $alerts;
    }
}
