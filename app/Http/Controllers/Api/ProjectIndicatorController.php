<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectIndicatorResource;
use App\Services\ProjectGuard;
use App\Services\ProjectIndicatorCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** API-K05-08 (BL-12). Read-only: the indicator is calculated, never edited. */
class ProjectIndicatorController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private ProjectIndicatorCalculator $indicator,
    ) {}

    /** GET /api/v1/projects/{projectId}/indicator  (operationId: getProjectIndicator) */
    public function show(Request $request, string $projectId)
    {
        $project = $this->projects->forMember($projectId, $request->user());
        Gate::authorize('viewIndicator', $project);

        return new ProjectIndicatorResource($this->indicator->forProject($projectId));
    }
}
