<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Schema "ActionItem" in openapi.yaml. Used by API-K05-02 (inside a MoM)
 * and API-K05-04 (action item list and status update).
 * Eager-load to avoid extra queries: ->with('mom.meeting').
 */
class ActionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'actionItemId' => $this->id,
            'momId' => $this->mom_id,
            'projectId' => $this->mom->meeting->project_id,
            'description' => $this->description,
            'picAccountId' => $this->pic_account_id,
            'dueDate' => $this->due_date->toDateString(),
            'status' => $this->status->value,
            'overdue' => $this->isOverdue(),
            'completedAt' => $this->completed_at?->toIso8601ZuluString(),
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
