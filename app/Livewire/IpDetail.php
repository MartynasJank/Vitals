<?php

namespace App\Livewire;

use App\Models\MalwareFile;
use App\Models\ThreatIp;
use App\Services\ThreatIntelService;
use Illuminate\View\View;
use Livewire\Component;

class IpDetail extends Component
{
    public string $ip = '';

    public ?ThreatIp $profile = null;

    public int $sshCount = 0;

    public int $nginxCount = 0;

    public int $cowrieCount = 0;

    public int $malwareCount = 0;

    /** @var array<int, MalwareFile> */
    public array $malwareFiles = [];

    /** @var array<int, array{username: string, timestamp: string}> */
    public array $sshAttempts = [];

    /** @var array<int, array{path: string, method: string, status_code: int|null, scan_type: string|null, user_agent: string|null, timestamp: string}> */
    public array $nginxHits = [];

    /** @var array<int, array{started_at: string, duration_seconds: float|null, is_interesting: bool, username: string|null, password: string|null, is_success: bool, commands: array, downloads: array}> */
    public array $cowrieSessions = [];

    /** @var array<int, array{username: string, count: int}> */
    public array $topSshUsernames = [];

    /** @var array<int, array{scan_type: string, count: int}> */
    public array $nginxScanTypes = [];

    /** @var array<int, array{path: string, scan_type: string|null, count: int}> */
    public array $topNginxPaths = [];

    /** @var array<int, int> */
    public array $activityByHour = [];

    public function mount(string $ip): void
    {
        $this->ip = $ip;

        $data = app(ThreatIntelService::class)->getIpProfile($ip);

        if ($data) {
            $this->profile = $data['profile'];
            $this->sshCount = $data['ssh_count'];
            $this->nginxCount = $data['nginx_count'];
            $this->cowrieCount = $data['cowrie_count'];
            $this->malwareCount = $data['malware_count'];
            $this->malwareFiles = $data['malware_files'];
            $this->sshAttempts = $data['ssh_attempts'];
            $this->nginxHits = $data['nginx_hits'];
            $this->cowrieSessions = $data['cowrie_sessions'];
            $this->topSshUsernames = $data['top_ssh_usernames'];
            $this->nginxScanTypes = $data['nginx_scan_types'];
            $this->topNginxPaths = $data['top_nginx_paths'];
            $this->activityByHour = $data['activity_by_hour'];
        }
    }

    public function render(): View
    {
        return view('livewire.ip-detail');
    }
}
