<?php

namespace App\Services;

use App\Contracts\BaselineDirectory;
use App\Contracts\TaskDirectory;
use App\Data\IndicatorReading;
use App\Enums\ProjectIndicator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * BL-12: ON_TRACK / DELAYED indicator. Nothing is stored (Skema Data Awal §catatan indikator).
 *
 * Rule (assumption until Q-10 is answered): DELAYED when actual progress (E-4, weighted
 * average) is lower than target progress (E-3, cumulative on today's date in WIB) by more
 * than `e5.indicator.tolerance` percentage points. Otherwise ON_TRACK.
 * No target yet or no tasks yet: the indicator is null, never guessed.
 */
class ProjectIndicatorCalculator
{
    public function __construct(
        private BaselineDirectory $baselines,
        private TaskDirectory $tasks,
    ) {}

    public function forProject(string $projectId, ?CarbonInterface $on = null): IndicatorReading
    {
        $on ??= Carbon::today(config('e5.display_timezone'));
        $tolerance = (float) config('e5.indicator.tolerance');

        $target = $this->baselines->targetProgress($projectId, $on);
        $actual = $this->tasks->actualProgress($projectId);

        $indicator = null;

        if ($target !== null && $actual !== null) {
            $indicator = $actual < $target - $tolerance
                ? ProjectIndicator::DELAYED
                : ProjectIndicator::ON_TRACK;
        }

        return new IndicatorReading(
            $projectId,
            $indicator,
            $target === null ? null : round($target, 1),
            $actual === null ? null : round($actual, 1),
            $tolerance,
            $on->toDateString(),
        );
    }
}
