<?php

namespace App\Livewire;

use App\Services\LogService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Logs extends Component
{
    public string $activeSource = 'nginx_error';

    public string $search = '';

    /** @var array<int, array{raw: string, level: string}> */
    public array $lines = [];

    /** @var array<string, array{label: string, path: string}> */
    public array $sources = [];

    public function mount(): void
    {
        $this->sources = app(LogService::class)->getSources();
        $this->loadLines();
    }

    #[Poll('10s')]
    public function loadLines(): void
    {
        $this->lines = app(LogService::class)->getLines($this->activeSource, $this->search);
    }

    public function switchSource(string $source): void
    {
        $this->activeSource = $source;
        $this->search = '';
        $this->loadLines();
    }

    public function updatedSearch(): void
    {
        $this->loadLines();
    }

    public function render(): View
    {
        return view('livewire.logs');
    }
}
