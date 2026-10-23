<?php

namespace App\Services;

use App\Data\Account;
use App\Enums\RiskIssueStatus;
use App\Exceptions\ApiException;
use App\Models\RiskIssue;
use Illuminate\Support\Facades\DB;

/**
 * Risk/issue status and PIC changes (BL-11). Saves and writes StatusHistory in one transaction.
 * OPEN → CLOSED is an assumption pending Q-09.
 */
class RiskIssueStatusService
{
    private const ALLOWED = [
        'OPEN' => ['IN_PROGRESS', 'CLOSED'],
        'IN_PROGRESS' => ['CLOSED'],
    ];

    public function __construct(private StatusHistoryRecorder $history) {}

    /** Sending the current status is not a change. resolutionNote is required for CLOSED (UpdateRiskIssueRequest). */
    public function change(RiskIssue $item, RiskIssueStatus $to, Account $by, ?string $resolutionNote = null): RiskIssue
    {
        $from = $item->status;

        if ($to === $from) {
            return $item;
        }

        if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
            throw new ApiException(409, 'INVALID_STATUS_TRANSITION', "Status {$from->value} tidak dapat diubah ke {$to->value}");
        }

        return DB::transaction(function () use ($item, $from, $to, $by, $resolutionNote) {
            $changes = ['status' => $to];

            if ($to === RiskIssueStatus::CLOSED) {
                $changes += ['closed_at' => now(), 'resolution_note' => $resolutionNote];
            }

            $item->update($changes);
            $this->history->record($item, $from, $to, $by, $resolutionNote);

            return $item;
        });
    }

    /** A PIC change keeps the status, so it is recorded as a history row from and to the same status. */
    public function changePic(RiskIssue $item, string $picAccountId, Account $by): RiskIssue
    {
        if ($item->pic_account_id === $picAccountId) {
            return $item;
        }

        return DB::transaction(function () use ($item, $picAccountId, $by) {
            $item->update(['pic_account_id' => $picAccountId]);
            $this->history->record($item, $item->status, $item->status, $by, "PIC diubah ke {$picAccountId}");

            return $item;
        });
    }
}
