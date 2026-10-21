<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Body of createMom and updateMom (schema MomInput). */
class SaveMomRequest extends FormRequest
{
    /** Role and membership checks happen in the controller via ProjectGuard and MomPolicy. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meetingDate' => ['required', 'date_format:Y-m-d'],
            'discussion' => ['required', 'string'],
            'decision' => ['required', 'string'],
            'changeSummary' => ['nullable', 'string'],
            'attendance' => ['present', 'array'],
            'attendance.*' => ['uuid', 'distinct'],
            'actionItems' => ['present', 'array'],
            'actionItems.*.actionItemId' => ['nullable', 'uuid', 'distinct'],
            'actionItems.*.description' => ['required', 'string'],
            'actionItems.*.picAccountId' => ['required', 'string', 'max:64'],
            'actionItems.*.dueDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:meetingDate'],
        ];
    }
}
