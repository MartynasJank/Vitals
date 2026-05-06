<?php

namespace App\Livewire;

use App\Services\SecurityService;
use App\Services\ThreatIntelService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Security extends Component
{
    /** @var array<int, array{ip: string, jail: string}> */
    public array $bannedIps = [];

    /** @var array<int, array{time: string, user: string, ip: string, password: string|null, source: string, is_success: bool, country: string|null, country_code: string|null, isp: string|null, asn: string|null, is_proxy: bool, total_hits: int}> */
    public array $sshAttempts = [];

    /** @var array<int, array{time: string, user: string, ip: string}> */
    public array $successfulLogins = [];

    /** @var array<int, array{time: string, ip: string, country: string|null, country_code: string|null, method: string, path: string, status_code: int, scan_type: string}> */
    public array $recentBotScans = [];

    /** @var array<int, string> */
    public array $firewallRules = [];

    public ?string $unbanMessage = null;

    public function mount(): void
    {
        $this->loadData();
    }

    #[Poll('30s')]
    public function loadData(): void
    {
        $service = app(SecurityService::class);

        $this->bannedIps = $service->getBannedIps();
        $this->successfulLogins = $service->getSuccessfulLogins();
        $this->firewallRules = $service->getFirewallRules();

        try {
            $threatIntel = app(ThreatIntelService::class);

            $cowrie = array_map(fn ($e) => array_merge($e, ['source' => 'cowrie']), $threatIntel->getRecentHoneypotLogins());

            $sshd = array_map(fn ($e) => array_merge($e, [
                'password' => null,
                'source' => 'sshd',
                'is_success' => false,
            ]), $threatIntel->getRecentSshdAttempts());

            $merged = array_merge($cowrie, $sshd);
            usort($merged, fn ($a, $b) => strcmp($b['time'], $a['time']));
            $this->sshAttempts = array_slice($merged, 0, 40);
        } catch (\Exception) {
            $this->sshAttempts = array_map(fn ($e) => array_merge($e, [
                'password' => null, 'source' => 'sshd', 'is_success' => false,
                'country' => null, 'country_code' => null, 'isp' => null,
                'asn' => null, 'is_proxy' => false, 'total_hits' => 1,
            ]), $service->getFailedLogins());
        }

        try {
            $this->recentBotScans = app(ThreatIntelService::class)->getRecentNginxHits();
        } catch (\Exception) {
            $this->recentBotScans = [];
        }
    }

    public function unban(string $ip, string $jail): void
    {
        $success = app(SecurityService::class)->unbanIp($ip, $jail);
        $this->unbanMessage = $success ? "Unbanned {$ip}." : "Failed to unban {$ip}.";
        $this->loadData();
    }

    public function render(): View
    {
        return view('livewire.security');
    }
}
