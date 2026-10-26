<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListChangeLogRequest;
use App\Http\Resources\ChangeRequestResource;
use App\Models\ChangeRequest;
use App\Services\ProjectGuard;

/** API-K05-06 */
class ChangeLogController extends Controller
{
    public function __construct(private ProjectGuard $projects) {}

    /** GET /api/v1/projects/{projectId}/change-log  (operationId: listChangeLog) */
    public function index(ListChangeLogRequest $request, string $projectId)
    {
        $this->projects->forMember($projectId, $request->user());

        $items = ChangeRequest::with('affectedTasks')
            ->where('project_id', $projectId)
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->get();

        return ChangeRequestResource::collection($items);
    }
}
