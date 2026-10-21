<?php

namespace App\Services;

use App\Contracts\IdentityProvider;
use App\Data\Account;
use App\Data\Project;
use App\Enums\ActionItemStatus;
use App\Enums\MeetingStatus;
use App\Enums\MomStatus;
use App\Exceptions\ApiException;
use App\Models\Meeting;
use App\Models\Mom;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and edits a MoM with its attendance and action items (API-K05-02).
 * Shared by Api\MomController and Web\MomPageController so both enforce the same rules.
 * $data uses the field names of schema MomInput (meetingDate, discussion, ..., actionItems).
 */
class MomWriter
{
    public function __construct(
        private IdentityProvider $identity,
        private StatusHistoryRecorder $history,
    ) {}

    /** 409 unless the meeting is DONE and has no MoM yet. */
    public function assertCanCreate(Meeting $meeting): void
    {
        if ($meeting->status !== MeetingStatus::DONE) {
            throw new ApiException(409, 'MEETING_NOT_DONE', 'MoM hanya dapat ditulis untuk rapat berstatus DONE');
        }

        if ($meeting->mom()->exists()) {
            throw new ApiException(409, 'MOM_ALREADY_EXISTS', 'Rapat ini sudah memiliki MoM');
        }
    }

    /** Only DRAFT and REVISION can be edited. */
    public function isEditable(Mom $mom): bool
    {
        return in_array($mom->status, [MomStatus::DRAFT, MomStatus::REVISION], true);
    }

    public function create(Meeting $meeting, Project $project, Account $by, array $data): Mom
    {
        $this->assertCanCreate($meeting);
        $this->checkReferences($data, $meeting, $project);

        return DB::transaction(function () use ($data, $meeting, $by) {
            $mom = $meeting->mom()->create($this->attributes($data) + [
                'status' => MomStatus::DRAFT,
                'written_by' => $by->accountId,
            ]);

            $this->syncContent($data, $meeting, $mom);
            $this->history->record($mom, null, MomStatus::DRAFT, $by);

            return $mom;
        });
    }

    public function update(Mom $mom, Project $project, array $data): Mom
    {
        if (! $this->isEditable($mom)) {
            throw new ApiException(409, 'MOM_NOT_EDITABLE', "MoM berstatus {$mom->status->value} tidak dapat diubah");
        }

        $this->checkReferences($data, $mom->meeting, $project, $mom);

        return DB::transaction(function () use ($data, $mom) {
            $mom->update($this->attributes($data));
            $this->syncContent($data, $mom->meeting, $mom);

            return $mom;
        });
    }

    private function attributes(array $data): array
    {
        return [
            'meeting_date' => $data['meetingDate'],
            'discussion' => $data['discussion'],
            'decision' => $data['decision'],
            'change_summary' => $data['changeSummary'] ?? null,
        ];
    }

    /** Checks that need F-1 / D-1 or this meeting's data. Failures are VALIDATION_ERROR. */
    private function checkReferences(array $data, Meeting $meeting, Project $project, ?Mom $mom = null): void
    {
        $participantIds = $meeting->participants()->pluck('id')->all();

        if (array_diff($data['attendance'], $participantIds)) {
            throw ValidationException::withMessages(['attendance' => 'Peserta bukan milik rapat ini']);
        }

        $ownItemIds = $mom?->actionItems()->pluck('id')->all() ?? [];

        foreach ($data['actionItems'] as $i => $item) {
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
    private function syncContent(array $data, Meeting $meeting, Mom $mom): void
    {
        $meeting->participants()->update(['attended' => false]);
        $meeting->participants()->whereIn('id', $data['attendance'])->update(['attended' => true]);

        $items = $data['actionItems'];

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
