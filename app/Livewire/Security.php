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

    /** @var array<int, array{time: string, user: string, ip: string, country?: string, country_code?: string, isp?: string, asn?: string, is_proxy?: bool, is_vpn?: bool, is_tor?: bool, total_hits?: int}> */
    public array $failedLogins = [];

    /** @var array<int, array{time: string, user: string, password: string, ip: string, country: string|null, country_code: string|null, isp: string|null, total_hits: int, is_proxy: bool, is_success: bool}> */
    public array $honeypotLogins = [];

    /** @var array<int, array{time: string, user: string, ip: string}> */
    public array $successfulLogins = [];

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
            $this->failedLogins = $threatIntel->getEnrichedFailedLogins();
            $this->honeypotLogins = $threatIntel->getRecentHoneypotLogins();
        } catch (\Exception) {
            $this->failedLogins = $service->getFailedLogins();
            $this->honeypotLogins = [];
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
