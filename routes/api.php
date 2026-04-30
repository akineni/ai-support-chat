<?php

use App\Http\Controllers\Agent;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Customer;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------
    // Auth routes
    // -------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/login', [Auth\AuthController::class, 'login']);
        Route::post('/logout', [Auth\AuthController::class, 'logout'])
            ->middleware('auth:sanctum');
    });

    // -------------------------------------------------------
    // Customer routes - no auth, identified by session_token
    // -------------------------------------------------------
    Route::prefix('chat')->group(function () {
        Route::post('/', [Customer\ChatController::class, 'start']);
        Route::get('/{sessionToken}/messages', [Customer\ChatController::class, 'history']);
        Route::post('/{sessionToken}/messages', [Customer\ChatController::class, 'sendMessage']);

        // Customer typing indicator
        Route::post('/{sessionToken}/typing', [Customer\ChatController::class, 'typing']);
    });

    // -------------------------------------------------------
    // Agent routes - Sanctum auth required
    // -------------------------------------------------------
    Route::prefix('agent')->middleware('auth:sanctum')->group(function () {
        Route::get('/conversations', [Agent\ChatController::class, 'index']);
        Route::get('/conversations/{uuid}/messages', [Agent\ChatController::class, 'messages']);
        Route::post('/conversations/{uuid}/takeover', [Agent\ChatController::class, 'takeover']);
        Route::post('/conversations/{uuid}/release', [Agent\ChatController::class, 'release']);
        Route::post('/conversations/{uuid}/reply', [Agent\ChatController::class, 'reply']);

        // Agent typing indicator
        Route::post('/conversations/{uuid}/typing', [Agent\ChatController::class, 'typing']);
    });
});
