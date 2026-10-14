<?php

namespace App\Http\Requests;

use App\Enums\MeetingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMeetingsRequest extends FormRequest
{
    /** Role and membership checks happen in the controller via ProjectGuard. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(MeetingStatus::class)],
        ];
    }
}
