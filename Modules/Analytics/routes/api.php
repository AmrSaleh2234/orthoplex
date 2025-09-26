<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\app\Http\Controllers\AnalyticsController;

Route::middleware(['auth:api', 'permission:analytics.read'])->prefix('analytics')->group(function () {
    Route::get('/daily-logins', [AnalyticsController::class, 'getDailyLogins'])->name('analytics.daily-logins');
});
