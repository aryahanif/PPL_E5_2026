<?php

use App\Http\Controllers\Web\DevLoginController;
use App\Http\Controllers\Web\MeetingPageController;
use App\Http\Controllers\Web\ProjectPageController;
use Illuminate\Support\Facades\Route;

Route::get('/dev-login', [DevLoginController::class, 'show']);
Route::post('/dev-login', [DevLoginController::class, 'store']);
Route::post('/logout', [DevLoginController::class, 'destroy']);

Route::middleware('auth:web')->group(function () {
    Route::redirect('/', '/projects');
    Route::get('/projects', [ProjectPageController::class, 'index']);
    Route::get('/projects/{projectId}/meetings', [ProjectPageController::class, 'meetings']);

    // US-1 (PM): schedule a meeting, meeting detail, mark done/cancelled
    Route::get('/projects/{projectId}/meetings/create', [MeetingPageController::class, 'create']);
    Route::post('/projects/{projectId}/meetings', [MeetingPageController::class, 'store']);
    Route::get('/meetings/{meeting}', [MeetingPageController::class, 'show']);
    Route::patch('/meetings/{meeting}/status', [MeetingPageController::class, 'updateStatus']);

    // TODO US-2..US-4: MoM editor, MoM review, action items (PRD §11)
});
