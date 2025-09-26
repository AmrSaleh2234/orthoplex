<?php

use Illuminate\Support\Facades\Route;
use Modules\RolesAndPermissions\app\Http\Controllers\RolesAndPermissionsController;
use Modules\RolesAndPermissions\app\Http\Controllers\InvitationController;
use Modules\RolesAndPermissions\app\Http\Controllers\RoleController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('rolesandpermissions', RolesAndPermissionsController::class)->names('rolesandpermissions');
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public Invitation Route
Route::post('/invitations/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

// Authenticated & Authorized Routes
Route::middleware('auth:api')->group(function () {
    // Invitations
    Route::post('/invitations', [InvitationController::class, 'invite'])
        ->middleware('permission:users.invite')
        ->name('invitations.invite');

    // Role Management
    Route::middleware('permission:roles.manage')->group(function () {
        Route::post('/roles', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.sync_permissions');
    });
});
