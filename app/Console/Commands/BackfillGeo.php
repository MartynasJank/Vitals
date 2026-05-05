<?php

namespace App\Console\Commands;

use App\Models\ThreatIp;
use App\Services\ThreatIntelService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vitals:backfill-geo')]
#[Description('Backfill lat/lon/org for existing threat IPs that were enriched before these columns existed')]
class BackfillGeo extends Command
{
    public function handle(ThreatIntelService $threatIntel): void
    {
        $missing = ThreatIp::whereNull('lat')->count();

        if ($missing === 0) {
            $this->info('All IPs already have geo coordinates.');

            return;
        }

        $this->info("Backfilling {$missing} IPs — this may take a few minutes (rate-limited to ~40 req/min).");

        $bar = $this->output->createProgressBar($missing);
        $bar->start();

        $updated = 0;

        ThreatIp::whereNull('lat')->chunkById(50, function ($chunk) use (&$updated, $bar, $threatIntel) {
            foreach ($chunk as $ip) {
                $geo = $threatIntel->fetchGeoForBackfill($ip->ip);

                if (isset($geo['lat'])) {
                    $ip->update([
                        'lat' => (float) $geo['lat'],
                        'lon' => (float) $geo['lon'],
                        'org' => $geo['org'] ?? null,
                    ]);
                    $updated++;
                }

                $bar->advance();
                usleep(1_500_000); // 40 req/min — under ip-api.com free limit of 45/min
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Updated {$updated} of {$missing} IPs.");
    }
}
