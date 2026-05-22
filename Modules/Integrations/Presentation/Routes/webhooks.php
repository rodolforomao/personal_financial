<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Presentation\Http\Controllers\WebhookController;

Route::middleware('throttle:120,1')->group(function () {
    Route::post('/telegram', [WebhookController::class, 'telegram']);
    Route::post('/whatsapp', [WebhookController::class, 'whatsapp']);
});
