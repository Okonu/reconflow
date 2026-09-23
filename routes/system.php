<?php

declare(strict_types=1);

use App\Http\Controllers\System\HealthController;
use App\Http\Controllers\System\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'live'])->name('system.health');
Route::get('/ready', [HealthController::class, 'ready'])->name('system.ready');
Route::get('/metrics', MetricsController::class)->name('system.metrics');
