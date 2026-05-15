<?php

namespace App\Livewire;

use App\Services\LogService;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Logs extends Component
{
    public string $activeSource = '';

    public string $search = '';

    public bool $polling = true;

    public bool $wordWrap = true;

    public string $levelFilter = 'all';

    public int $lineCount = 100;

    /** @var array<int, array{raw: string, level: string, type: string}> */
    public array $lines = [];

    /** @var array<string, array{label: string, path: string, size: string, modified: string}> */
    public array $sources = [];

    public function mount(): void
    {
        $this->sources = app(LogService::class)->getSources();
        $this->activeSource = array_key_first($this->sources) ?? '';
        $this->loadLines();
    }

    public function loadLines(): void
    {
        $all = app(LogService::class)->getLines($this->activeSource, $this->search, $this->lineCount);

        $this->lines = $this->levelFilter === 'all'
            ? $all
            : array_values(array_filter($all, fn ($l) => $l['level'] === $this->levelFilter));
    }

    public function switchSource(string $source): void
    {
        $this->activeSource = $source;
        $this->search = '';
        $this->levelFilter = 'all';
        $this->loadLines();
    }

    public function updatedSearch(): void
    {
        $this->loadLines();
    }

    public function updatedLineCount(): void
    {
        $this->loadLines();
    }

    public function updatedLevelFilter(): void
    {
        $this->loadLines();
    }

    public function togglePolling(): void
    {
        $this->polling = ! $this->polling;
    }

    public function toggleWrap(): void
    {
        $this->wordWrap = ! $this->wordWrap;
    }

    public function download(): StreamedResponse
    {
        $sources = app(LogService::class)->getSources();
        $path = $sources[$this->activeSource]['path'] ?? null;

        if (! $path) {
            return response()->streamDownload(fn () => print (''), 'empty.log');
        }

        return response()->streamDownload(function () use ($path) {
            $content = file_get_contents($path);
            echo $content !== false ? $content : '';
        }, $this->activeSource.'.log');
    }

    public function render(): View
    {
        return view('livewire.logs');
    }
}
