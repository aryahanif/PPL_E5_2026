<?php

namespace App\Http\Requests;

use App\Enums\ChangeSource;
use App\Enums\ChangeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'momId' => ['required', 'uuid'],
            'changeDescription' => ['required', 'string'],
            'source' => ['required', Rule::enum(ChangeSource::class)],
            'changeType' => ['required', Rule::enum(ChangeType::class)],
            'isFormalCr' => ['required', 'boolean'],
        ];
    }
}