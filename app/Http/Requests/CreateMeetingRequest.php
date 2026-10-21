<?php

namespace App\Http\Requests;

use App\Enums\ParticipantType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMeetingRequest extends FormRequest
{
    /** Role and membership checks happen in the controller via ProjectGuard and MeetingPolicy. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'scheduledAt' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:200'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.participantType' => ['required', Rule::enum(ParticipantType::class)],
            'participants.*.accountId' => ['required_if:participants.*.participantType,INTERNAL', 'nullable', 'string', 'max:64', 'distinct'],
            'participants.*.displayName' => ['required_if:participants.*.participantType,CLIENT', 'nullable', 'string', 'max:150'],
        ];
    }
}
