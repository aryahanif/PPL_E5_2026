<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema "ProjectIndicator" in openapi.yaml. Used by API-K05-08. Wraps App\Data\IndicatorReading. */
class ProjectIndicatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'projectId' => $this->projectId,
            'indicator' => $this->indicator?->value,
            'targetProgress' => $this->targetProgress,
            'actualProgress' => $this->actualProgress,
            'tolerance' => $this->tolerance,
            'asOf' => $this->asOf,
        ];
    }
}
