<?php

namespace App\Livewire;

use App\Services\DatabaseService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Databases extends Component
{
    /** @var array<int, array{name: string, size_mb: float, table_count: int, rows: int, tables: array<int, array{name: string, engine: string, rows: int, size_mb: float}>}> */
    public array $databases = [];

    /** @var array{version: string, uptime: string, connections: int, max_connections: int, threads_running: int, slow_queries: int, buffer_hit_rate: string, queries: int} */
    public array $serverStats = [];

    /** @var array<int, array{id: int, user: string, db: string|null, command: string, time: int, state: string|null, info: string|null}> */
    public array $processList = [];

    /** @var array<int, array{query: string, schema: string|null, count: int, avg_ms: float, max_ms: float, total_ms: float}> */
    public array $slowQueries = [];

    /** @var array{active_transactions: int, lock_waits: int, history_list_length: int|null, last_deadlock: string|null} */
    public array $innodbStatus = [];

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
        $this->processList = $service->getProcessList();
        $this->slowQueries = $service->getSlowQueries();
        $this->innodbStatus = $service->getInnoDbStatus();
    }

    public function render(): View
    {
        return view('livewire.databases');
    }
}
