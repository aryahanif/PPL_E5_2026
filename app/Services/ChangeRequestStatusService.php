<?php

namespace App\Services;

use App\Contracts\BaselineDirectory;
use App\Contracts\TaskDirectory;
use App\Data\Account;
use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeType;
use App\Exceptions\ApiException;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeRequestStatusService
{
    private const ALLOWED = [
        'PENDING_ANALYSIS' => ['ANALYZED'],
        'ANALYZED' => ['APPROVED', 'REJECTED'],
    ];

    public function __construct(
        private StatusHistoryRecorder $history,
        private BaselineDirectory $baselines,
        private TaskDirectory $tasks,
    ) {}

    public function record(array $data, string $projectId, Account $by): ChangeRequest
    {
        $status = $data['isFormalCr']
            ? ChangeRequestStatus::PENDING_ANALYSIS
            : ChangeRequestStatus::RECORDED;

        return DB::transaction(function () use ($data, $projectId, $by, $status) {
            $changeRequest = ChangeRequest::create([
                'project_id' => $projectId,
                'mom_id' => $data['momId'],
                'change_description' => $data['changeDescription'],
                'source' => $data['source'],
                'change_type' => $data['changeType'],
                'is_formal_cr' => $data['isFormalCr'],
                'status' => $status,
                'created_by' => $by->accountId,
            ]);

            $this->history->record($changeRequest, null, $status, $by);

            return $changeRequest;
        });
    }

    public function decide(ChangeRequest $changeRequest, ChangeRequestStatus $to, string $note, Account $by): ChangeRequest
    {
        $from = $changeRequest->status;

        if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
            throw new ApiException(409, 'INVALID_STATUS_TRANSITION', "Status {$from->value} tidak dapat diubah ke {$to->value}");
        }

        return DB::transaction(function () use ($changeRequest, $from, $to, $note, $by) {
            $changeRequest->update([
                'status' => $to,
                'decision_note' => $note,
                'decided_by' => $by->accountId,
                'decided_at' => now(),
            ]);

            $this->history->record($changeRequest, $from, $to, $by, $note);

            return $changeRequest->load('affectedTasks');
        });
    }

    public function analyze(ChangeRequest $changeRequest, array $data, Account $by): ChangeRequest
    {
        $from = $changeRequest->status;
        $to = ChangeRequestStatus::ANALYZED;

        if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
            throw new ApiException(409, 'INVALID_STATUS_TRANSITION', "Status {$from->value} tidak dapat diubah ke {$to->value}");
        }

        $type = $changeRequest->change_type;
        $baselineItemId = $data['baselineItemId'] ?? null;
        $taskIds = $data['affectedTaskIds'] ?? [];

        if (in_array($type, [ChangeType::SCOPE, ChangeType::TIMELINE], true) && ! $baselineItemId) {
            throw ValidationException::withMessages(['baselineItemId' => 'Baseline wajib untuk perubahan SCOPE atau TIMELINE']);
        }

        if ($baselineItemId) {
            $baseline = $this->baselines->find($baselineItemId);

            if (! $baseline || $baseline->projectId !== $changeRequest->project_id) {
                throw ValidationException::withMessages(['baselineItemId' => 'Baseline tidak ditemukan untuk proyek ini']);
            }
        }

        foreach ($taskIds as $index => $taskId) {
            $task = $this->tasks->find($taskId);

            if (! $task || $task->projectId !== $changeRequest->project_id) {
                throw ValidationException::withMessages(["affectedTaskIds.{$index}" => 'Task tidak ditemukan untuk proyek ini']);
            }
        }

        return DB::transaction(function () use ($changeRequest, $data, $by, $from, $to, $baselineItemId, $taskIds) {
            $changeRequest->update([
                'impact' => $data['impact'],
                'baseline_item_id' => $baselineItemId,
                'analyzed_by' => $by->accountId,
                'status' => $to,
            ]);

            ChangeRequestTask::where('change_request_id', $changeRequest->id)->delete();
            foreach ($taskIds as $taskId) {
                ChangeRequestTask::create([
                    'change_request_id' => $changeRequest->id,
                    'task_id' => $taskId,
                    'added_by' => $by->accountId,
                    'created_at' => now(),
                ]);
            }

            $this->history->record($changeRequest, $from, $to, $by);

            return $changeRequest->load('affectedTasks');
        });
    }
}