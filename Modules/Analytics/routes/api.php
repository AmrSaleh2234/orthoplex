<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\AnalyticsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('analytics', AnalyticsController::class)->names('analytics');
});

Route::middleware(['auth:api', 'permission:analytics.read'])->prefix('analytics')->group(function () {
    Route::get('/daily-logins', [AnalyticsController::class, 'getDailyLogins'])->name('analytics.daily-logins');
});
