<?php

namespace App\Http\Resources;

use App\Contracts\IdentityProvider;
use App\Enums\ParticipantType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema "Participant" in openapi.yaml. Internal names are looked up in F-1, never stored. */
class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayName = $this->participant_type === ParticipantType::INTERNAL
            ? (app(IdentityProvider::class)->find($this->account_id)?->displayName ?? $this->account_id)
            : $this->display_name;

        return [
            'participantId' => $this->id,
            'participantType' => $this->participant_type->value,
            'accountId' => $this->account_id,
            'displayName' => $displayName,
            'attended' => $this->attended,
        ];
    }
}
