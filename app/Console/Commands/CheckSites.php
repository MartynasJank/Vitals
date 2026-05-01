<?php

namespace App\Console\Commands;

use App\Models\SiteCheck;
use App\Services\SiteService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vitals:check-sites')]
#[Description('Check all sites and record results in the database')]
class CheckSites extends Command
{
    public function handle(SiteService $service): void
    {
        foreach ($service->discoverSites() as $site) {
            $result = $service->checkSite($site['url']);

            SiteCheck::create([
                'site_name' => $site['name'],
                'url' => $site['url'],
                'status' => $result['status'],
                'response_ms' => $result['response_ms'],
                'status_code' => $result['status_code'],
                'checked_at' => now(),
            ]);

            $this->line("{$site['name']}: {$result['status']}");
        }

        $this->info('Site checks complete.');
    }
}
