<?php

namespace App\Console\Commands;

use App\Models\MalwareFile;
use App\Services\VirusTotalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('malware:fetch-vt')]
#[Description('Fetch VirusTotal analysis for pending malware files (hash lookup only)')]
class FetchVirusTotalData extends Command
{
    public function handle(VirusTotalService $vt): void
    {
        $files = MalwareFile::where(function ($q) {
            $q->where('vt_status', 'pending')
                ->orWhere(function ($q) {
                    $q->where('vt_status', 'found')
                        ->where('vt_last_checked_at', '<', now()->subHours(24));
                });
        })
            ->limit(10)
            ->get();

        if ($files->isEmpty()) {
            $this->info('No files to check.');

            return;
        }

        foreach ($files as $index => $file) {
            $this->line("Checking {$file->sha256}...");
            $vt->lookup($file);

            // Free tier: 4 requests/minute — sleep between each except the last
            if ($index < $files->count() - 1) {
                sleep(15);
            }
        }

        $this->info("Checked {$files->count()} files.");
    }
}
