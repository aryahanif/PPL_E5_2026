<?php

namespace App\Services;

use App\Data\Account;
use App\Models\StatusHistory;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes one row to status_histories (ENT-K05-08). Call it inside the same
 * DB transaction as the status change:
 *
 *   DB::transaction(function () use ($mom, $account) {
 *       $from = $mom->status;
 *       $mom->update(['status' => MomStatus::FINAL]);
 *       $this->history->record($mom, $from, MomStatus::FINAL, $account);
 *   });
 */
class StatusHistoryRecorder
{
    public function record(
        Model $entity,
        BackedEnum|string|null $from,
        BackedEnum|string $to,
        Account $by,
        ?string $note = null,
    ): StatusHistory {
        return StatusHistory::create([
            'entity_type' => $entity::HISTORY_TYPE,
            'entity_id' => $entity->getKey(),
            'from_status' => $from instanceof BackedEnum ? $from->value : $from,
            'to_status' => $to instanceof BackedEnum ? $to->value : $to,
            'changed_by' => $by->accountId,
            'changed_at' => now(),
            'note' => $note,
        ]);
    }
}
