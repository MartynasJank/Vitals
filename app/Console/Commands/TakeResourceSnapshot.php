<?php

namespace App\Console\Commands;

use App\Models\ResourceSnapshot;
use App\Services\ServerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vitals:snapshot')]
#[Description('Take a resource snapshot and store it in the database')]
class TakeResourceSnapshot extends Command
{
    public function handle(ServerService $server): void
    {
        $ram = $server->getRamStats();
        $disk = $server->getDiskStats();

        ResourceSnapshot::create([
            'cpu_percent' => $server->getCpuPercent(),
            'ram_used_mb' => $ram['used_mb'],
            'ram_total_mb' => $ram['total_mb'],
            'disk_used_gb' => $disk['used_gb'],
            'disk_total_gb' => $disk['total_gb'],
            'recorded_at' => now(),
        ]);

        $this->info('Snapshot taken.');
    }
}
