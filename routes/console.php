<?php

use App\Models\CowrieLogin;
use App\Models\NginxHit;
use App\Models\ResourceSnapshot;
use App\Models\SiteCheck;
use App\Models\SshAttempt;
use Illuminate\Support\Facades\Schedule;

Schedule::command('vitals:snapshot')->everyFiveMinutes();
Schedule::command('vitals:check-sites')->everyFiveMinutes();
Schedule::command('vitals:parse-nginx-logs')->everyFiveMinutes();
Schedule::command('vitals:parse-ssh-logs')->everyFiveMinutes();
Schedule::command('vitals:parse-cowrie-logs')->everyMinute();

Schedule::call(fn () => SshAttempt::where('timestamp', '<', now()->subDays(90))->delete())
    ->daily()
    ->name('cleanup:ssh-attempts');

Schedule::call(fn () => NginxHit::where('timestamp', '<', now()->subDays(90))->delete())
    ->daily()
    ->name('cleanup:nginx-hits');

Schedule::call(fn () => ResourceSnapshot::where('recorded_at', '<', now()->subDays(7))->delete())
    ->daily()
    ->name('cleanup:snapshots');

Schedule::call(fn () => SiteCheck::where('checked_at', '<', now()->subDays(30))->delete())
    ->daily()
    ->name('cleanup:site-checks');

Schedule::call(fn () => CowrieLogin::where('is_success', false)->where('timestamp', '<', now()->subDays(30))->delete())
    ->daily()
    ->name('cleanup:cowrie-logins');
