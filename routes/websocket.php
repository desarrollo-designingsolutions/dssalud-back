<?php

use App\Http\Controllers\WebSocketController;
use Illuminate\Support\Facades\Route;

    /*
    |--------------------------------------------------------------------------
    | Websocket
    |--------------------------------------------------------------------------
    */

    Route::get('/websocket/progress/{batchId}', [WebSocketController::class, 'getProgress']);
    Route::get('/websocket/connection-status', [WebSocketController::class, 'checkConnection']);
    Route::delete('/websocket/progress/{batchId}', [WebSocketController::class, 'cleanupProgress']);
