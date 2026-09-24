<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\DataProtection\Http\Controllers\ErasureController;
use Modules\DataProtection\Http\Controllers\UnmaskController;

Route::middleware(['auth', 'throttle:30,1'])->group(function (): void {
    Route::post('/pii/unmask', UnmaskController::class)->name('pii.unmask');
    Route::post('/privacy/erasures', ErasureController::class)->name('privacy.erasures.store');
});
