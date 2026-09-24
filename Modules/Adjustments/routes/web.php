<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Adjustments\Http\Controllers\AdjustmentController;

Route::middleware('auth')->group(function (): void {
    Route::get('/approvals', [AdjustmentController::class, 'index'])->name('adjustments.index');
    Route::post('/exceptions/{exception}/adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
    Route::post('/adjustments/{adjustment}/approve', [AdjustmentController::class, 'approve'])->name('adjustments.approve');
    Route::post('/adjustments/{adjustment}/reject', [AdjustmentController::class, 'reject'])->name('adjustments.reject');
    Route::post('/adjustments/{adjustment}/retry', [AdjustmentController::class, 'retry'])->name('adjustments.retry');
    Route::post('/erp/simulate-failure', [AdjustmentController::class, 'toggleErpFailure'])->name('adjustments.erp-failure');
});
