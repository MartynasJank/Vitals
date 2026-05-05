<?php

namespace App\Console\Commands;

use App\Models\NginxHit;
use App\Models\SshAttempt;
use App\Services\ThreatIntelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vitals:enrich-ip {ip} {source=ssh} {--username= : SSH username to record}')]
#[Description('Enrich an IP address in the threat intel database')]
class EnrichIp extends Command
{
    public function handle(ThreatIntelService $threatIntel): void
    {
        $ip = $this->argument('ip');
        $source = $this->argument('source');

        $ipId = $threatIntel->upsertIpWithEnrichment($ip);

        if ($source === 'ssh') {
            SshAttempt::create([
                'ip_id' => $ipId,
                'username' => $this->option('username'),
                'timestamp' => now(),
            ]);
        } elseif ($source === 'nginx') {
            NginxHit::create([
                'ip_id' => $ipId,
                'timestamp' => now(),
            ]);
        }

        $this->info("Enriched {$ip} (source: {$source}, ip_id: {$ipId}).");
    }
}
