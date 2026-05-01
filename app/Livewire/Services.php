<?php

namespace App\Livewire;

use App\Services\SystemServiceManager;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Services extends Component
{
    /** @var array<string, array{label: string, running: bool, uptime: string|null, memory: string|null}> */
    public array $services = [];

    /** @var array<int, string> */
    public array $cronJobs = [];

    public ?string $restartMessage = null;

    public function mount(): void
    {
        $this->loadServices();
        $this->loadCronJobs();
    }

    #[Poll('10s')]
    public function loadServices(): void
    {
        $this->services = app(SystemServiceManager::class)->getAll();
    }

    public function loadCronJobs(): void
    {
        $output = shell_exec('sudo crontab -l 2>/dev/null');

        $this->cronJobs = $output
            ? collect(explode("\n", trim($output)))
                ->filter(fn ($line) => ! empty($line) && ! str_starts_with($line, '#'))
                ->values()
                ->all()
            : [];
    }

    public function restart(string $service): void
    {
        $success = app(SystemServiceManager::class)->restart($service);
        $this->restartMessage = $success ? "Restarted {$service}." : "Failed to restart {$service}.";
        $this->loadServices();
    }

    public function render(): View
    {
        return view('livewire.services');
    }
}
