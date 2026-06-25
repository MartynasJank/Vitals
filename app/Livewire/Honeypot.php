<?php

namespace App\Livewire;

use App\Services\SecurityService;
use App\Services\ThreatIntelService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Honeypot extends Component
{
    /** @var array{total_sessions: int, unique_ips: int, total_commands: int, total_downloads: int, interesting_sessions: int} */
    public array $stats = ['total_sessions' => 0, 'unique_ips' => 0, 'total_commands' => 0, 'total_downloads' => 0, 'interesting_sessions' => 0];

    /** @var array<int, array{session: string, ip: string|null, country: string|null, country_code: string|null, isp: string|null, username: string|null, password: string|null, duration_seconds: float|null, started_at: string|null, commands: array<int, string>}> */
    public array $recentSessions = [];

    /** @var array<int, array{username: string, password: string, hit_count: int}> */
    public array $topCredentials = [];

    /** @var array<int, array{input: string, count: int}> */
    public array $topCommands = [];

    /** @var array<int, array{url: string, filename: string|null, file_hash: string|null, count: int}> */
    public array $topDownloads = [];

    /** @var array<int, array{session: string, ip: string|null, country: string|null, country_code: string|null, isp: string|null, username: string|null, password: string|null, duration_seconds: float|null, started_at: string|null, tags: array<int, string>, commands: array<int, array{input: string, class: string}>}> */
    public array $interestingSessions = [];

    /** @var array<int, array{username: string, count: int}> */
    public array $topUsernames = [];

    /** @var array<int, array{password: string, count: int}> */
    public array $topPasswords = [];

    public string $countryFilter = '';

    /** @var array<int, array{country: string, country_code: string}> */
    public array $availableCountries = [];

    public bool $loginsOnly = false;

    public ?string $banMessage = null;

    public function mount(): void
    {
        $this->availableCountries = app(ThreatIntelService::class)->getDistinctCountries();
        $this->loadData();
    }

    #[Poll('60s')]
    public function loadData(): void
    {
        try {
            $service = app(ThreatIntelService::class);
            $country = $this->countryFilter ?: null;
            $this->stats = $service->getCowrieStats($country);
            $sessions = $service->getRecentCowrieSessions(limit: $this->loginsOnly ? 100 : 20, countryCode: $country);
            if ($this->loginsOnly) {
                $sessions = array_values(array_filter($sessions, fn ($s) => ! empty($s['username'])));
                $sessions = array_slice($sessions, 0, 20);
            }
            $this->recentSessions = $sessions;
            $this->topCredentials = $service->getTopCredentials(countryCode: $country);
            $this->topCommands = $service->getTopCowrieCommands(countryCode: $country);
            $this->topDownloads = $service->getTopCowrieDownloads(countryCode: $country);
            $this->interestingSessions = $service->getInterestingSessions(countryCode: $country);
            $this->topUsernames = $service->getTopUsernames(countryCode: $country);
            $this->topPasswords = $service->getTopPasswords(countryCode: $country);
        } catch (\Exception) {
        }
    }

    public function updatedCountryFilter(): void
    {
        $this->loadData();
    }

    public function toggleLoginsOnly(): void
    {
        $this->loginsOnly = ! $this->loginsOnly;
        $this->loadData();
    }

    public function ban(string $ip): void
    {
        $success = app(SecurityService::class)->banIp($ip);
        $this->banMessage = $success ? "Banned {$ip}." : "Failed to ban {$ip}.";
    }

    public function render(): View
    {
        return view('livewire.honeypot');
    }
}
