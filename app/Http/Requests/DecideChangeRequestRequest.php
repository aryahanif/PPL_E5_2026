<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:APPROVED,REJECTED'],
            'decisionNote' => ['required', 'string'],
        ];
    }
}
