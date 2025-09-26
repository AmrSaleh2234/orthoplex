<?php

use Illuminate\Support\Facades\Route;
use Modules\RolesAndPermissions\app\Http\Controllers\RolesAndPermissionsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('rolesandpermissions', RolesAndPermissionsController::class)->names('rolesandpermissions');
});
