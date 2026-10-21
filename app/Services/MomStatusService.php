<?php

namespace App\Services;

use App\Data\Account;
use App\Enums\MomStatus;
use App\Exceptions\ApiException;
use App\Models\Mom;
use Illuminate\Support\Facades\DB;

/** MoM status changes (PRD §6). Saves and writes StatusHistory in one transaction. */
class MomStatusService
{
    private const ALLOWED = [
        'DRAFT' => ['IN_REVIEW'],
        'IN_REVIEW' => ['FINAL', 'REVISION'],
        'REVISION' => ['IN_REVIEW'],
    ];

    public function __construct(private StatusHistoryRecorder $history) {}

    public function change(Mom $mom, MomStatus $to, Account $by, ?string $note = null): Mom
    {
        $from = $mom->status;

        if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
            throw new ApiException(409, 'INVALID_STATUS_TRANSITION', "Status {$from->value} tidak dapat diubah ke {$to->value}");
        }

        return DB::transaction(function () use ($mom, $from, $to, $by, $note) {
            $changes = ['status' => $to];

            if ($to === MomStatus::FINAL) {
                $changes += ['finalized_at' => now(), 'reviewed_by' => $by->accountId];
            }

            if ($to === MomStatus::REVISION) {
                $changes['review_note'] = $note;
            }

            $mom->update($changes);
            $this->history->record($mom, $from, $to, $by, $note);

            return $mom;
        });
    }
}
