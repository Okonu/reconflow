<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ingestion\Http\Controllers\BatchController;
use Modules\Ingestion\Http\Controllers\DemoController;
use Modules\Ingestion\Http\Controllers\TemplateController;
use Modules\Ingestion\Http\Controllers\UploadController;

Route::middleware('auth')->name('ingestion.')->group(function (): void {
    Route::get('/uploads', [UploadController::class, 'index'])->name('uploads.index');
    Route::post('/uploads', [UploadController::class, 'store'])->name('uploads.store');
    Route::get('/uploads/templates/{source}.xlsx', [TemplateController::class, 'template'])->name('uploads.template');
    Route::get('/uploads/sample-pack.zip', [TemplateController::class, 'samplePack'])->name('uploads.sample-pack');
    Route::get('/uploads/{staging}', [UploadController::class, 'show'])->whereUuid('staging')->name('uploads.show');
    Route::post('/uploads/{staging}/confirm', [UploadController::class, 'confirm'])->whereUuid('staging')->name('uploads.confirm');
    Route::post('/uploads/{staging}/cancel', [UploadController::class, 'cancel'])->whereUuid('staging')->name('uploads.cancel');
    Route::get('/batches', [BatchController::class, 'index'])->name('batches.index');
    Route::get('/batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
    Route::post('/demo/reset', [DemoController::class, 'reset'])->name('demo.reset');
});
