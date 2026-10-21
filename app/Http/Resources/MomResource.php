<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Schema "Mom" in openapi.yaml. Used by API-K05-02 and API-K05-03.
 * Eager-load to avoid extra queries: $mom->load(['meeting', 'actionItems.mom.meeting']).
 */
class MomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'momId' => $this->id,
            'meetingId' => $this->meeting_id,
            'projectId' => $this->meeting->project_id,
            'meetingDate' => $this->meeting_date->toDateString(),
            'discussion' => $this->discussion,
            'decision' => $this->decision,
            'changeSummary' => $this->change_summary,
            'status' => $this->status->value,
            'writtenBy' => $this->written_by,
            'reviewedBy' => $this->reviewed_by,
            'reviewNote' => $this->review_note,
            'finalizedAt' => $this->finalized_at?->toIso8601ZuluString(),
            'actionItems' => ActionItemResource::collection($this->actionItems),
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
