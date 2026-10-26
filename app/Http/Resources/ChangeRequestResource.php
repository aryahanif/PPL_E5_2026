<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'changeRequestId' => $this->id,
            'projectId' => $this->project_id,
            'momId' => $this->mom_id,
            'changeDescription' => $this->change_description,
            'source' => $this->source->value,
            'changeType' => $this->change_type->value,
            'isFormalCr' => $this->is_formal_cr,
            'baselineItemId' => $this->baseline_item_id,
            'impact' => $this->impact,
            'affectedTaskIds' => $this->whenLoaded('affectedTasks', fn () => $this->affectedTasks->pluck('task_id')->values()),
            'analyzedBy' => $this->analyzed_by,
            'status' => $this->status->value,
            'decisionNote' => $this->decision_note,
            'decidedBy' => $this->decided_by,
            'decidedAt' => $this->decided_at?->toIso8601ZuluString(),
            'createdBy' => $this->created_by,
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}