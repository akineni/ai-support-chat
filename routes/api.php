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
        Route::post('/', [Customer\ChatController::class, 'start'])
            ->middleware('throttle:start-conversation');

        Route::prefix('/{sessionToken}')->group(function () {
            Route::get('/messages', [Customer\ChatController::class, 'history']);
            Route::post('/messages', [Customer\ChatController::class, 'sendMessage'])
                ->middleware('throttle:send-message');
            Route::post('/typing', [Customer\ChatController::class, 'typing'])
                ->middleware('throttle:typing');
        });
    });

    // -------------------------------------------------------
    // Agent routes - Sanctum auth required
    // -------------------------------------------------------
    Route::prefix('agent')->middleware('auth:sanctum')->group(function () {
        Route::get('/conversations', [Agent\ChatController::class, 'index']);
        Route::get('/conversations/unread-counts', [Agent\ChatController::class, 'unreadCounts']);

        Route::get('/conversations/{uuid}/messages', [Agent\ChatController::class, 'messages']);
        Route::post('/conversations/{uuid}/takeover', [Agent\ChatController::class, 'takeover']);
        Route::post('/conversations/{uuid}/release', [Agent\ChatController::class, 'release']);
        Route::post('/conversations/{uuid}/reply', [Agent\ChatController::class, 'reply']);

        // Agent typing indicator
        Route::post('/conversations/{uuid}/typing', [Agent\ChatController::class, 'typing']);
    });
});
