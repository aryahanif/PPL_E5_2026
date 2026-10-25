<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema "RiskIssueRecord" in openapi.yaml. Used by API-K05-09. */
class RiskIssueRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'riskIssueId' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'impactLevel' => $this->impact_level?->value,
            'status' => $this->status->value,
            'picAccountId' => $this->pic_account_id,
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'closedAt' => $this->closed_at?->toIso8601ZuluString(),
            'resolutionNote' => $this->resolution_note,
        ];
    }
}
