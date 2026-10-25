<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMomStatusRequest extends FormRequest
{
    /** Role checks happen in the controller via MomPolicy. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:IN_REVIEW,FINAL,REVISION'],
            'reviewNote' => ['required_if:status,REVISION', 'nullable', 'string'],
        ];
    }
}
