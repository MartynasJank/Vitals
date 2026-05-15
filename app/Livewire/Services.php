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

    /** @var array<int, array{schedule: string, command: string}> */
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
        $this->restartMessage = null;
        $this->services = app(SystemServiceManager::class)->getAll();
    }

    public function loadCronJobs(): void
    {
        $output = shell_exec('sudo crontab -l 2>/dev/null');

        if (! $output) {
            $this->cronJobs = [];

            return;
        }

        $this->cronJobs = collect(explode("\n", trim($output)))
            ->filter(fn ($line) => ! empty($line) && ! str_starts_with($line, '#'))
            ->map(fn ($line) => $this->parseCronLine($line))
            ->values()
            ->all();
    }

    /** @return array{schedule: string, command: string} */
    private function parseCronLine(string $line): array
    {
        $parts = preg_split('/\s+/', trim($line), 6);

        if (count($parts) < 6) {
            return ['schedule' => $line, 'command' => ''];
        }

        [$min, $hour, $day, $month, $dow, $rawCommand] = $parts;

        $schedule = $this->parseCronSchedule($min, $hour, $day, $month, $dow);
        $command = $this->cleanCronCommand($rawCommand);

        return ['schedule' => $schedule, 'command' => $command];
    }

    private function parseCronSchedule(string $min, string $hour, string $day, string $month, string $dow): string
    {
        if ($min === '*' && $hour === '*' && $day === '*' && $month === '*' && $dow === '*') {
            return 'every minute';
        }

        if (str_starts_with($min, '*/') && $hour === '*' && $day === '*' && $month === '*' && $dow === '*') {
            return 'every '.substr($min, 2).' min';
        }

        if ($min === '0' && str_starts_with($hour, '*/') && $day === '*' && $month === '*' && $dow === '*') {
            return 'every '.substr($hour, 2).' hours';
        }

        if ($min === '0' && $hour === '*' && $day === '*' && $month === '*' && $dow === '*') {
            return 'every hour';
        }

        if ($min === '0' && $hour === '0' && $day === '*' && $month === '*' && $dow === '*') {
            return 'daily at midnight';
        }

        return "{$min} {$hour} {$day} {$month} {$dow}";
    }

    private function cleanCronCommand(string $command): string
    {
        // Strip output redirection
        $command = trim((string) preg_replace('/\s*>>?\s*\/dev\/null.*$/i', '', $command));

        // Shorten php + artisan path to just "artisan <cmd>"
        $command = (string) preg_replace('#\S*php\S*\s+\S*/artisan\s+#', 'artisan ', $command);

        return $command;
    }

    public function restart(string $service): void
    {
        $success = app(SystemServiceManager::class)->restart($service);
        $this->restartMessage = $success ? "Restarted {$service}." : "Failed to restart {$service}.";
        // Give the service time to come back up before polling status (important for nginx,
        // which would otherwise drop the connection mid-response).
        sleep(3);
        $this->loadServices();
    }

    public function render(): View
    {
        return view('livewire.services');
    }
}
