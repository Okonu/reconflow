<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reconciliation\Http\Controllers\PossibleMatchController;
use Modules\Reconciliation\Http\Controllers\RunController;

Route::middleware('auth')->group(function (): void {
    Route::get('/runs', [RunController::class, 'index'])->name('runs.index');
    Route::post('/runs', [RunController::class, 'store'])->name('runs.store');
    Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');
    Route::get('/results/{result}/possible-matches', [PossibleMatchController::class, 'index'])->name('results.possible-matches');
    Route::post('/results/{result}/manual-match', [PossibleMatchController::class, 'store'])->name('results.manual-match');
});
