<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('malware:recent-downloads {--limit=20 : Number of rows to show}')]
#[Description('Show most recent source IP assignments with file info')]
class ShowRecentDownloads extends Command
{
    public function handle(): void
    {
        $limit = (int) $this->option('limit');

        $rows = DB::table('cowrie_downloads as cd')
            ->join('cowrie_sessions as cs', 'cs.id', '=', 'cd.cowrie_session_id')
            ->join('threat_ips as ti', 'ti.id', '=', 'cs.ip_id')
            ->join('malware_files as mf', 'mf.sha256', '=', 'cd.file_hash')
            ->joinSub(
                DB::table('cowrie_downloads as cd2')
                    ->join('cowrie_sessions as cs2', 'cs2.id', '=', 'cd2.cowrie_session_id')
                    ->select('cd2.file_hash', DB::raw('COUNT(DISTINCT cs2.ip_id) as ip_count'))
                    ->groupBy('cd2.file_hash'),
                'counts',
                'counts.file_hash',
                '=',
                'cd.file_hash'
            )
            ->select(
                'cd.timestamp as assigned_at',
                'ti.ip',
                'ti.country',
                DB::raw('LEFT(cd.file_hash, 16) as sha256_short'),
                'mf.first_seen_at',
                'mf.malware_family',
                'counts.ip_count'
            )
            ->orderByDesc('cd.timestamp')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No records found.');

            return;
        }

        $this->table(
            ['Assigned at', 'IP', 'Country', 'SHA256 (short)', 'First seen', 'Family', 'Source IPs'],
            $rows->map(fn ($r) => [
                $r->assigned_at,
                $r->ip,
                $r->country ?? '—',
                $r->sha256_short,
                $r->first_seen_at,
                $r->malware_family ?? '—',
                $r->ip_count,
            ])
        );
    }
}
