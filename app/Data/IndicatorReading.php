<?php

namespace App\Data;

use App\Enums\ProjectIndicator;

/**
 * Result of comparing E-3 target progress with E-4 actual progress for one project.
 * $indicator is null when either side is missing (no target yet, or no tasks).
 */
final class IndicatorReading
{
    public function __construct(
        public readonly string $projectId,
        public readonly ?ProjectIndicator $indicator,
        public readonly ?float $targetProgress,
        public readonly ?float $actualProgress,
        public readonly float $tolerance,
        public readonly string $asOf,
    ) {}
}
