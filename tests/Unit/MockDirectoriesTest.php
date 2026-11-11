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

        $this->assertSame('SCOPE', $dir->find('019a0000-e301-7000-8000-000000014001')->type);
        $this->assertSame('019a0000-d101-7000-8000-000000000016', $dir->find('019a0000-e301-7000-8000-000000016002')->projectId);
        $this->assertNull($dir->find('019a0000-e301-7000-8000-000000999099'));
    }

    public function test_target_progress_uses_latest_date_not_after_given_date(): void
    {
        $dir = app(BaselineDirectory::class);

        $this->assertNull($dir->targetProgress('019a0000-d101-7000-8000-000000000014', CarbonImmutable::parse('2026-07-01')));
        $this->assertSame(30.0, $dir->targetProgress('019a0000-d101-7000-8000-000000000014', CarbonImmutable::parse('2026-09-10')));
        $this->assertSame(45.0, $dir->targetProgress('019a0000-d101-7000-8000-000000000014', CarbonImmutable::parse('2027-01-01')));
        $this->assertSame(60.0, $dir->targetProgress('019a0000-d101-7000-8000-000000000016', CarbonImmutable::parse('2027-01-01')));
        $this->assertNull($dir->targetProgress('019a0000-d101-7000-8000-000000000999', CarbonImmutable::parse('2027-01-01')));
    }

    public function test_task_find_and_for_project(): void
    {
        $dir = app(TaskDirectory::class);

        $this->assertSame(2.0, $dir->find('019a0000-e401-7000-8000-000000014001')->weight);
        $this->assertNull($dir->find('019a0000-e401-7000-8000-000000999999'));
        $this->assertCount(3, $dir->forProject('019a0000-d101-7000-8000-000000000016'));
        $this->assertSame([], $dir->forProject('019a0000-d101-7000-8000-000000000999'));
    }

    public function test_014_is_on_track_and_016_is_delayed(): void
    {
        $tasks = app(TaskDirectory::class);
        $baselines = app(BaselineDirectory::class);
        $today = CarbonImmutable::now();

        $this->assertSame(48.0, $tasks->actualProgress('019a0000-d101-7000-8000-000000000014'));
        $this->assertGreaterThanOrEqual($baselines->targetProgress('019a0000-d101-7000-8000-000000000014', $today), $tasks->actualProgress('019a0000-d101-7000-8000-000000000014'));

        $this->assertSame(40.0, $tasks->actualProgress('019a0000-d101-7000-8000-000000000016'));
        $this->assertLessThan($baselines->targetProgress('019a0000-d101-7000-8000-000000000016', $today), $tasks->actualProgress('019a0000-d101-7000-8000-000000000016'));

        $this->assertNull($tasks->actualProgress('019a0000-d101-7000-8000-000000000999'));
    }
}
