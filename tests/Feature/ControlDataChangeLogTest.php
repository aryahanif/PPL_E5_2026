<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeSource;
use App\Enums\ChangeType;
use App\Enums\MeetingStatus;
use App\Enums\MomStatus;
use App\Models\ChangeRequest;
use App\Models\Meeting;
use App\Models\Mom;
use App\Services\ControlDataService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** API-K05-09 change-log query, independent of the control-data stub and resources. */
class ControlDataChangeLogTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PROJECT = 'PRJ-2026-016';

    private const MEETING = '11111111-1111-4111-8111-000000000101';

    private const MOM = '22222222-2222-4222-8222-000000000101';

    // September 2026 in WIB, expressed as inclusive UTC boundaries.
    private const START = '2026-08-31 17:00:00';

    private const END = '2026-09-30 16:59:59';

    private const FIRST = '66666666-6666-4666-8666-000000000101';

    private const SECOND = '66666666-6666-4666-8666-000000000102';

    private const THIRD = '66666666-6666-4666-8666-000000000103';

    private const FOURTH = '66666666-6666-4666-8666-000000000104';

    protected function setUp(): void
    {
        parent::setUp();

        Meeting::forceCreate([
            'id' => self::MEETING,
            'project_id' => self::PROJECT,
            'title' => 'Rapat perubahan modul laporan',
            'scheduled_at' => '2026-08-20 02:00:00',
            'status' => MeetingStatus::DONE,
            'created_by' => 'acc-pm-0007',
            'created_at' => '2026-08-01 09:00:00',
            'updated_at' => '2026-08-20 02:00:00',
        ]);

        Mom::forceCreate([
            'id' => self::MOM,
            'meeting_id' => self::MEETING,
            'meeting_date' => '2026-08-20',
            'discussion' => 'Perubahan kebutuhan modul laporan.',
            'decision' => 'Catat perubahan untuk ditindaklanjuti.',
            'status' => MomStatus::FINAL,
            'written_by' => 'acc-tw-0091',
            'reviewed_by' => 'acc-tl-0042',
            'finalized_at' => '2026-08-20 03:00:00',
            'created_at' => '2026-08-20 02:00:00',
            'updated_at' => '2026-08-20 03:00:00',
        ]);
    }

    public function test_recorded_entry_created_in_the_period_is_included(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::RECORDED, '2026-09-15 09:00:00');

        $this->assertSame([self::FIRST], $this->records()->modelKeys());
    }

    public function test_recorded_entries_created_outside_the_period_are_excluded(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::RECORDED, '2026-08-30 09:00:00');
        $this->changeRequest(self::SECOND, ChangeRequestStatus::RECORDED, '2026-10-01 09:00:00');

        $this->assertTrue($this->records()->isEmpty());
    }

    public function test_recorded_entry_created_inside_the_period_is_included_when_updated_outside(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::RECORDED, '2026-09-15 09:00:00', attributes: [
            'updated_at' => '2026-10-01 09:00:00',
        ]);

        $this->assertDatabaseHas('change_requests', [
            'id' => self::FIRST,
            'created_at' => '2026-09-15 09:00:00',
            'updated_at' => '2026-10-01 09:00:00',
            'decided_at' => null,
        ]);
        $this->assertSame([self::FIRST], $this->records()->modelKeys());
    }

    public function test_recorded_entry_created_outside_the_period_is_excluded_when_updated_inside(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::RECORDED, '2026-08-20 09:00:00', attributes: [
            'updated_at' => '2026-09-15 09:00:00',
        ]);
        $this->changeRequest(self::SECOND, ChangeRequestStatus::RECORDED, '2026-09-10 09:00:00');

        $this->assertDatabaseHas('change_requests', [
            'id' => self::FIRST,
            'created_at' => '2026-08-20 09:00:00',
            'updated_at' => '2026-09-15 09:00:00',
            'decided_at' => null,
        ]);
        $this->assertSame([self::SECOND], $this->records()->modelKeys());
    }

    public function test_approved_and_rejected_entries_are_included_by_decided_at(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::APPROVED, '2026-08-20 09:00:00', '2026-09-10 09:00:00');
        $this->changeRequest(self::SECOND, ChangeRequestStatus::REJECTED, '2026-08-21 09:00:00', '2026-09-15 09:00:00');

        $this->assertSame([self::FIRST, self::SECOND], $this->records()->modelKeys());
    }

    #[DataProvider('decisionStatuses')]
    public function test_decisions_outside_the_period_are_excluded_even_when_created_inside(ChangeRequestStatus $status): void
    {
        $this->changeRequest(self::FIRST, $status, '2026-09-10 09:00:00', '2026-08-31 16:59:59');
        $this->changeRequest(self::SECOND, $status, '2026-09-15 09:00:00', '2026-09-30 17:00:00');

        $this->assertTrue($this->records()->isEmpty());
    }

    public function test_approved_and_rejected_entries_without_decided_at_are_excluded(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::APPROVED, '2026-09-10 09:00:00');
        $this->changeRequest(self::SECOND, ChangeRequestStatus::REJECTED, '2026-09-15 09:00:00');

        $this->assertTrue($this->records()->isEmpty());
    }

    public function test_pending_analysis_and_analyzed_entries_are_excluded(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::PENDING_ANALYSIS, '2026-09-10 09:00:00');
        $this->changeRequest(self::SECOND, ChangeRequestStatus::ANALYZED, '2026-09-15 09:00:00');

        $this->assertTrue($this->records()->isEmpty());
    }

    public function test_pending_analysis_and_analyzed_entries_with_decided_at_in_the_period_are_excluded(): void
    {
        // Defensive fixtures intentionally violate the normal lifecycle to prove that
        // the status filter excludes these entries even when decided_at is populated.
        $this->changeRequest(self::FIRST, ChangeRequestStatus::PENDING_ANALYSIS, '2026-09-10 09:00:00', '2026-09-20 09:00:00');
        $this->changeRequest(self::SECOND, ChangeRequestStatus::ANALYZED, '2026-09-11 09:00:00', '2026-09-21 09:00:00');
        $this->changeRequest(self::THIRD, ChangeRequestStatus::APPROVED, '2026-08-20 09:00:00', '2026-09-20 09:00:00');

        $this->assertDatabaseHas('change_requests', [
            'id' => self::FIRST,
            'status' => ChangeRequestStatus::PENDING_ANALYSIS->value,
            'decided_at' => '2026-09-20 09:00:00',
        ]);
        $this->assertDatabaseHas('change_requests', [
            'id' => self::SECOND,
            'status' => ChangeRequestStatus::ANALYZED->value,
            'decided_at' => '2026-09-21 09:00:00',
        ]);
        $this->assertSame([self::THIRD], $this->records()->modelKeys());
    }

    public function test_other_project_entries_are_excluded_from_all_status_branches(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::RECORDED, '2026-09-10 09:00:00');
        $otherProject = [
            'project_id' => 'PRJ-2026-014',
            'mom_id' => '22222222-2222-4222-8222-000000000003',
        ];
        $this->changeRequest(self::SECOND, ChangeRequestStatus::RECORDED, '2026-09-10 09:00:00', attributes: $otherProject);
        $this->changeRequest(self::THIRD, ChangeRequestStatus::APPROVED, '2026-08-20 09:00:00', '2026-09-15 09:00:00', $otherProject);
        $this->changeRequest(self::FOURTH, ChangeRequestStatus::REJECTED, '2026-08-21 09:00:00', '2026-09-15 09:00:00', $otherProject);

        $this->assertSame([self::FIRST], $this->records()->modelKeys());
        $this->assertTrue($this->records('PRJ-2026-015')->isEmpty());
    }

    public function test_results_are_ordered_by_created_at_ascending(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::RECORDED, '2026-09-20 09:00:00');
        $this->changeRequest(self::SECOND, ChangeRequestStatus::REJECTED, '2026-08-01 09:00:00', '2026-09-25 09:00:00');
        $this->changeRequest(self::THIRD, ChangeRequestStatus::APPROVED, '2026-08-10 09:00:00', '2026-09-05 09:00:00');

        $this->assertSame([self::SECOND, self::THIRD, self::FIRST], $this->records()->modelKeys());
    }

    public function test_recorded_period_boundaries_are_inclusive(): void
    {
        $this->changeRequest(self::FIRST, ChangeRequestStatus::RECORDED, self::START);
        $this->changeRequest(self::SECOND, ChangeRequestStatus::RECORDED, self::END);
        $this->changeRequest(self::THIRD, ChangeRequestStatus::RECORDED, '2026-08-31 16:59:59');
        $this->changeRequest(self::FOURTH, ChangeRequestStatus::RECORDED, '2026-09-30 17:00:00');

        $this->assertSame([self::FIRST, self::SECOND], $this->records()->modelKeys());
    }

    #[DataProvider('decisionStatuses')]
    public function test_decision_period_boundaries_are_inclusive(ChangeRequestStatus $status): void
    {
        $this->changeRequest(self::FIRST, $status, '2026-08-20 09:00:00', self::START);
        $this->changeRequest(self::SECOND, $status, '2026-08-21 09:00:00', self::END);
        $this->changeRequest(self::THIRD, $status, '2026-08-22 09:00:00', '2026-08-31 16:59:59');
        $this->changeRequest(self::FOURTH, $status, '2026-08-23 09:00:00', '2026-09-30 17:00:00');

        $this->assertSame([self::FIRST, self::SECOND], $this->records()->modelKeys());
    }

    public function test_period_without_data_returns_an_empty_collection(): void
    {
        $records = $this->records();

        $this->assertInstanceOf(Collection::class, $records);
        $this->assertTrue($records->isEmpty());
    }

    public function test_affected_tasks_are_eager_loaded_for_each_entry(): void
    {
        $first = $this->changeRequest(self::FIRST, ChangeRequestStatus::RECORDED, '2026-09-10 09:00:00');
        $second = $this->changeRequest(self::SECOND, ChangeRequestStatus::APPROVED, '2026-09-11 09:00:00', '2026-09-15 09:00:00');
        $this->changeRequest(self::THIRD, ChangeRequestStatus::REJECTED, '2026-09-12 09:00:00', '2026-09-15 09:00:00');
        $first->affectedTasks()->create([
            'task_id' => 'TSK-016-001',
            'added_by' => 'acc-tl-0042',
            'created_at' => '2026-09-10 09:00:00',
        ]);
        $second->affectedTasks()->create([
            'task_id' => 'TSK-016-002',
            'added_by' => 'acc-tl-0042',
            'created_at' => '2026-09-12 09:00:00',
        ]);

        $records = $this->records();

        $this->assertSame([self::FIRST, self::SECOND, self::THIRD], $records->modelKeys());
        foreach ($records as $record) {
            $this->assertTrue($record->relationLoaded('affectedTasks'));
        }
        $this->assertSame(['TSK-016-001'], $records[0]->affectedTasks->pluck('task_id')->all());
        $this->assertSame(['TSK-016-002'], $records[1]->affectedTasks->pluck('task_id')->all());
        $this->assertTrue($records[2]->affectedTasks->isEmpty());
    }

    public static function decisionStatuses(): array
    {
        return [
            'approved' => [ChangeRequestStatus::APPROVED],
            'rejected' => [ChangeRequestStatus::REJECTED],
        ];
    }

    private function records(string $projectId = self::PROJECT): Collection
    {
        return app(ControlDataService::class)->changeLogForPeriod(
            $projectId,
            CarbonImmutable::parse(self::START, 'UTC'),
            CarbonImmutable::parse(self::END, 'UTC'),
        );
    }

    private function changeRequest(string $id, ChangeRequestStatus $status, string $createdAt, ?string $decidedAt = null, array $attributes = []): ChangeRequest
    {
        return ChangeRequest::forceCreate(array_replace([
            'id' => $id,
            'project_id' => self::PROJECT,
            'mom_id' => self::MOM,
            'change_description' => 'Penyesuaian modul laporan bulanan.',
            'source' => ChangeSource::INTERNAL,
            'change_type' => ChangeType::TASK,
            'is_formal_cr' => $status !== ChangeRequestStatus::RECORDED,
            'status' => $status,
            'decided_at' => $decidedAt,
            'decided_by' => $decidedAt !== null ? 'acc-pm-0007' : null,
            'created_by' => 'acc-pm-0007',
            'created_at' => $createdAt,
            'updated_at' => $decidedAt ?? $createdAt,
        ], $attributes));
    }
}
