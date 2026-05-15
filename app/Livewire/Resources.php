<?php

namespace App\Livewire;

use App\Models\ResourceSnapshot;
use App\Services\ServerService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Resources extends Component
{
    public float $cpuPercent = 0.0;

    /** @var array{one: float, five: float, fifteen: float} */
    public array $loadAverage = ['one' => 0.0, 'five' => 0.0, 'fifteen' => 0.0];

    public int $coreCount = 1;

    /** @var array{used_mb: int, total_mb: int, free_mb: int, cached_mb: int} */
    public array $ram = ['used_mb' => 0, 'total_mb' => 0, 'free_mb' => 0, 'cached_mb' => 0];

    /** @var array{used_mb: int, total_mb: int} */
    public array $swap = ['used_mb' => 0, 'total_mb' => 0];

    /** @var array{used_gb: float, total_gb: float, percent: float} */
    public array $disk = ['used_gb' => 0.0, 'total_gb' => 0.0, 'percent' => 0.0];

    /** @var array{interface: string, rx_rate_kbps: float, tx_rate_kbps: float, rx_total_gb: float, tx_total_gb: float} */
    public array $network = ['interface' => '', 'rx_rate_kbps' => 0.0, 'tx_rate_kbps' => 0.0, 'rx_total_gb' => 0.0, 'tx_total_gb' => 0.0];

    /** @var array{device: string, read_kbps: float, write_kbps: float} */
    public array $diskIo = ['device' => '', 'read_kbps' => 0.0, 'write_kbps' => 0.0];

    /** @var array{total: int, established: int, time_wait: int} */
    public array $tcpStats = ['total' => 0, 'established' => 0, 'time_wait' => 0];

    public string $uptime = '';

    /** @var array<int, array{device: string, mount: string, total_gb: int, used_gb: int, avail_gb: int, percent: int}> */
    public array $diskPartitions = [];

    /** @var array<int, array{pid: int, user: string, cpu: float, memory: float, command: string}> */
    public array $processes = [];

    public string $range = '1h';

    public string $processSort = 'cpu';

    public ?string $killMessage = null;

    public function mount(): void
    {
        $this->loadStats();
    }

    #[Poll('5s')]
    public function loadStats(): void
    {
        $server = app(ServerService::class);

        $this->cpuPercent = $server->getCpuPercent();
        $this->loadAverage = $server->getLoadAverage();
        $this->coreCount = $server->getCoreCount();
        $this->uptime = $server->getServerUptime();
        $this->ram = $server->getRamStats();
        $this->swap = $server->getSwapStats();
        $this->disk = $server->getDiskStats();
        $this->diskIo = $server->getDiskIoStats();
        $this->network = $server->getNetworkStats();
        $this->tcpStats = $server->getTcpStats();
        $this->diskPartitions = $server->getAllDiskPartitions();
        $this->processes = $server->getTopProcesses($this->processSort);
    }

    public function setRange(string $range): void
    {
        $this->range = $range;
    }

    public function setProcessSort(string $sort): void
    {
        $this->processSort = $sort;
        $this->loadStats();
    }

    public function kill(int $pid): void
    {
        $success = app(ServerService::class)->killProcess($pid);
        $this->killMessage = $success ? "Sent SIGTERM to PID {$pid}." : "Failed to kill PID {$pid}.";
        $this->loadStats();
    }

    public function render(): View
    {
        $limits = ['1h' => 12, '24h' => 288, '7d' => 2016];
        $limit = $limits[$this->range] ?? 12;

        $snapshots = ResourceSnapshot::orderBy('recorded_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $labelFormat = $this->range === '7d' ? 'd M H:i' : 'H:i';

        return view('livewire.resources', [
            'cpuHistory' => $snapshots->pluck('cpu_percent'),
            'ramHistory' => $snapshots->map(fn ($s) => $s->ram_total_mb > 0
                ? round($s->ram_used_mb / $s->ram_total_mb * 100, 1)
                : 0
            ),
            'netRxHistory' => $snapshots->map(fn ($s) => round($s->rx_rate_kbps ?? 0, 1)),
            'netTxHistory' => $snapshots->map(fn ($s) => round($s->tx_rate_kbps ?? 0, 1)),
            'labels' => $snapshots->map(fn ($s) => $s->recorded_at->format($labelFormat)),
        ]);
    }
}
