<?php

use App\Http\Controllers\GithubWebhookController;
use App\Http\Controllers\Internal\CloudTaskHandlerController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/github', [GithubWebhookController::class, 'handle'])
    ->middleware('github.webhook');

Route::post('/internal/queue/handle', [CloudTaskHandlerController::class, 'handle'])
    ->middleware('cloudtasks.handler');
