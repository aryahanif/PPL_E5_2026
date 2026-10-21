<?php

namespace App\Http\Requests;

use App\Enums\ActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListActionItemsRequest extends FormRequest
{
    /** Membership checks happen in the controller via ProjectGuard. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 'boolean' rejects the string "true", which clients send in query strings.
        return [
            'projectId' => ['sometimes', 'string'],
            'mine' => ['sometimes', 'in:true,false,1,0'],
            'overdue' => ['sometimes', 'in:true,false,1,0'],
            'status' => ['sometimes', Rule::enum(ActionItemStatus::class)],
        ];
    }
}
