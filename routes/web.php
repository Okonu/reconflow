<?php

declare(strict_types=1);

use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
});
