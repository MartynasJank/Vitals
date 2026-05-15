<?php

namespace App\Livewire;

use App\Models\CowrieSession;
use App\Services\SecurityService;
use App\Services\SystemServiceManager;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Services extends Component
{
    /** @var array<string, array{label: string, running: bool, restarting: bool, uptime: string|null, memory: string|null, workers: int|null, ports: int[], journal: string[]}> */
    public array $services = [];

    /** @var array<int, array{schedule: string, command: string, raw: string}> */
    public array $cronJobs = [];

    public ?string $restartMessage = null;

    public int $failBanned = 0;

    public int $cowrieActiveSessions = 0;

    public string $newCronSchedule = '';

    public string $newCronCommand = '';

    public ?string $cronError = null;

    public function mount(): void
    {
        $this->loadServices();
        $this->loadCronJobs();
        $this->loadBadges();
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

    public function loadBadges(): void
    {
        $this->failBanned = count(app(SecurityService::class)->getBannedIps());
        $this->cowrieActiveSessions = CowrieSession::whereNull('ended_at')
            ->where('started_at', '>=', now()->subHours(2))
            ->count();
    }

    public function addCronJob(): void
    {
        $this->cronError = null;
        $schedule = trim($this->newCronSchedule);
        $command = trim($this->newCronCommand);

        if (! $schedule || ! $command) {
            $this->cronError = 'Schedule and command are required.';

            return;
        }

        if (! $this->isValidCronSchedule($schedule)) {
            $this->cronError = 'Invalid cron schedule.';

            return;
        }

        $line = $schedule.' '.$command;
        $escaped = escapeshellarg($line);
        $result = shell_exec("(sudo crontab -l 2>/dev/null; echo {$escaped}) | sudo crontab - 2>&1");

        if ($result !== null && trim($result) !== '') {
            $this->cronError = 'Failed to add cron job.';

            return;
        }

        $this->newCronSchedule = '';
        $this->newCronCommand = '';
        $this->loadCronJobs();
    }

    public function deleteCronJob(int $index): void
    {
        $raw = $this->cronJobs[$index]['raw'] ?? null;

        if (! $raw) {
            return;
        }

        $escaped = escapeshellarg($raw);
        shell_exec("sudo crontab -l 2>/dev/null | grep -vxF {$escaped} | sudo crontab - 2>&1");
        $this->loadCronJobs();
    }

    /** @return array{schedule: string, command: string, raw: string} */
    private function parseCronLine(string $line): array
    {
        $parts = preg_split('/\s+/', trim($line), 6);

        if (count($parts) < 6) {
            return ['schedule' => $line, 'command' => '', 'raw' => $line];
        }

        [$min, $hour, $day, $month, $dow, $rawCommand] = $parts;

        return [
            'schedule' => $this->parseCronSchedule($min, $hour, $day, $month, $dow),
            'command' => $this->cleanCronCommand($rawCommand),
            'raw' => $line,
        ];
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
        $command = trim((string) preg_replace('/\s*>>?\s*\/dev\/null.*$/i', '', $command));
        $command = (string) preg_replace('#\S*php\S*\s+\S*/artisan\s+#', 'artisan ', $command);

        return $command;
    }

    private function isValidCronSchedule(string $schedule): bool
    {
        $parts = preg_split('/\s+/', $schedule);

        if (count($parts) !== 5) {
            return false;
        }

        return (bool) preg_match('/^[\d\*\/,\-]+$/', implode('', $parts));
    }

    public function restart(string $service): void
    {
        $success = app(SystemServiceManager::class)->restart($service);
        $this->restartMessage = $success ? "Restarted {$service}." : "Failed to restart {$service}.";
        sleep(3);
        $this->loadServices();
    }

    public function render(): View
    {
        return view('livewire.services');
    }
}
