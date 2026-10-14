<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema "Meeting" in openapi.yaml. */
class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'meetingId' => $this->id,
            'projectId' => $this->project_id,
            'title' => $this->title,
            'scheduledAt' => $this->scheduled_at->toIso8601ZuluString(),
            'location' => $this->location,
            'status' => $this->status->value,
            'participants' => ParticipantResource::collection($this->whenLoaded('participants')),
            'momId' => $this->mom?->id,
            'momStatus' => $this->mom?->status->value,
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
