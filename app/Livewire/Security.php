<?php

namespace App\Livewire;

use App\Services\SecurityService;
use Illuminate\View\View;
use Livewire\Attributes\Poll;
use Livewire\Component;

class Security extends Component
{
    /** @var array<int, array{ip: string, jail: string}> */
    public array $bannedIps = [];

    /** @var array<int, array{time: string, user: string, ip: string}> */
    public array $failedLogins = [];

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
        $this->failedLogins = $service->getFailedLogins();
        $this->successfulLogins = $service->getSuccessfulLogins();
        $this->firewallRules = $service->getFirewallRules();
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
