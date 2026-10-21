<?php

namespace App\Contracts;

use App\Data\BaselineItem;
use Carbon\CarbonInterface;

/** Baseline items and cumulative progress targets from E-3 (PRD §9). */
interface BaselineDirectory
{
    public function find(string $baselineItemId): ?BaselineItem;

    /** Cumulative target progress (0-100) on $date, or null if the project has no target yet. */
    public function targetProgress(string $projectId, CarbonInterface $date): ?float;
}
