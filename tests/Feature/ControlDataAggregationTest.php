<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeSource;
use App\Enums\ChangeType;
use App\Enums\MeetingStatus;
use App\Enums\MomStatus;
use App\Enums\RiskIssueStatus;
use App\Enums\RiskIssueType;
use App\Models\ChangeRequest;
use App\Models\Meeting;
use App\Models\Mom;
use App\Models\RiskIssue;
use App\Services\ControlDataService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-09 aggregation of the existing risk/issue and change-log queries. */
class ControlDataAggregationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PROJECT = 'PRJ-2026-016';

    private const MEETING = '11111111-1111-4111-8111-000000000201';

    private const MOM = '22222222-2222-4222-8222-000000000201';

    // September 2026 in WIB, expressed as inclusive UTC boundaries.
    private const START = '2026-08-31 17:00:00';

    private const END = '2026-09-30 16:59:59';

    private const RISK_FIRST = '55555555-5555-4555-8555-000000000201';

    private const RISK_SECOND = '55555555-5555-4555-8555-000000000202';

    private const RISK_THIRD = '55555555-5555-4555-8555-000000000203';

    private const CHANGE_FIRST = '66666666-6666-4666-8666-000000000201';

    private const CHANGE_SECOND = '66666666-6666-4666-8666-000000000202';

    private const CHANGE_THIRD = '66666666-6666-4666-8666-000000000203';

    protected function setUp(): void
    {
        parent::setUp();

        Meeting::forceCreate([
            'id' => self::MEETING,
            'project_id' => self::PROJECT,
            'title' => 'Rapat pengendalian proyek',
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
            'discussion' => 'Risiko dan perubahan kebutuhan modul laporan.',
            'decision' => 'Catat risiko dan perubahan untuk ditindaklanjuti.',
            'status' => MomStatus::FINAL,
            'written_by' => 'acc-tw-0091',
            'reviewed_by' => 'acc-tl-0042',
            'finalized_at' => '2026-08-20 03:00:00',
            'created_at' => '2026-08-20 02:00:00',
            'updated_at' => '2026-08-20 03:00:00',
        ]);
    }

    public function test_returns_exactly_two_keys_with_matching_model_collections(): void
    {
        $this->riskIssue(self::RISK_FIRST);
        $this->changeRequest(self::CHANGE_FIRST);

        $result = $this->controlData();

        $this->assertSame(['riskIssues', 'changeLog'], array_keys($result));
        $this->assertInstanceOf(Collection::class, $result['riskIssues']);
        $this->assertInstanceOf(Collection::class, $result['changeLog']);
        $this->assertInstanceOf(RiskIssue::class, $result['riskIssues']->first());
        $this->assertInstanceOf(ChangeRequest::class, $result['changeLog']->first());
        $this->assertSame([self::RISK_FIRST], $result['riskIssues']->modelKeys());
        $this->assertSame([self::CHANGE_FIRST], $result['changeLog']->modelKeys());
    }

    public function test_project_and_period_parameters_are_applied_to_both_collections(): void
    {
        $this->riskIssue(self::RISK_FIRST);
        $this->riskIssue(self::RISK_SECOND, updatedAt: '2026-10-10 09:00:00');
        $this->riskIssue(self::RISK_THIRD, attributes: ['project_id' => 'PRJ-2026-014']);
        $this->changeRequest(self::CHANGE_FIRST);
        $this->changeRequest(self::CHANGE_SECOND, '2026-10-10 09:00:00');
        $this->changeRequest(self::CHANGE_THIRD, attributes: [
            'project_id' => 'PRJ-2026-014',
            'mom_id' => '22222222-2222-4222-8222-000000000003',
        ]);

        $september = $this->controlData();
        $october = $this->controlData(startUtc: '2026-09-30 17:00:00', endUtc: '2026-10-31 16:59:59');
        $otherProject = $this->controlData('PRJ-2026-015');

        $this->assertSame([self::RISK_FIRST], $september['riskIssues']->modelKeys());
        $this->assertSame([self::CHANGE_FIRST], $september['changeLog']->modelKeys());
        $this->assertSame([self::RISK_SECOND], $october['riskIssues']->modelKeys());
        $this->assertSame([self::CHANGE_SECOND], $october['changeLog']->modelKeys());
        $this->assertSame([], $otherProject['riskIssues']->modelKeys());
        $this->assertSame([], $otherProject['changeLog']->modelKeys());
    }

    public function test_returns_two_empty_eloquent_collections_when_neither_query_matches(): void
    {
        $result = $this->controlData();

        $this->assertInstanceOf(Collection::class, $result['riskIssues']);
        $this->assertInstanceOf(Collection::class, $result['changeLog']);
        $this->assertSame([], $result['riskIssues']->modelKeys());
        $this->assertSame([], $result['changeLog']->modelKeys());
    }

    public function test_returns_risk_issues_when_the_change_log_is_empty(): void
    {
        $this->riskIssue(self::RISK_FIRST);

        $result = $this->controlData();

        $this->assertSame([self::RISK_FIRST], $result['riskIssues']->modelKeys());
        $this->assertSame([], $result['changeLog']->modelKeys());
    }

    public function test_returns_the_change_log_when_risk_issues_are_empty(): void
    {
        $this->changeRequest(self::CHANGE_FIRST);

        $result = $this->controlData();

        $this->assertSame([], $result['riskIssues']->modelKeys());
        $this->assertSame([self::CHANGE_FIRST], $result['changeLog']->modelKeys());
    }

    public function test_preserves_created_at_ascending_order_in_each_collection(): void
    {
        $this->riskIssue(self::RISK_FIRST, '2026-08-20 09:00:00');
        $this->riskIssue(self::RISK_SECOND, '2026-08-01 09:00:00', attributes: ['type' => RiskIssueType::ISSUE]);
        $this->changeRequest(self::CHANGE_FIRST, '2026-09-20 09:00:00');
        $this->changeRequest(self::CHANGE_SECOND, '2026-09-05 09:00:00');

        $result = $this->controlData();

        $this->assertSame([self::RISK_SECOND, self::RISK_FIRST], $result['riskIssues']->modelKeys());
        $this->assertSame([self::CHANGE_SECOND, self::CHANGE_FIRST], $result['changeLog']->modelKeys());
    }

    public function test_preserves_loaded_affected_tasks_without_extra_queries(): void
    {
        $this->riskIssue(self::RISK_FIRST);
        $change = $this->changeRequest(self::CHANGE_FIRST);
        $this->changeRequest(self::CHANGE_SECOND, '2026-09-11 09:00:00');
        $change->affectedTasks()->create([
            'task_id' => 'TSK-016-001',
            'added_by' => 'acc-tl-0042',
            'created_at' => '2026-09-10 09:00:00',
        ]);
        // One query per collection, plus one eager-loading query for all affected tasks.
        $this->expectsDatabaseQueryCount(3);

        $result = $this->controlData();

        $this->assertSame([self::RISK_FIRST], $result['riskIssues']->modelKeys());
        $this->assertSame([self::CHANGE_FIRST, self::CHANGE_SECOND], $result['changeLog']->modelKeys());
        foreach ($result['changeLog'] as $record) {
            $this->assertTrue($record->relationLoaded('affectedTasks'));
        }
        $this->assertSame(['TSK-016-001'], $result['changeLog'][0]->affectedTasks->pluck('task_id')->all());
        $this->assertSame([], $result['changeLog'][1]->affectedTasks->modelKeys());
    }

    private function controlData(string $projectId = self::PROJECT, string $startUtc = self::START, string $endUtc = self::END): array
    {
        return app(ControlDataService::class)->forPeriod(
            $projectId,
            CarbonImmutable::parse($startUtc, 'UTC'),
            CarbonImmutable::parse($endUtc, 'UTC'),
        );
    }

    private function riskIssue(string $id, string $createdAt = '2026-08-01 09:00:00', string $updatedAt = '2026-09-15 09:00:00', array $attributes = []): RiskIssue
    {
        return RiskIssue::forceCreate(array_replace([
            'id' => $id,
            'project_id' => self::PROJECT,
            'type' => RiskIssueType::RISK,
            'title' => 'Keterlambatan data uji',
            'description' => 'Data uji fiktif terlambat diterima.',
            'status' => RiskIssueStatus::CLOSED,
            'pic_account_id' => 'acc-tl-0042',
            'reported_by' => 'acc-pm-0007',
            'closed_at' => $updatedAt,
            'resolution_note' => 'Data uji cadangan telah disiapkan.',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ], $attributes));
    }

    private function changeRequest(string $id, string $createdAt = '2026-09-10 09:00:00', array $attributes = []): ChangeRequest
    {
        return ChangeRequest::forceCreate(array_replace([
            'id' => $id,
            'project_id' => self::PROJECT,
            'mom_id' => self::MOM,
            'change_description' => 'Penyesuaian format laporan bulanan.',
            'source' => ChangeSource::INTERNAL,
            'change_type' => ChangeType::TASK,
            'is_formal_cr' => false,
            'status' => ChangeRequestStatus::RECORDED,
            'created_by' => 'acc-pm-0007',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $attributes));
    }
}
