<?php

namespace App\Livewire;

use App\Services\ServerService;
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
    }

    public function mount(): void
    {
        $this->refresh();
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
