<?php

namespace App\Livewire;

use App\Models\CowrieCommand;
use App\Models\CowrieDownload;
use App\Models\CowrieSession;
use App\Models\ResourceSnapshot;
use App\Models\SiteCheck;
use App\Services\ServerService;
use App\Services\SystemServiceManager;
use App\Services\ThreatIntelService;
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

    /** @var array{rx_rate_kbps: float, tx_rate_kbps: float} */
    public array $networkStats = ['rx_rate_kbps' => 0.0, 'tx_rate_kbps' => 0.0];

    /** @var array{total: int, established: int, time_wait: int} */
    public array $tcpStats = ['total' => 0, 'established' => 0, 'time_wait' => 0];

    /** @var array<int, array{site_name: string, url: string, status: string, response_ms: int|null}> */
    public array $siteStatuses = [];

    /** @var array<int, array{label: string, running: bool}> */
    public array $services = [];

    /** @var array<int, array{level: string, message: string}> */
    public array $alerts = [];

    public int $attacksLast24h = 0;

    public int $attacksLastHour = 0;

    public string $topIpsRange = '24h';

    /** @var array<int, array{ip: string, country: string|null, country_code: string|null, isp: string|null, ssh: int, nginx: int, total: int}> */
    public array $topIps = [];

    /** @var array{sessions_24h: int, commands_24h: int, downloads_24h: int} */
    public array $honeypotSummary = ['sessions_24h' => 0, 'commands_24h' => 0, 'downloads_24h' => 0];

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

        $network = $server->getNetworkStats();
        $this->networkStats = [
            'rx_rate_kbps' => $network['rx_rate_kbps'],
            'tx_rate_kbps' => $network['tx_rate_kbps'],
        ];

        $this->tcpStats = $server->getTcpStats();

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

        try {
            $threatIntel = app(ThreatIntelService::class);
            $this->attacksLast24h = $threatIntel->getTotalAttacksLast24h();
            $this->attacksLastHour = $threatIntel->getTotalAttacksLastHour();
        } catch (\Exception) {
            $this->attacksLast24h = 0;
            $this->attacksLastHour = 0;
        }

        try {
            $since = now()->subHours(24);
            $this->honeypotSummary = [
                'sessions_24h' => CowrieSession::where('started_at', '>=', $since)->count(),
                'commands_24h' => CowrieCommand::where('timestamp', '>=', $since)->count(),
                'downloads_24h' => CowrieDownload::where('timestamp', '>=', $since)->count(),
            ];
        } catch (\Exception) {
            // keep defaults
        }

        $this->dispatch('dashboard-refreshed');
    }

    public function mount(): void
    {
        $this->refresh();
        $this->loadTopIps();
    }

    public function setTopIpsRange(string $range): void
    {
        $this->topIpsRange = $range;
        $this->loadTopIps();
    }

    private function loadTopIps(): void
    {
        try {
            $this->topIps = app(ThreatIntelService::class)->getTopIpsByHits($this->topIpsRange);
        } catch (\Exception) {
            $this->topIps = [];
        }
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
            'diskHistory' => $snapshots->map(fn ($s) => $s->disk_total_gb > 0
                ? round($s->disk_used_gb / $s->disk_total_gb * 100, 1)
                : 0
            ),
            'netRxHistory' => $snapshots->pluck('rx_rate_kbps'),
            'netTxHistory' => $snapshots->pluck('tx_rate_kbps'),
            'labels' => $snapshots->map(fn ($s) => $s->recorded_at->format('H:i')),
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
