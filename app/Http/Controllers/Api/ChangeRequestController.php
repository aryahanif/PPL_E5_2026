<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChangeRequestStatus;
use App\Enums\MomStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeChangeRequestRequest;
use App\Http\Requests\CreateChangeRequestRequest;
use App\Http\Requests\DecideChangeRequestRequest;
use App\Http\Resources\ChangeRequestResource;
use App\Models\ChangeRequest;
use App\Models\Mom;
use App\Services\ChangeRequestStatusService;
use App\Services\ProjectGuard;
use Illuminate\Support\Facades\Gate;

class ChangeRequestController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private ChangeRequestStatusService $statuses,
    ) {}

    /** POST /api/v1/change-requests (operationId: createChangeRequest) */
    public function store(CreateChangeRequestRequest $request)
    {
        $account = $request->user();
        $mom = Mom::findOrFail($request->validated('momId'));
        $projectId = $mom->meeting->project_id;
        $this->projects->forActiveMember($projectId, $account);
        Gate::authorize('create', ChangeRequest::class);

        if ($mom->status !== MomStatus::FINAL) {
            throw new ApiException(409, 'MOM_NOT_FINAL', 'Perubahan hanya dapat dicatat dari MoM berstatus FINAL');
        }

        $changeRequest = $this->statuses->record($request->validated(), $projectId, $account);

        return (new ChangeRequestResource($changeRequest->load('affectedTasks')))
            ->response()->setStatusCode(201);
    }

    /** PATCH /api/v1/change-requests/{changeRequestId}/analysis (operationId: analyzeChangeRequest) */
    public function analyze(AnalyzeChangeRequestRequest $request, ChangeRequest $changeRequest)
    {
        $this->projects->forMember($changeRequest->project_id, $request->user());
        Gate::authorize('analyze', $changeRequest);

        $changeRequest = $this->statuses->analyze($changeRequest, $request->validated(), $request->user());

        return new ChangeRequestResource($changeRequest);
    }

    /** PATCH /api/v1/change-requests/{changeRequestId}/decision (operationId: decideChangeRequest) */
    public function decide(DecideChangeRequestRequest $request, ChangeRequest $changeRequest)
    {
        $this->projects->forMember($changeRequest->project_id, $request->user());
        Gate::authorize('decide', $changeRequest);

        $changeRequest = $this->statuses->decide(
            $changeRequest,
            ChangeRequestStatus::from($request->validated('decision')),
            $request->validated('decisionNote'),
            $request->user(),
        );

        return new ChangeRequestResource($changeRequest);
    }
}