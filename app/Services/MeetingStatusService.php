<?php

namespace App\Services;

use App\Data\Account;
use App\Enums\MeetingStatus;
use App\Exceptions\ApiException;
use App\Models\Meeting;
use Illuminate\Support\Facades\DB;

/** Meeting status changes (PRD §6). Saves and writes StatusHistory in one transaction. */
class MeetingStatusService
{
    private const ALLOWED = [
        'SCHEDULED' => ['DONE', 'CANCELLED'],
    ];

    public function __construct(private StatusHistoryRecorder $history) {}

    public function change(Meeting $meeting, MeetingStatus $to, Account $by, ?string $note = null): Meeting
    {
        $from = $meeting->status;

        if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
            throw new ApiException(409, 'INVALID_STATUS_TRANSITION', "Status {$from->value} tidak dapat diubah ke {$to->value}");
        }

        return DB::transaction(function () use ($meeting, $from, $to, $by, $note) {
            $meeting->update(['status' => $to]);
            $this->history->record($meeting, $from, $to, $by, $note);

            return $meeting;
        });
    }
}
