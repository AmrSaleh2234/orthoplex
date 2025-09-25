<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\GdprController;
use Modules\User\Http\Controllers\UserController;

Route::middleware('auth:api')->prefix('v1')->group(function () {
    Route::apiResource('users', UserController::class)->names('user');

    // User Management
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:users.read')
            ->name('users.index');

        Route::delete('/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete')
            ->name('users.destroy');

        Route::post('/{id}/restore', [UserController::class, 'restore'])
            ->middleware('permission:users.restore')
            ->name('users.restore');
    });

    // GDPR
    Route::prefix('gdpr')->group(function () {
        Route::post('/export', [GdprController::class, 'requestExport'])->name('gdpr.export');

        Route::post('/delete-request', [GdprController::class, 'requestDeletion'])
            ->middleware('permission:gdpr.delete.request')
            ->name('gdpr.delete.request');

        Route::middleware('permission:gdpr.delete.manage')->group(function () {
            Route::get('/delete-requests', [GdprController::class, 'getDeleteRequests'])->name('gdpr.delete.requests');
            Route::post('/delete-requests/{request}/approve', [GdprController::class, 'approveDeleteRequest'])->name('gdpr.delete.approve');
            Route::post('/delete-requests/{request}/deny', [GdprController::class, 'denyDeleteRequest'])->name('gdpr.delete.deny');
        });
    });
});
