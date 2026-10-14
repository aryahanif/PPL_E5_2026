<?php

use App\Http\Controllers\Web\DevLoginController;
use App\Http\Controllers\Web\ProjectPageController;
use Illuminate\Support\Facades\Route;

Route::get('/dev-login', [DevLoginController::class, 'show']);
Route::post('/dev-login', [DevLoginController::class, 'store']);
Route::post('/logout', [DevLoginController::class, 'destroy']);

Route::middleware('auth:web')->group(function () {
    Route::redirect('/', '/projects');
    Route::get('/projects', [ProjectPageController::class, 'index']);
    Route::get('/projects/{projectId}/meetings', [ProjectPageController::class, 'meetings']);

    // TODO US-1..US-4: meeting detail, MoM editor, MoM review, action items (PRD §11)
});
