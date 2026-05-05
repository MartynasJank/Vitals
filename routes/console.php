<?php

use App\Models\ResourceSnapshot;
use App\Models\SiteCheck;
use Illuminate\Support\Facades\Schedule;

Schedule::command('vitals:snapshot')->everyFiveMinutes();
Schedule::command('vitals:check-sites')->everyFiveMinutes();
Schedule::command('vitals:parse-nginx-logs')->everyFiveMinutes();

Schedule::call(fn () => ResourceSnapshot::where('recorded_at', '<', now()->subDays(7))->delete())
    ->daily()
    ->name('cleanup:snapshots');

Schedule::call(fn () => SiteCheck::where('checked_at', '<', now()->subDays(30))->delete())
    ->daily()
    ->name('cleanup:site-checks');
