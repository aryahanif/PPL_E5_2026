<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema "ChangeLogRecord" in openapi.yaml. Used by API-K05-09. */
class ChangeLogRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'changeRequestId' => $this->id,
            'changeDescription' => $this->change_description,
            'source' => $this->source->value,
            'changeType' => $this->change_type->value,
            'isFormalCr' => $this->is_formal_cr,
            'impact' => $this->impact,
            'status' => $this->status->value,
            'decisionNote' => $this->decision_note,
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'decidedAt' => $this->decided_at?->toIso8601ZuluString(),
            'baselineItemId' => $this->baseline_item_id,
            'affectedTaskIds' => $this->whenLoaded('affectedTasks', fn () => $this->affectedTasks->pluck('task_id')->values()->all(), []),
        ];
    }
}
