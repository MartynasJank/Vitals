<?php

namespace App\Console\Commands;

use App\Models\ThreatIp;
use App\Services\ThreatIntelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('threat-intel:backfill-privacy')]
#[Description('Re-enrich all threat IPs with accurate VPN/proxy/Tor flags from ipinfo.io')]
class BackfillPrivacyFlags extends Command
{
    public function handle(ThreatIntelService $threatIntel): void
    {
        if (! config('services.ipinfo.token')) {
            $this->error('IPINFO_TOKEN is not set. Add it to .env and try again.');

            return;
        }

        $total = ThreatIp::count();

        if ($total === 0) {
            $this->info('No threat IPs found.');

            return;
        }

        $this->info("Re-enriching {$total} IPs — throttled to ~60 req/min to stay well within ipinfo.io limits.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        ThreatIp::chunkById(50, function ($chunk) use (&$updated, $bar, $threatIntel) {
            foreach ($chunk as $record) {
                $privacy = $threatIntel->fetchPrivacyData($record->ip);

                if (! empty($privacy)) {
                    $record->update([
                        'is_vpn' => (bool) ($privacy['vpn'] ?? false),
                        'is_proxy' => (bool) ($privacy['proxy'] ?? false),
                        'is_tor' => (bool) ($privacy['tor'] ?? false),
                    ]);
                    $updated++;
                }

                $bar->advance();
                usleep(1_000_000); // 1 req/sec
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Updated {$updated} of {$total} IPs.");
    }
}
