<?php

namespace App\Http\Requests;

use App\Enums\ImpactLevel;
use App\Enums\RiskIssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Body of createRiskIssue (schema RiskIssueCreate). */
class CreateRiskIssueRequest extends FormRequest
{
    /** Role and membership checks happen in the controller via ProjectGuard and RiskIssuePolicy. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(RiskIssueType::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'impactLevel' => ['nullable', Rule::enum(ImpactLevel::class)],
            'mitigation' => ['nullable', 'string'],
            'picAccountId' => ['required', 'string', 'max:64'],
            'sourceMomId' => ['nullable', 'uuid'],
        ];
    }
}
