<?php

use Illuminate\Support\Facades\Route;
use Modules\Webhooks\app\Http\Controllers\WebhooksController;
use Modules\Webhooks\app\Http\Controllers\TenantWebhookController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('webhooks', WebhooksController::class)->names('webhooks');
});

Route::middleware(['auth:api', 'permission:webhooks.manage'])->prefix('webhooks')->group(function () {
    Route::get('/', [TenantWebhookController::class, 'index'])->name('webhooks.index');
    Route::post('/', [TenantWebhookController::class, 'store'])->name('webhooks.store');
    Route::delete('/{webhook}', [TenantWebhookController::class, 'destroy'])->name('webhooks.destroy');
});
