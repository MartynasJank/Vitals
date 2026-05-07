<?php

namespace App\Console\Commands;

use App\Models\CowrieCommand;
use App\Models\CowrieDownload;
use App\Models\CowrieLogin;
use App\Models\CowrieSession;
use App\Models\Credential;
use App\Models\NginxHit;
use App\Models\SshAttempt;
use App\Models\ThreatIp;
use Illuminate\Console\Command;

class ResetThreatData extends Command
{
    protected $signature = 'vitals:reset-threat-data {--force : Skip confirmation prompt}';

    protected $description = 'Truncate all threat intel tables and reset log parse cursors';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete ALL threat intel data. Continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->truncateTables();
        $this->resetStateFiles();

        $this->info('Threat data reset complete.');

        return self::SUCCESS;
    }

    private function truncateTables(): void
    {
        $models = [
            CowrieCommand::class,
            CowrieDownload::class,
            CowrieLogin::class,
            CowrieSession::class,
            Credential::class,
            NginxHit::class,
            SshAttempt::class,
            ThreatIp::class,
        ];

        foreach ($models as $model) {
            $model::truncate();
            $this->line("  Cleared: {$model}");
        }
    }

    private function resetStateFiles(): void
    {
        $stateFiles = [
            storage_path('app/nginx_parse_state.json'),
            storage_path('app/cowrie_parse_state.json'),
        ];

        foreach ($stateFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
                $this->line("  Deleted: {$path}");
            }
        }
    }
}
