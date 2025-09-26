<?php

use Illuminate\Support\Facades\Route;
use Modules\Webhooks\app\Http\Controllers\WebhooksController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('webhooks', WebhooksController::class)->names('webhooks');
});
