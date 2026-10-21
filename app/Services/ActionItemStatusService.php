<?php

namespace App\Services;

use App\Data\Account;
use App\Enums\ActionItemStatus;
use App\Enums\MomStatus;
use App\Exceptions\ApiException;
use App\Models\ActionItem;
use Illuminate\Support\Facades\DB;

/** Action item status changes (PRD §6), forward only. Saves and writes StatusHistory in one transaction. */
class ActionItemStatusService
{
    private const ALLOWED = [
        'TODO' => ['IN_PROGRESS'],
        'IN_PROGRESS' => ['DONE'],
    ];

    public function __construct(private StatusHistoryRecorder $history) {}

    /** The only status the item may move to, or null when it is DONE. */
    public function next(ActionItemStatus $from): ?ActionItemStatus
    {
        $to = self::ALLOWED[$from->value][0] ?? null;

        return $to ? ActionItemStatus::from($to) : null;
    }

    /** Items only exist for the team once their MoM is FINAL. */
    public function assertMomFinal(ActionItem $item): void
    {
        if ($item->mom->status !== MomStatus::FINAL) {
            throw new ApiException(409, 'INVALID_STATUS_TRANSITION', 'Action item hanya dapat diubah setelah MoM berstatus FINAL');
        }
    }

    public function change(ActionItem $item, ActionItemStatus $to, Account $by): ActionItem
    {
        $from = $item->status;

        if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
            throw new ApiException(409, 'INVALID_STATUS_TRANSITION', "Status {$from->value} tidak dapat diubah ke {$to->value}");
        }

        return DB::transaction(function () use ($item, $from, $to, $by) {
            $item->update([
                'status' => $to,
                'completed_at' => $to === ActionItemStatus::DONE ? now() : null,
            ]);
            $this->history->record($item, $from, $to, $by);

            return $item;
        });
    }
}
