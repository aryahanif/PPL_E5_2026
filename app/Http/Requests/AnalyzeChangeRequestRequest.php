<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'impact' => ['required', 'string'],
            'baselineItemId' => ['nullable', 'string', 'max:64'],
            'affectedTaskIds' => ['present', 'array'],
            'affectedTaskIds.*' => ['required', 'string', 'max:64', 'distinct'],
        ];
    }
}