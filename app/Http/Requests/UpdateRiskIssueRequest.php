<?php

namespace App\Http\Requests;

use App\Enums\ImpactLevel;
use App\Enums\RiskIssueStatus;
use App\Enums\RiskIssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Body of updateRiskIssue (schema RiskIssueUpdate). Only the fields sent are changed. */
class UpdateRiskIssueRequest extends FormRequest
{
    /** Which fields a TL may send is checked in the controller via RiskIssuePolicy. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::enum(RiskIssueType::class)],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'required', 'string'],
            'impactLevel' => ['sometimes', 'nullable', Rule::enum(ImpactLevel::class)],
            'mitigation' => ['sometimes', 'nullable', 'string'],
            'picAccountId' => ['sometimes', 'required', 'string', 'max:64'],
            'sourceMomId' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'required', Rule::enum(RiskIssueStatus::class)],
            'resolutionNote' => ['required_if:status,CLOSED', 'nullable', 'string'],
        ];
    }

    /** At least one known field must be sent (minProperties: 1). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! array_intersect_key($this->all(), $this->rules())) {
                    $validator->errors()->add('body', 'Tidak ada field yang diubah');
                }
            },
        ];
    }

    /** @return list<string> camelCase names of the fields in the body, for RiskIssuePolicy::update. */
    public function fields(): array
    {
        return array_keys($this->validated());
    }
}
