<?php

namespace App\Livewire;

use App\Services\DatabaseService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Databases extends Component
{
    /** @var array<int, array{name: string, size_mb: float, tables: int, rows: int}> */
    public array $databases = [];

    /** @var array{version: string, uptime: string, connections: int, queries: int} */
    public array $serverStats = [];

    public function mount(): void
    {
        $this->loadData();
    }

    #[Poll('30s')]
    public function loadData(): void
    {
        $service = app(DatabaseService::class);
        $this->databases = $service->getDatabases();
        $this->serverStats = $service->getServerStats();
    }

    public function render(): View
    {
        return view('livewire.databases');
    }
}
