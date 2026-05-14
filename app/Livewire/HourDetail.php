<?php

namespace App\Livewire;

use App\Services\ThreatIntelService;
use Illuminate\View\View;
use Livewire\Component;

class HourDetail extends Component
{
    public string $date = '';

    public int $hour = 0;

    public string $label = '';

    public int $sshCount = 0;

    public int $nginxCount = 0;

    public int $cowrieCount = 0;

    public int $uniqueIps = 0;

    /** @var array<int, array{timestamp: string|null, ip: string, country: string|null, country_code: string|null, username: string|null, isp: string|null}> */
    public array $sshAttempts = [];

    /** @var array<int, array{timestamp: string|null, ip: string, country: string|null, country_code: string|null, method: string|null, path: string|null, status_code: int|null, scan_type: string|null}> */
    public array $nginxHits = [];

    /** @var array<int, array{session: string, ip: string|null, country: string|null, country_code: string|null, isp: string|null, username: string|null, password: string|null, duration_seconds: float|null, started_at: string|null, commands: array<int, string>}> */
    public array $cowrieSessions = [];

    public function mount(string $date, int $hour): void
    {
        $this->date = $date;
        $this->hour = $hour;

        $data = app(ThreatIntelService::class)->getHourlyBreakdown($date, $hour);

        $this->label = $data['label'];
        $this->sshCount = $data['ssh_count'];
        $this->nginxCount = $data['nginx_count'];
        $this->cowrieCount = $data['cowrie_count'];
        $this->uniqueIps = $data['unique_ips'];
        $this->sshAttempts = $data['ssh_attempts'];
        $this->nginxHits = $data['nginx_hits'];
        $this->cowrieSessions = $data['cowrie_sessions'];
    }

    public function render(): View
    {
        return view('livewire.hour-detail');
    }
}
