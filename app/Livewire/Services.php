<?php

namespace App\Livewire;

use App\Models\CowrieSession;
use App\Services\DatabaseService;
use App\Services\SecurityService;
use App\Services\SystemServiceManager;
use Illuminate\Support\Facades\DB;
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

    /** @var array<int, array{pid: int, options: string}> */
    public array $queueWorkers = [];

    public int $failedJobs = 0;

    /** @var array<int, array{job: string, queue: string, failed_at: string, exception: string}> */
    public array $recentFailedJobs = [];

    public ?string $schedulerLastRun = null;

    public function mount(): void
    {
        $this->loadServices();
        $this->loadCronJobs();
    }

    #[Poll('10s')]
    public function loadServices(): void
    {
        $this->restartMessage = null;
        $manager = app(SystemServiceManager::class);
        $this->services = $manager->getAll();
        $this->queueWorkers = $manager->getQueueWorkers();
        $this->schedulerLastRun = $manager->getSchedulerLastRun();
        $this->loadFailedJobs();
        $this->loadServiceDetails();
    }

    private function loadFailedJobs(): void
    {
        try {
            $this->failedJobs = DB::table('failed_jobs')->count();
            $this->recentFailedJobs = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(3)
                ->get(['queue', 'payload', 'exception', 'failed_at'])
                ->map(function ($row) {
                    $payload = json_decode($row->payload, true);

                    return [
                        'job' => class_basename($payload['displayName'] ?? 'Unknown'),
                        'queue' => $row->queue,
                        'failed_at' => $row->failed_at,
                        'exception' => strtok($row->exception ?? '', "\n"),
                    ];
                })
                ->all();
        } catch (\Exception) {
            $this->failedJobs = 0;
            $this->recentFailedJobs = [];
        }
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

    private function loadServiceDetails(): void
    {
        // MySQL
        try {
            $stats = app(DatabaseService::class)->getServerStats();
            $this->services['mysql']['details'] = $stats;
        } catch (\Exception) {
            $this->services['mysql']['details'] = null;
        }

        // Fail2ban — reuse result for badge + details
        $banned = app(SecurityService::class)->getBannedIps();
        $this->failBanned = count($banned);
        $byJail = [];
        foreach ($banned as $entry) {
            $byJail[$entry['jail']][] = $entry['ip'];
        }
        $this->services['fail2ban']['details'] = $byJail;

        // Cowrie — reuse result for badge + details
        $this->cowrieActiveSessions = CowrieSession::whereNull('ended_at')
            ->where('started_at', '>=', now()->subHours(2))
            ->count();

        $this->services['cowrie']['details'] = CowrieSession::with('ip')
            ->orderByDesc('started_at')
            ->limit(5)
            ->get(['id', 'ip_id', 'started_at', 'ended_at'])
            ->map(fn ($s) => [
                'ip' => $s->ip?->ip,
                'country' => $s->ip?->country,
                'started_at' => $s->started_at?->diffForHumans(),
                'active' => $s->ended_at === null,
            ])
            ->all();
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
