<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\GdprController;
use Modules\User\Http\Controllers\UserController;

Route::middleware('auth:api')->prefix('v1')->group(function () {
    // Standard CRUD routes for users
    Route::apiResource('users', UserController::class)->names('user');

    // Extended User Management routes
    Route::prefix('users')->group(function () {
        // Basic CRUD (already covered by apiResource above, but explicit for clarity)
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:users.read')
            ->name('users.index');

        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:users.create')
            ->name('users.store');

        Route::get('/{id}', [UserController::class, 'show'])
            ->middleware('permission:users.read')
            ->name('users.show');

        Route::put('/{id}', [UserController::class, 'update'])
            ->middleware('permission:users.update')
            ->name('users.update');

        Route::delete('/{id}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete')
            ->name('users.destroy');

        // Additional user management operations
        Route::post('/{id}/restore', [UserController::class, 'restore'])
            ->middleware('permission:users.restore')
            ->name('users.restore');

        Route::get('/search', [UserController::class, 'search'])
            ->middleware('permission:users.read')
            ->name('users.search');

        Route::get('/status/{status}', [UserController::class, 'getByStatus'])
            ->middleware('permission:users.read')
            ->name('users.by-status');

        Route::get('/trashed/list', [UserController::class, 'trashed'])
            ->middleware('permission:users.read')
            ->name('users.trashed');

        // User status management
        Route::patch('/{id}/status', [UserController::class, 'updateStatus'])
            ->middleware('permission:users.update')
            ->name('users.update-status');

        // Role management
        Route::post('/{id}/roles', [UserController::class, 'assignRole'])
            ->middleware('permission:roles.assign')
            ->name('users.assign-role');

        Route::delete('/{id}/roles', [UserController::class, 'removeRole'])
            ->middleware('permission:roles.assign')
            ->name('users.remove-role');

        // Bulk operations
        Route::post('/bulk', [UserController::class, 'bulkOperation'])
            ->middleware('permission:users.bulk')
            ->name('users.bulk');
    });

    // GDPR Compliance Routes
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
