<?php

namespace App\Http\Controllers\Api;

use App\Contracts\IdentityProvider;
use App\Data\Project;
use App\Enums\ActionItemStatus;
use App\Enums\MeetingStatus;
use App\Enums\MomStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMomRequest;
use App\Http\Requests\UpdateMomStatusRequest;
use App\Http\Resources\MomResource;
use App\Models\Meeting;
use App\Models\Mom;
use App\Services\MomStatusService;
use App\Services\ProjectGuard;
use App\Services\StatusHistoryRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * API-K05-02 and API-K05-03: MoM endpoints. Shared file: each owner adds only
 * their own methods below. Follow the reference endpoint
 * (Api\MeetingController::index) and doc/DESIGN.md §4.
 *
 * Guard a bound MoM's project with:
 *   $this->projects->forMember($mom->meeting->project_id, $request->user());
 */
class MomController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private IdentityProvider $identity,
        private StatusHistoryRecorder $history,
        private MomStatusService $statuses,
    ) {}

    /** POST /api/v1/meetings/{meetingId}/mom  (operationId: createMom) */
    public function store(SaveMomRequest $request, Meeting $meeting)
    {
        $account = $request->user();
        $project = $this->projects->forMember($meeting->project_id, $account);
        Gate::authorize('create', Mom::class);

        if ($meeting->status !== MeetingStatus::DONE) {
            throw new ApiException(409, 'MEETING_NOT_DONE', 'MoM hanya dapat ditulis untuk rapat berstatus DONE');
        }

        if ($meeting->mom()->exists()) {
            throw new ApiException(409, 'MOM_ALREADY_EXISTS', 'Rapat ini sudah memiliki MoM');
        }

        $this->checkReferences($request, $meeting, $project);

        $mom = DB::transaction(function () use ($request, $meeting, $account) {
            $mom = $meeting->mom()->create([
                'meeting_date' => $request->validated('meetingDate'),
                'discussion' => $request->validated('discussion'),
                'decision' => $request->validated('decision'),
                'change_summary' => $request->validated('changeSummary'),
                'status' => MomStatus::DRAFT,
                'written_by' => $account->accountId,
            ]);

            $this->syncContent($request, $meeting, $mom);
            $this->history->record($mom, null, MomStatus::DRAFT, $account);

            return $mom;
        });

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

        if (! in_array($mom->status, [MomStatus::DRAFT, MomStatus::REVISION], true)) {
            throw new ApiException(409, 'MOM_NOT_EDITABLE', "MoM berstatus {$mom->status->value} tidak dapat diubah");
        }

        $this->checkReferences($request, $mom->meeting, $project, $mom);

        DB::transaction(function () use ($request, $mom) {
            $mom->update([
                'meeting_date' => $request->validated('meetingDate'),
                'discussion' => $request->validated('discussion'),
                'decision' => $request->validated('decision'),
                'change_summary' => $request->validated('changeSummary'),
            ]);

            $this->syncContent($request, $mom->meeting, $mom);
        });

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

    /** Checks that need F-1 / D-1 or this meeting's data. Failures are 400 VALIDATION_ERROR. */
    private function checkReferences(SaveMomRequest $request, Meeting $meeting, Project $project, ?Mom $mom = null): void
    {
        $participantIds = $meeting->participants()->pluck('id')->all();

        if (array_diff($request->validated('attendance'), $participantIds)) {
            throw ValidationException::withMessages(['attendance' => 'Peserta bukan milik rapat ini']);
        }

        $ownItemIds = $mom?->actionItems()->pluck('id')->all() ?? [];

        foreach ($request->validated('actionItems') as $i => $item) {
            $pic = $item['picAccountId'];

            if (! $this->identity->find($pic) || ! $project->hasMember($pic)) {
                throw ValidationException::withMessages(["actionItems.{$i}.picAccountId" => "Akun {$pic} bukan anggota proyek"]);
            }

            if (! empty($item['actionItemId']) && ! in_array($item['actionItemId'], $ownItemIds, true)) {
                throw ValidationException::withMessages(["actionItems.{$i}.actionItemId" => 'Action item bukan milik MoM ini']);
            }
        }
    }

    /** Replaces attendance and action items: with ID = update, without ID = create (TODO), missing = delete. */
    private function syncContent(SaveMomRequest $request, Meeting $meeting, Mom $mom): void
    {
        $meeting->participants()->update(['attended' => false]);
        $meeting->participants()->whereIn('id', $request->validated('attendance'))->update(['attended' => true]);

        $items = $request->validated('actionItems');

        $mom->actionItems()->whereNotIn('id', array_filter(array_column($items, 'actionItemId')))->delete();

        foreach ($items as $item) {
            $attributes = [
                'description' => $item['description'],
                'pic_account_id' => $item['picAccountId'],
                'due_date' => $item['dueDate'],
            ];

            if (! empty($item['actionItemId'])) {
                $mom->actionItems()->whereKey($item['actionItemId'])->first()->update($attributes);
            } else {
                $mom->actionItems()->create($attributes + ['status' => ActionItemStatus::TODO]);
            }
        }
    }
}
