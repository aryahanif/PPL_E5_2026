<?php

namespace App\Services;

use App\Enums\RiskIssueStatus;
use App\Models\RiskIssue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Raw project control data queries for API-K05-09. */
class ControlDataService
{
    /** @return Collection<int, RiskIssue> */
    public function riskIssuesForPeriod(string $projectId, CarbonImmutable $startUtc, CarbonImmutable $endUtc): Collection
    {
        return RiskIssue::query()
            ->where('project_id', $projectId)
            ->where(fn (Builder $query) => $query
                ->where('status', '!=', RiskIssueStatus::CLOSED)
                ->orWhereBetween('updated_at', [$startUtc, $endUtc]))
            ->orderBy('created_at')
            ->get();
    }
}
