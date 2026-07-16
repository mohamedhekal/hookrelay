<?php

declare(strict_types=1);

use Hekal\HookRelay\Http\Controllers\InboundWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('ingest/{source}', InboundWebhookController::class)
    ->name('hookrelay.ingest');
