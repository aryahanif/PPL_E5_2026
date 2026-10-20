<?php

namespace Tests\Unit;

use App\Contracts\BaselineDirectory;
use App\Contracts\TaskDirectory;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MockDirectoriesTest extends TestCase
{
    public function test_baseline_find_known_and_unknown(): void
    {
        $dir = app(BaselineDirectory::class);

        $this->assertSame('SCOPE', $dir->find('BSL-014-01')->type);
        $this->assertSame('PRJ-2026-016', $dir->find('BSL-016-02')->projectId);
        $this->assertNull($dir->find('BSL-999-99'));
    }

    public function test_target_progress_uses_latest_date_not_after_given_date(): void
    {
        $dir = app(BaselineDirectory::class);

        $this->assertNull($dir->targetProgress('PRJ-2026-014', CarbonImmutable::parse('2026-07-01')));
        $this->assertSame(30.0, $dir->targetProgress('PRJ-2026-014', CarbonImmutable::parse('2026-09-10')));
        $this->assertSame(45.0, $dir->targetProgress('PRJ-2026-014', CarbonImmutable::parse('2027-01-01')));
        $this->assertSame(60.0, $dir->targetProgress('PRJ-2026-016', CarbonImmutable::parse('2027-01-01')));
        $this->assertNull($dir->targetProgress('PRJ-2026-999', CarbonImmutable::parse('2027-01-01')));
    }

    public function test_task_find_and_for_project(): void
    {
        $dir = app(TaskDirectory::class);

        $this->assertSame(2.0, $dir->find('TSK-014-001')->weight);
        $this->assertNull($dir->find('TSK-999-999'));
        $this->assertCount(3, $dir->forProject('PRJ-2026-016'));
        $this->assertSame([], $dir->forProject('PRJ-2026-999'));
    }

    public function test_014_is_on_track_and_016_is_delayed(): void
    {
        $tasks = app(TaskDirectory::class);
        $baselines = app(BaselineDirectory::class);
        $today = CarbonImmutable::now();

        $this->assertSame(48.0, $tasks->actualProgress('PRJ-2026-014'));
        $this->assertGreaterThanOrEqual($baselines->targetProgress('PRJ-2026-014', $today), $tasks->actualProgress('PRJ-2026-014'));

        $this->assertSame(40.0, $tasks->actualProgress('PRJ-2026-016'));
        $this->assertLessThan($baselines->targetProgress('PRJ-2026-016', $today), $tasks->actualProgress('PRJ-2026-016'));

        $this->assertNull($tasks->actualProgress('PRJ-2026-999'));
    }
}
