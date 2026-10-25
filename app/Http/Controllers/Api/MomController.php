<?php

namespace App\Http\Controllers\Api;

use App\Enums\MomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMomRequest;
use App\Http\Requests\UpdateMomStatusRequest;
use App\Http\Resources\MomResource;
use App\Models\Meeting;
use App\Models\Mom;
use App\Services\MomStatusService;
use App\Services\MomWriter;
use App\Services\ProjectGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * API-K05-02 and API-K05-03: MoM endpoints. Shared file: each owner adds only
 * their own methods below. Follow the reference endpoint
 * (Api\MeetingController::index) and doc/DESIGN.md §4.
 *
 * Guard a bound MoM's project with:
 *   $this->projects->forMember($mom->meeting->project_id, $request->user());
 *
 * Write rules (DONE meeting, one MoM per meeting, editable statuses, sync of
 * attendance and action items) live in MomWriter, shared with the MoM pages.
 */
class MomController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private MomStatusService $statuses,
        private MomWriter $writer,
    ) {}

    /** POST /api/v1/meetings/{meetingId}/mom  (operationId: createMom) */
    public function store(SaveMomRequest $request, Meeting $meeting)
    {
        $account = $request->user();
        $project = $this->projects->forMember($meeting->project_id, $account);
        Gate::authorize('create', Mom::class);

        $mom = $this->writer->create($meeting, $project, $account, $request->validated());

        return (new MomResource($this->loaded($mom)))->response()->setStatusCode(201);
    }

    /** GET /api/v1/moms/{momId}  (operationId: getMom) */
    public function show(Request $request, Mom $mom)
    {
        $this->projects->forMember($mom->meeting->project_id, $request->user());
        Gate::authorize('view', $mom);

        return new MomResource($this->loaded($mom));
    }

    /** PUT /api/v1/moms/{momId}  (operationId: updateMom) */
    public function update(SaveMomRequest $request, Mom $mom)
    {
        $project = $this->projects->forMember($mom->meeting->project_id, $request->user());
        Gate::authorize('update', $mom);

        $this->writer->update($mom, $project, $request->validated());

        return new MomResource($this->loaded($mom));
    }

    /** PATCH /api/v1/moms/{momId}/status  (operationId: updateMomStatus) */
    public function updateStatus(UpdateMomStatusRequest $request, Mom $mom)
    {
        $this->projects->forMember($mom->meeting->project_id, $request->user());

        $to = MomStatus::from($request->validated('status'));
        Gate::authorize($to === MomStatus::IN_REVIEW ? 'submit' : 'review', $mom);

        $this->statuses->change($mom, $to, $request->user(), $request->validated('reviewNote'));

        return new MomResource($this->loaded($mom));
    }

    private function loaded(Mom $mom): Mom
    {
        return $mom->refresh()->load(['meeting', 'actionItems.mom.meeting']);
    }
}
