<?php

use App\Http\Controllers\Web\ActionItemPageController;
use App\Http\Controllers\Web\DevLoginController;
use App\Http\Controllers\Web\MeetingPageController;
use App\Http\Controllers\Web\MomPageController;
use App\Http\Controllers\Web\ProjectPageController;
use App\Http\Controllers\Web\RiskIssuePageController;
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

    // US-2 (TW): MoM editor. US-3 (TL): MoM view and review
    Route::get('/meetings/{meeting}/mom/create', [MomPageController::class, 'create']);
    Route::post('/meetings/{meeting}/mom', [MomPageController::class, 'store']);
    Route::get('/moms/{mom}', [MomPageController::class, 'show']);
    Route::get('/moms/{mom}/edit', [MomPageController::class, 'edit']);
    Route::put('/moms/{mom}', [MomPageController::class, 'update']);
    Route::patch('/moms/{mom}/status', [MomPageController::class, 'updateStatus']);

    // US-4: action items
    Route::get('/action-items', [ActionItemPageController::class, 'all']);
    Route::get('/action-items/{actionItem}', [ActionItemPageController::class, 'show']);
    Route::get('/projects/{projectId}/action-items', [ActionItemPageController::class, 'index']);
    Route::patch('/action-items/{actionItem}/status', [ActionItemPageController::class, 'updateStatus']);

    // BL-10, BL-11: risks and issues
    Route::get('/projects/{projectId}/risk-issues', [RiskIssuePageController::class, 'index']);
    Route::get('/projects/{projectId}/risk-issues/create', [RiskIssuePageController::class, 'create']);
    Route::post('/projects/{projectId}/risk-issues', [RiskIssuePageController::class, 'store']);
    Route::get('/risk-issues/{riskIssue}', [RiskIssuePageController::class, 'show']);
    Route::patch('/risk-issues/{riskIssue}', [RiskIssuePageController::class, 'update']);
});
