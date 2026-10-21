<?php

use App\Http\Controllers\Api\ContextController;
use App\Http\Controllers\Api\ControlDataController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MomController;
use Illuminate\Support\Facades\Route;

// Prefix /api/v1 is set in bootstrap/app.php. Keep this list in sync with openapi.yaml.

Route::middleware('auth:api')->group(function () {
    // Context (F-1 / D-1 mocks)
    Route::get('/me', [ContextController::class, 'me']);
    Route::get('/projects', [ContextController::class, 'projects']);

    // API-K05-01 Meetings: reference endpoint
    Route::get('/projects/{projectId}/meetings', [MeetingController::class, 'index']);
    Route::post('/projects/{projectId}/meetings', [MeetingController::class, 'store']);
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
    Route::patch('/meetings/{meeting}/status', [MeetingController::class, 'updateStatus']);

    // API-K05-02 MoM write and read
    Route::post('/meetings/{meeting}/mom', [MomController::class, 'store']);
    Route::get('/moms/{mom}', [MomController::class, 'show']);
    Route::put('/moms/{mom}', [MomController::class, 'update']);

    // API-K05-03 MoM review
    Route::patch('/moms/{mom}/status', [MomController::class, 'updateStatus']);

    // TODO US-4: GET /action-items, PATCH /action-items/{actionItem}/status

    // API-K05-09 for D-3 (stub in slice 1)
    Route::get('/projects/{projectId}/control-data', [ControlDataController::class, 'show']);
});
