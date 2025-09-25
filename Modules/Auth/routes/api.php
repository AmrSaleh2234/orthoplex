<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\MagicLinkController;
use Modules\Auth\Http\Controllers\TwoFactorController;

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

// Standard Auth
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:api');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api');
Route::post('/login/2fa', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:api');

// Magic Link Auth
Route::post('/magic-link', [MagicLinkController::class, 'sendMagicLink'])->middleware('throttle:api');
Route::get('/magic-link/{token}', [MagicLinkController::class, 'loginWithMagicLink'])->name('magic.login');

// Email Verification
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [AuthController::class, 'resend'])
    ->middleware(['auth:api', 'throttle:6,1'])
    ->name('verification.send');

// 2FA Management
Route::middleware('auth:api')->group(function () {
    Route::post('/user/2fa/generate', [TwoFactorController::class, 'generateSecret']);
    Route::post('/user/2fa/enable', [TwoFactorController::class, 'enableTwoFactor']);
    Route::post('/user/2fa/disable', [TwoFactorController::class, 'disableTwoFactor']);
});
