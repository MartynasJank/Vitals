<?php

use App\Models\ResourceSnapshot;
use Illuminate\Support\Facades\Schedule;

Schedule::command('vitals:snapshot')->everyFiveMinutes();

Schedule::call(fn () => ResourceSnapshot::where('recorded_at', '<', now()->subDays(7))->delete())
    ->daily()
    ->name('cleanup:snapshots');
