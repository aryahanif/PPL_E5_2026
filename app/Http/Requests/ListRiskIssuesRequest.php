<?php

namespace App\Http\Requests;

use App\Enums\ImpactLevel;
use App\Enums\RiskIssueStatus;
use App\Enums\RiskIssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRiskIssuesRequest extends FormRequest
{
    /** Membership checks happen in the controller via ProjectGuard. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(RiskIssueType::class)],
            'status' => ['sometimes', Rule::enum(RiskIssueStatus::class)],
            'impactLevel' => ['sometimes', Rule::enum(ImpactLevel::class)],
        ];
    }
}
