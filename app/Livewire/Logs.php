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

    /** @var array<int, array{raw: string, level: string}> */
    public array $lines = [];

    /** @var array<string, array{label: string, path: string}> */
    public array $sources = [];

    public function mount(): void
    {
        $this->sources = app(LogService::class)->getSources();
        $this->activeSource = array_key_first($this->sources) ?? '';
        $this->loadLines();
    }

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

    public function togglePolling(): void
    {
        $this->polling = ! $this->polling;
    }

    public function download(): StreamedResponse
    {
        $sources = app(LogService::class)->getSources();
        $path = $sources[$this->activeSource]['path'] ?? null;

        if (! $path) {
            return response()->streamDownload(fn () => print (''), 'empty.log');
        }

        $filename = $this->activeSource.'.log';
        $filePath = $path;

        return response()->streamDownload(function () use ($filePath) {
            $content = file_get_contents($filePath);
            echo $content !== false ? $content : '';
        }, $filename);
    }

    public function render(): View
    {
        return view('livewire.logs');
    }
}
