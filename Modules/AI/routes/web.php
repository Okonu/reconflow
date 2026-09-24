<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AI\Http\Controllers\AiController;

Route::middleware('auth')->group(function (): void {
    Route::get('/ai', [AiController::class, 'oversight'])->name('ai.oversight');
    Route::post('/ai/kill-switch', [AiController::class, 'toggleKillSwitch'])->name('ai.kill-switch');
    Route::post('/exceptions/{exception}/ai-triage', [AiController::class, 'triage'])->middleware('throttle:20,1')->name('ai.triage');
    Route::post('/ai/triage-batch', [AiController::class, 'triageBatch'])->middleware('throttle:5,1')->name('ai.triage-batch');
    Route::post('/ai/suggestions/{suggestion}/decision', [AiController::class, 'decide'])->name('ai.decide');
    Route::get('/runs/{run}/ai-summary', [AiController::class, 'latestSummary'])->name('ai.summary.show');
    Route::post('/runs/{run}/ai-summary', [AiController::class, 'summarise'])->middleware('throttle:10,1')->name('ai.summary.store');
});
