<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListMeetingsRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Services\ProjectGuard;
use Illuminate\Support\Facades\Gate;

/**
 * API-K05-01. REFERENCE ENDPOINT: copy this pattern for every new endpoint.
 *
 *  1. Validate input with a Form Request (400 VALIDATION_ERROR).
 *  2. Check the project with ProjectGuard (404 PROJECT_NOT_FOUND / 403 FORBIDDEN).
 *  3. Check the role with a Policy via Gate::authorize (403 FORBIDDEN).
 *  4. Query with Eloquent, eager-load relations.
 *  5. Return an API Resource whose fields match openapi.yaml exactly.
 */
class MeetingController extends Controller
{
    public function __construct(private ProjectGuard $projects) {}

    /** GET /api/v1/projects/{projectId}/meetings  (operationId: listProjectMeetings) */
    public function index(ListMeetingsRequest $request, string $projectId)
    {
        $this->projects->forMember($projectId, $request->user());
        Gate::authorize('viewAny', Meeting::class);

        $meetings = Meeting::query()
            ->with(['participants', 'mom'])
            ->where('project_id', $projectId)
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('scheduled_at')
            ->get();

        return MeetingResource::collection($meetings);
    }

    // TODO US-1 [owner]: store()        POST  /projects/{projectId}/meetings  (createMeeting)
    // TODO US-1 [owner]: show()         GET   /meetings/{meetingId}           (getMeeting)
    // TODO US-1 [owner]: updateStatus() PATCH /meetings/{meetingId}/status    (updateMeetingStatus)
}
