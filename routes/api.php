<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Full\ProjectController as FullProjectController;
use App\Http\Controllers\Api\Full\TicketController as FullTicketController;
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

/*
| Full access API (/api/full): one key per staff user, reaches all their projects, "project" is sent in the payload.
| Separate from the bot API above. See FULL-API.md.
*/
Route::prefix('full')->middleware(['api.full', 'throttle:120,1'])->group(function () {
    Route::get('/me', [FullTicketController::class, 'me']);
    Route::get('/options', [FullTicketController::class, 'options']);

    Route::get('/projects', [FullProjectController::class, 'index']);
    Route::post('/projects', [FullProjectController::class, 'store']);
    Route::get('/projects/{project}', [FullProjectController::class, 'show']);
    Route::match(['put', 'patch'], '/projects/{project}', [FullProjectController::class, 'update']);

    Route::get('/tickets', [FullTicketController::class, 'index']);
    Route::post('/tickets', [FullTicketController::class, 'store']);
    Route::get('/tickets/{number}', [FullTicketController::class, 'show']);
    Route::match(['put', 'patch'], '/tickets/{number}', [FullTicketController::class, 'update']);
    Route::delete('/tickets/{number}', [FullTicketController::class, 'destroy']);
});
