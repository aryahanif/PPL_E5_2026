<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActionItemStatusRequest extends FormRequest
{
    /** Only the PIC may update; checked in the controller via ActionItemPolicy. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:IN_PROGRESS,DONE'],
        ];
    }
}
