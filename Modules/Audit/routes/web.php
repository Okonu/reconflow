<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\AuditExportController;
use Modules\Audit\Http\Controllers\AuditIntegrityController;
use Modules\Audit\Http\Controllers\AuditLogController;

Route::middleware('auth')->prefix('audit')->name('audit.')->group(function (): void {
    Route::get('/', [AuditLogController::class, 'index'])->name('index');
    Route::post('/verify', AuditIntegrityController::class)->name('verify');
    Route::get('/export', AuditExportController::class)->middleware('throttle:10,1')->name('export');
});
