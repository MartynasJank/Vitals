<?php

use App\Livewire\Dashboard;
use App\Livewire\Databases;
use App\Livewire\Logs;
use App\Livewire\Resources;
use App\Livewire\Security;
use App\Livewire\Services;
use App\Livewire\Sites;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/resources', Resources::class)->name('resources');
Route::get('/sites', Sites::class)->name('sites');
Route::get('/services', Services::class)->name('services');
Route::get('/security', Security::class)->name('security');
Route::get('/logs', Logs::class)->name('logs');
Route::get('/databases', Databases::class)->name('databases');
