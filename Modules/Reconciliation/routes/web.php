<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reconciliation\Http\Controllers\MatchReviewController;
use Modules\Reconciliation\Http\Controllers\PossibleMatchController;
use Modules\Reconciliation\Http\Controllers\ReportController;
use Modules\Reconciliation\Http\Controllers\RunController;

Route::middleware('auth')->group(function (): void {
    Route::get('/runs', [RunController::class, 'index'])->name('runs.index');
    Route::post('/runs', [RunController::class, 'store'])->name('runs.store');
    Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');
    Route::get('/matches', [MatchReviewController::class, 'index'])->name('matches.index');
    Route::post('/matches/confirm', [MatchReviewController::class, 'confirm'])->name('matches.confirm');
    Route::post('/matches/{result}/reject', [MatchReviewController::class, 'reject'])->name('matches.reject');
    Route::get('/results/{result}/possible-matches', [PossibleMatchController::class, 'index'])->name('results.possible-matches');
    Route::post('/results/{result}/manual-match', [PossibleMatchController::class, 'store'])->name('results.manual-match');
    Route::get('/reports/reconciliation', [ReportController::class, 'index'])->name('reports.reconciliation');
    Route::get('/reports/reconciliation/export', [ReportController::class, 'export'])->middleware('throttle:20,1')->name('reports.reconciliation.export');
    Route::post('/reports/reconciliation/export-unmasked', [ReportController::class, 'exportUnmasked'])->middleware('throttle:10,1')->name('reports.reconciliation.export-unmasked');
});
