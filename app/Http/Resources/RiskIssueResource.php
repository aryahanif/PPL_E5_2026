<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema "RiskIssue" in openapi.yaml. Used by API-K05-07. */
class RiskIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'riskIssueId' => $this->id,
            'projectId' => $this->project_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'impactLevel' => $this->impact_level?->value,
            'mitigation' => $this->mitigation,
            'status' => $this->status->value,
            'picAccountId' => $this->pic_account_id,
            'sourceMomId' => $this->source_mom_id,
            'resolutionNote' => $this->resolution_note,
            'reportedBy' => $this->reported_by,
            'closedAt' => $this->closed_at?->toIso8601ZuluString(),
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
