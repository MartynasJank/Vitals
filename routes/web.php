<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\MalwareViewerCsp;
use App\Http\Middleware\RequireAuth;
use App\Livewire\Dashboard;
use App\Livewire\Databases;
use App\Livewire\Honeypot;
use App\Livewire\IpDetail;
use App\Livewire\Logs;
use App\Livewire\MalwareViewer;
use App\Livewire\Resources;
use App\Livewire\Security;
use App\Livewire\Services;
use App\Livewire\Sites;
use App\Livewire\ThreatIntel;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => view('auth.login'))->name('login');
Route::get('/auth/redirect', [AuthController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(RequireAuth::class)->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/resources', Resources::class)->name('resources');
    Route::get('/sites', Sites::class)->name('sites');
    Route::get('/services', Services::class)->name('services');
    Route::get('/security', Security::class)->name('security');
    Route::get('/logs', Logs::class)->name('logs');
    Route::get('/databases', Databases::class)->name('databases');
    Route::get('/threat-intel', ThreatIntel::class)->name('threat-intel');
    Route::get('/threat-intel/ip/{ip}', IpDetail::class)->name('ip-detail');
    Route::get('/honeypot', Honeypot::class)->name('honeypot');
    Route::get('/honeypot/malware', MalwareViewer::class)->name('honeypot.malware')->middleware(MalwareViewerCsp::class);
});
