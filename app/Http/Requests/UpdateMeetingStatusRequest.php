<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingStatusRequest extends FormRequest
{
    /** Role checks happen in the controller via MeetingPolicy. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:DONE,CANCELLED'],
        ];
    }
}
