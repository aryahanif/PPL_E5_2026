<?php

namespace Tests\Feature;

use App\Contracts\BaselineDirectory;
use App\Contracts\TaskDirectory;
use App\Data\BaselineItem;
use App\Data\Task;
use App\Enums\ProjectIndicator;
use App\Services\ProjectIndicatorCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Tests\TestCase;

/** BL-12: the ON_TRACK / DELAYED rule, with the fixture data and with stubs for edge cases. */
class ProjectIndicatorCalculatorTest extends TestCase
{
    private function calculator(): ProjectIndicatorCalculator
    {
        return app(ProjectIndicatorCalculator::class);
    }

    /** Replace both adapters with fixed numbers (null = not available). */
    private function stub(?float $target, ?float $actual): void
    {
        $this->app->instance(BaselineDirectory::class, new class($target) implements BaselineDirectory
        {
            public function __construct(private ?float $target) {}

            public function find(string $baselineItemId): ?BaselineItem
            {
                return null;
            }

            public function targetProgress(string $projectId, CarbonInterface $date): ?float
            {
                return $this->target;
            }
        });

        $this->app->instance(TaskDirectory::class, new class($actual) implements TaskDirectory
        {
            public function __construct(private ?float $actual) {}

            public function find(string $taskId): ?Task
            {
                return null;
            }

            public function forProject(string $projectId): array
            {
                return [];
            }

            public function actualProgress(string $projectId): ?float
            {
                return $this->actual;
            }
        });
    }

    public function test_014_is_on_track_and_016_is_delayed_with_fixtures(): void
    {
        $on = CarbonImmutable::parse('2026-09-20');

        $ok = $this->calculator()->forProject('PRJ-2026-014', $on);
        $this->assertSame(ProjectIndicator::ON_TRACK, $ok->indicator);
        $this->assertSame(45.0, $ok->targetProgress);
        $this->assertSame(48.0, $ok->actualProgress);
        $this->assertSame('2026-09-20', $ok->asOf);

        $late = $this->calculator()->forProject('PRJ-2026-016', $on);
        $this->assertSame(ProjectIndicator::DELAYED, $late->indicator);
        $this->assertSame(60.0, $late->targetProgress);
        $this->assertSame(40.0, $late->actualProgress);
    }

    public function test_target_follows_the_date(): void
    {
        // On 2026-09-05 the 014 target is 30 and 016 is 50: 016 is still behind (40 < 50).
        $this->assertSame(30.0, $this->calculator()->forProject('PRJ-2026-014', CarbonImmutable::parse('2026-09-05'))->targetProgress);
        $this->assertSame(ProjectIndicator::DELAYED, $this->calculator()->forProject('PRJ-2026-016', CarbonImmutable::parse('2026-09-05'))->indicator);
    }

    public function test_actual_equal_to_target_is_on_track(): void
    {
        $this->stub(50.0, 50.0);

        $this->assertSame(ProjectIndicator::ON_TRACK, $this->calculator()->forProject('X')->indicator);
    }

    public function test_actual_just_below_target_is_delayed_with_zero_tolerance(): void
    {
        $this->stub(50.0, 49.9);

        $this->assertSame(ProjectIndicator::DELAYED, $this->calculator()->forProject('X')->indicator);
    }

    public function test_tolerance_is_inclusive(): void
    {
        config(['e5.indicator.tolerance' => 5.0]);

        $this->stub(50.0, 45.0); // exactly 5 points behind
        $this->assertSame(ProjectIndicator::ON_TRACK, $this->calculator()->forProject('X')->indicator);

        $this->stub(50.0, 44.9); // just over the tolerance
        $this->assertSame(ProjectIndicator::DELAYED, $this->calculator()->forProject('X')->indicator);
        $this->assertSame(5.0, $this->calculator()->forProject('X')->tolerance);
    }

    public function test_ahead_of_target_is_on_track(): void
    {
        $this->stub(10.0, 90.0);

        $this->assertSame(ProjectIndicator::ON_TRACK, $this->calculator()->forProject('X')->indicator);
    }

    public function test_no_target_yet_gives_null_indicator(): void
    {
        // Before the first E-3 target date (2026-08-01) there is nothing to compare with.
        $reading = $this->calculator()->forProject('PRJ-2026-014', CarbonImmutable::parse('2026-07-01'));

        $this->assertNull($reading->indicator);
        $this->assertNull($reading->targetProgress);
        $this->assertSame(48.0, $reading->actualProgress);
    }

    public function test_no_tasks_gives_null_indicator(): void
    {
        $this->stub(50.0, null);

        $reading = $this->calculator()->forProject('X');

        $this->assertNull($reading->indicator);
        $this->assertSame(50.0, $reading->targetProgress);
        $this->assertNull($reading->actualProgress);
    }
}
