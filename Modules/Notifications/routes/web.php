<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationController;

Route::middleware('auth')->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read')->whereUuid('notification');
});
