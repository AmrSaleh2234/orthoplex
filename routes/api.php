<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Webhooks\app\Http\Controllers\WebhooksController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/provision-tenant', [WebhooksController::class, 'provisionTenant'])
    ->name('webhooks.provision-tenant');
