<?php

namespace App\Http\Requests;

use App\Enums\ChangeRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListChangeLogRequest extends FormRequest
{
    /** Membership checks happen in the controller via ProjectGuard. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(ChangeRequestStatus::class)],
        ];
    }
}
