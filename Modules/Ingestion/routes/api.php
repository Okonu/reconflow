<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ingestion\Http\Controllers\MockErpController;
use Modules\Ingestion\Http\Controllers\MockSourceController;
use Modules\Ingestion\Http\Middleware\VerifySourceSystemToken;

Route::middleware(VerifySourceSystemToken::class)->group(function (): void {
    Route::post('/mock/erp/journals', MockErpController::class)->name('mock.erp.journals');
    Route::get('/mock/{source}', MockSourceController::class)->name('mock.source');
});
