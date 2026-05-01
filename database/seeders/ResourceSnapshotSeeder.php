<?php

namespace Database\Seeders;

use App\Models\ResourceSnapshot;
use Illuminate\Database\Seeder;

class ResourceSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        ResourceSnapshot::truncate();

        $now = now();

        for ($i = 59; $i >= 0; $i--) {
            ResourceSnapshot::create([
                'cpu_percent' => fake()->randomFloat(1, 2, 45),
                'ram_used_mb' => fake()->numberBetween(2800, 4200),
                'ram_total_mb' => 8192,
                'disk_used_gb' => fake()->randomFloat(1, 18, 22),
                'disk_total_gb' => 160,
                'recorded_at' => $now->copy()->subMinutes($i * 5),
            ]);
        }
    }
}