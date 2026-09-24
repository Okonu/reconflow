<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('home');
});
