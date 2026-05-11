<?php

namespace App\Services;

use App\Models\MalwareFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VirusTotalService
{
    private const API_BASE = 'https://www.virustotal.com/api/v3';

    public function lookup(MalwareFile $file): void
    {
        $key = config('services.virustotal.key');

        if (! $key) {
            return;
        }

        try {
            $response = Http::withHeader('x-apikey', $key)
                ->timeout(15)
                ->get(self::API_BASE."/files/{$file->sha256}");
        } catch (\Exception $e) {
            // Log only the message — Guzzle exceptions can include request headers
            // (and therefore the API key) in the full exception object.
            Log::error("VT lookup failed for {$file->sha256}", ['error' => $e->getMessage()]);
            $file->update(['vt_status' => 'error']);

            return;
        }

        if ($response->status() === 404) {
            $file->update(['vt_status' => 'not_found', 'vt_last_checked_at' => now()]);

            return;
        }

        if (! $response->successful()) {
            Log::warning("VT lookup non-200 for {$file->sha256}", ['status' => $response->status()]);
            $file->update(['vt_status' => 'error']);

            return;
        }

        $data = $response->json('data.attributes');

        if (! $data) {
            $file->update(['vt_status' => 'error']);

            return;
        }

        $stats = $data['last_analysis_stats'] ?? [];
        $classification = $data['popular_threat_classification'] ?? [];

        // Validate permalink before storing — reject anything that isn't a plain
        // HTTPS virustotal.com URL to prevent javascript: / data: URI injection.
        $permalink = $response->json('data.links.self');
        if ($permalink && ! preg_match('#^https://www\.virustotal\.com/#', $permalink)) {
            $permalink = null;
        }

        $file->update([
            'vt_status' => 'found',
            'vt_malicious' => $stats['malicious'] ?? 0,
            'vt_total' => array_sum($stats),
            'vt_threat_label' => $classification['suggested_threat_label'] ?? null,
            'vt_threat_categories' => array_column($classification['popular_threat_category'] ?? [], 'value'),
            'vt_family_labels' => array_column($classification['popular_threat_name'] ?? [], 'value'),
            'vt_last_checked_at' => now(),
            'vt_permalink' => $permalink,
        ]);
    }
}
