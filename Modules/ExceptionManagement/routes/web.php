<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ExceptionManagement\Http\Controllers\ExceptionActionController;
use Modules\ExceptionManagement\Http\Controllers\ExceptionController;
use Modules\ExceptionManagement\Http\Controllers\SignoffController;

Route::middleware('auth')->group(function (): void {
    Route::get('/exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
    Route::post('/exceptions/assign', [ExceptionActionController::class, 'assign'])->name('exceptions.assign');
    Route::get('/exceptions/{exception}', [ExceptionController::class, 'show'])->name('exceptions.show');
    Route::post('/exceptions/{exception}/review', [ExceptionActionController::class, 'review'])->name('exceptions.review');
    Route::post('/exceptions/{exception}/resolve', [ExceptionActionController::class, 'resolve'])->name('exceptions.resolve');
    Route::post('/exceptions/{exception}/comments', [ExceptionActionController::class, 'comment'])->name('exceptions.comment');
    Route::get('/signoff/{date}', [SignoffController::class, 'show'])->where('date', '\d{4}-\d{2}-\d{2}')->name('signoff.show');
    Route::post('/signoff/{date}', [SignoffController::class, 'store'])->where('date', '\d{4}-\d{2}-\d{2}')->name('signoff.store');
    Route::post('/signoff/{date}/reopen', [SignoffController::class, 'reopen'])->where('date', '\d{4}-\d{2}-\d{2}')->name('signoff.reopen');
});
