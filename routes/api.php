<?php

use App\Http\Controllers\Api\AgentActivityController;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Support\Facades\Route;

// Префикс /api добавляется автоматически (bootstrap/app.php → withRouting(api: ...))
Route::prefix('agent')
    ->middleware([AuthenticateDevice::class, 'throttle:300,1'])
    ->group(function () {
        Route::post('/heartbeat', [AgentActivityController::class, 'heartbeat'])->name('api.agent.heartbeat');
        Route::post('/activity', [AgentActivityController::class, 'store'])->name('api.agent.activity');
    });
