<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Rbac\Http\Controllers\RoleController;
use Modules\Rbac\Http\Controllers\UserRoleController;

Route::middleware('auth')->prefix('access')->name('rbac.')->group(function (): void {
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::put('/users/{assignee}/roles', UserRoleController::class)->whereNumber('assignee')->name('users.roles');
});
