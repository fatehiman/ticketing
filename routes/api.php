<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

/*
| Bot API (JSON). Every route is under /api. See API.md.
| Login: POST /api/auth/start → developer opens login_url → POST /api/auth/token.
*/

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/start', [AuthController::class, 'start']);
    Route::post('/auth/token', [AuthController::class, 'token']);
});

Route::middleware(['api.token', 'throttle:120,1'])->group(function () {
    Route::get('/me', [TicketController::class, 'me']);
    Route::get('/options', [TicketController::class, 'options']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{number}', [TicketController::class, 'show']);
});
