<?php

namespace App\Http\Controllers\Api;

use App\Contracts\IdentityProvider;
use App\Enums\MeetingStatus;
use App\Enums\ParticipantType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateMeetingRequest;
use App\Http\Requests\ListMeetingsRequest;
use App\Http\Requests\UpdateMeetingStatusRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Services\MeetingStatusService;
use App\Services\ProjectGuard;
use App\Services\StatusHistoryRecorder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
    public function __construct(
        private ProjectGuard $projects,
        private IdentityProvider $identity,
        private StatusHistoryRecorder $history,
        private MeetingStatusService $statuses,
    ) {}

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

    /** POST /api/v1/projects/{projectId}/meetings  (operationId: createMeeting) */
    public function store(CreateMeetingRequest $request, string $projectId)
    {
        $account = $request->user();
        $project = $this->projects->forActiveMember($projectId, $account);
        Gate::authorize('create', Meeting::class);

        $participants = $request->validated('participants');

        foreach ($participants as $p) {
            $id = $p['accountId'] ?? null;

            if ($p['participantType'] === ParticipantType::INTERNAL->value
                && (! $this->identity->find($id) || ! $project->hasMember($id))) {
                throw ValidationException::withMessages(['participants' => "Akun {$id} bukan anggota proyek"]);
            }
        }

        $meeting = DB::transaction(function () use ($request, $account, $projectId, $participants) {
            $meeting = Meeting::create([
                'project_id' => $projectId,
                'title' => $request->validated('title'),
                'scheduled_at' => Carbon::parse($request->validated('scheduledAt'))->utc(),
                'location' => $request->validated('location'),
                'status' => MeetingStatus::SCHEDULED,
                'created_by' => $account->accountId,
            ]);

            foreach ($participants as $p) {
                $internal = $p['participantType'] === ParticipantType::INTERNAL->value;
                $meeting->participants()->create([
                    'participant_type' => $p['participantType'],
                    'account_id' => $internal ? $p['accountId'] : null,
                    'display_name' => $internal ? null : $p['displayName'],
                ]);
            }

            $this->history->record($meeting, null, MeetingStatus::SCHEDULED, $account);

            return $meeting;
        });

        return (new MeetingResource($meeting->load(['participants', 'mom'])))
            ->response()->setStatusCode(201);
    }

    /** GET /api/v1/meetings/{meetingId}  (operationId: getMeeting) */
    public function show(Request $request, Meeting $meeting)
    {
        $this->projects->forMember($meeting->project_id, $request->user());
        Gate::authorize('view', $meeting);

        return new MeetingResource($meeting->load(['participants', 'mom']));
    }

    /** PATCH /api/v1/meetings/{meetingId}/status  (operationId: updateMeetingStatus) */
    public function updateStatus(UpdateMeetingStatusRequest $request, Meeting $meeting)
    {
        $this->projects->forMember($meeting->project_id, $request->user());
        Gate::authorize('updateStatus', $meeting);

        $meeting = $this->statuses->change(
            $meeting,
            MeetingStatus::from($request->validated('status')),
            $request->user(),
        );

        return new MeetingResource($meeting->load(['participants', 'mom']));
    }
}
