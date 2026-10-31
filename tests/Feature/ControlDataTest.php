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
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** API-K05-09 GET /api/v1/projects/{projectId}/control-data. */
class ControlDataTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PROJECT = 'PRJ-2026-016';

    private const PERIOD = '?periodStart=2026-09-01&periodEnd=2026-09-30';

    private const MEETING = '11111111-1111-4111-8111-000000000301';

    private const MOM = '22222222-2222-4222-8222-000000000301';

    private const RISK_FIRST = '55555555-5555-4555-8555-000000000301';

    private const RISK_SECOND = '55555555-5555-4555-8555-000000000302';

    private const RISK_THIRD = '55555555-5555-4555-8555-000000000303';

    private const CHANGE_FIRST = '66666666-6666-4666-8666-000000000301';

    private const CHANGE_SECOND = '66666666-6666-4666-8666-000000000302';

    private const CHANGE_THIRD = '66666666-6666-4666-8666-000000000303';

    private const CHANGE_FOURTH = '66666666-6666-4666-8666-000000000304';

    private const CHANGE_FIFTH = '66666666-6666-4666-8666-000000000305';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-01 02:00:00', 'UTC'));

        Meeting::forceCreate([
            'id' => self::MEETING,
            'project_id' => self::PROJECT,
            'title' => 'Rapat pengendalian proyek',
            'scheduled_at' => '2026-08-20 02:00:00',
            'status' => MeetingStatus::DONE,
            'created_by' => 'acc-pm-0007',
        ]);

        Mom::forceCreate([
            'id' => self::MOM,
            'meeting_id' => self::MEETING,
            'meeting_date' => '2026-08-20',
            'discussion' => 'Risiko dan perubahan modul laporan.',
            'decision' => 'Catat risiko dan perubahan untuk ditindaklanjuti.',
            'status' => MomStatus::FINAL,
            'written_by' => 'acc-tw-0091',
            'reviewed_by' => 'acc-tl-0042',
            'finalized_at' => '2026-08-20 03:00:00',
        ]);
    }

    public function test_returns_exact_control_data_schema_from_database_without_internal_fields_or_wrappers(): void
    {
        $risk = $this->riskIssue(self::RISK_FIRST);
        $change = $this->changeRequest(self::CHANGE_FIRST, attributes: [
            'status' => ChangeRequestStatus::APPROVED,
            'source' => ChangeSource::USER,
            'change_type' => ChangeType::SCOPE,
            'is_formal_cr' => true,
            'impact' => 'Tambah dua hari untuk pengujian.',
            'decision_note' => 'Disetujui untuk rilis berikutnya.',
            'decided_at' => '2026-09-17 04:00:00',
            'analyzed_by' => 'acc-tl-0042',
            'decided_by' => 'acc-pm-0007',
            'baseline_item_id' => 'BSL-016-01',
        ]);
        foreach (['TSK-016-001', 'TSK-016-002'] as $taskId) {
            $change->affectedTasks()->create([
                'task_id' => $taskId,
                'impact_note' => 'Perbarui pengujian.',
                'added_by' => 'acc-tl-0042',
                'created_at' => '2026-09-16 03:00:00',
            ]);
        }

        $response = $this->fetch()->assertOk();
        $taskIds = $response->json('changeLog.0.affectedTaskIds');
        $this->assertIsList($taskIds);
        $this->assertEqualsCanonicalizing(['TSK-016-001', 'TSK-016-002'], $taskIds);

        $response->assertExactJson([
            'projectId' => self::PROJECT,
            'periodStart' => '2026-09-01',
            'periodEnd' => '2026-09-30',
            'generatedAt' => '2026-10-01T02:00:00Z',
            'riskIssues' => [[
                'riskIssueId' => self::RISK_FIRST,
                'type' => 'RISK',
                'title' => 'Risiko keterlambatan data uji',
                'description' => 'Data uji fiktif belum diterima.',
                'impactLevel' => null,
                'status' => 'CLOSED',
                'picAccountId' => 'acc-tl-0042',
                'createdAt' => '2026-08-01T09:00:00Z',
                'closedAt' => '2026-09-15T09:00:00Z',
                'resolutionNote' => 'Data uji cadangan telah disiapkan.',
            ]],
            'changeLog' => [[
                'changeRequestId' => self::CHANGE_FIRST,
                'changeDescription' => 'Penyesuaian format laporan bulanan.',
                'source' => 'USER',
                'changeType' => 'SCOPE',
                'isFormalCr' => true,
                'impact' => 'Tambah dua hari untuk pengujian.',
                'status' => 'APPROVED',
                'decisionNote' => 'Disetujui untuk rilis berikutnya.',
                'createdAt' => '2026-09-10T09:00:00Z',
                'decidedAt' => '2026-09-17T04:00:00Z',
                'baselineItemId' => 'BSL-016-01',
                'affectedTaskIds' => $taskIds,
            ]],
        ]);
        $response->assertJsonMissingPath('data')
            ->assertJsonMissingPath('riskIssues.data')
            ->assertJsonMissingPath('changeLog.data')
            ->assertJsonMissingPath('riskIssues.0.reportedBy')
            ->assertJsonMissingPath('changeLog.0.createdBy')
            ->assertJsonMissingPath('changeLog.0.analyzedBy')
            ->assertJsonMissingPath('changeLog.0.decidedBy');

        // A second request must reflect database changes, rather than the example JSON.
        RiskIssue::withoutTimestamps(fn () => $risk->update(['title' => 'Data uji sudah tersedia']));
        $change->update(['change_description' => 'Format laporan diperbarui.']);
        $this->fetch()->assertOk()
            ->assertJsonPath('riskIssues.0.title', 'Data uji sudah tersedia')
            ->assertJsonPath('changeLog.0.changeDescription', 'Format laporan diperbarui.');
    }

    public function test_nullable_fields_remain_present_and_tasks_may_be_empty(): void
    {
        $this->riskIssue(self::RISK_FIRST, attributes: [
            'status' => RiskIssueStatus::OPEN,
            'closed_at' => null,
            'resolution_note' => null,
        ]);
        $this->changeRequest(self::CHANGE_FIRST);

        $this->fetch()->assertOk()->assertJsonCount(6)
            ->assertJsonStructure([
                'riskIssues' => [['impactLevel', 'closedAt', 'resolutionNote']],
                'changeLog' => [['impact', 'decisionNote', 'decidedAt', 'baselineItemId', 'affectedTaskIds']],
            ])
            ->assertJsonPath('riskIssues.0.impactLevel', null)
            ->assertJsonPath('riskIssues.0.closedAt', null)
            ->assertJsonPath('riskIssues.0.resolutionNote', null)
            ->assertJsonPath('changeLog.0.isFormalCr', false)
            ->assertJsonPath('changeLog.0.impact', null)
            ->assertJsonPath('changeLog.0.decisionNote', null)
            ->assertJsonPath('changeLog.0.decidedAt', null)
            ->assertJsonPath('changeLog.0.baselineItemId', null)
            ->assertJsonPath('changeLog.0.affectedTaskIds', []);
    }

    public function test_project_filter_applies_to_every_risk_and_change_log_branch(): void
    {
        $this->riskIssue(self::RISK_FIRST);
        $this->changeRequest(self::CHANGE_FIRST);
        $this->riskIssue(self::RISK_SECOND, attributes: ['project_id' => 'PRJ-2026-014']);
        $this->riskIssue(self::RISK_THIRD, attributes: [
            'project_id' => 'PRJ-2026-014',
            'status' => RiskIssueStatus::OPEN,
            'closed_at' => null,
            'resolution_note' => null,
        ]);
        foreach ([
            self::CHANGE_SECOND => ChangeRequestStatus::RECORDED,
            self::CHANGE_THIRD => ChangeRequestStatus::APPROVED,
            self::CHANGE_FOURTH => ChangeRequestStatus::REJECTED,
        ] as $id => $status) {
            $this->changeRequest($id, attributes: [
                'project_id' => 'PRJ-2026-014',
                'mom_id' => '22222222-2222-4222-8222-000000000003',
                'status' => $status,
                'is_formal_cr' => $status !== ChangeRequestStatus::RECORDED,
                'decided_at' => $status === ChangeRequestStatus::RECORDED ? null : '2026-09-15 09:00:00',
            ]);
        }

        $this->fetch()->assertOk()
            ->assertJsonPath('riskIssues.*.riskIssueId', [self::RISK_FIRST])
            ->assertJsonPath('changeLog.*.changeRequestId', [self::CHANGE_FIRST]);
    }

    public function test_change_log_contains_only_recorded_and_decided_entries_in_created_at_order(): void
    {
        $this->changeRequest(self::CHANGE_FIRST, '2026-09-20 09:00:00');
        $this->changeRequest(self::CHANGE_SECOND, '2026-08-05 09:00:00', [
            'status' => ChangeRequestStatus::APPROVED,
            'is_formal_cr' => true,
            'decided_at' => '2026-09-15 09:00:00',
        ]);
        $this->changeRequest(self::CHANGE_THIRD, '2026-08-01 09:00:00', [
            'status' => ChangeRequestStatus::REJECTED,
            'is_formal_cr' => true,
            'decided_at' => '2026-09-25 09:00:00',
        ]);
        $this->changeRequest(self::CHANGE_FOURTH, attributes: [
            'status' => ChangeRequestStatus::PENDING_ANALYSIS,
            'is_formal_cr' => true,
        ]);
        $this->changeRequest(self::CHANGE_FIFTH, attributes: [
            'status' => ChangeRequestStatus::ANALYZED,
            'is_formal_cr' => true,
        ]);

        $this->fetch()->assertOk()
            ->assertJsonPath('changeLog.*.changeRequestId', [self::CHANGE_THIRD, self::CHANGE_SECOND, self::CHANGE_FIRST])
            ->assertJsonPath('changeLog.*.status', ['REJECTED', 'APPROVED', 'RECORDED']);
    }

    public function test_change_log_uses_recorded_creation_time_and_formal_decision_time(): void
    {
        $this->changeRequest(self::CHANGE_FIRST, '2026-08-20 09:00:00', [
            'updated_at' => '2026-09-15 09:00:00',
        ]);
        $this->changeRequest(self::CHANGE_SECOND, attributes: [
            'status' => ChangeRequestStatus::APPROVED,
            'is_formal_cr' => true,
            'decided_at' => '2026-09-30 17:00:00',
        ]);
        $this->changeRequest(self::CHANGE_THIRD, attributes: [
            'status' => ChangeRequestStatus::REJECTED,
            'is_formal_cr' => true,
            'decided_at' => '2026-08-31 16:59:59',
        ]);

        $this->fetch()->assertOk()->assertJsonPath('changeLog', []);
    }

    public function test_non_closed_risks_outside_the_period_remain_included_in_created_at_order(): void
    {
        $this->riskIssue(self::RISK_FIRST, '2026-08-10 09:00:00', [
            'status' => RiskIssueStatus::OPEN,
            'created_at' => '2026-08-10 08:00:00',
            'closed_at' => null,
            'resolution_note' => null,
        ]);
        $this->riskIssue(self::RISK_SECOND, '2026-10-01 09:00:00', [
            'type' => RiskIssueType::ISSUE,
            'status' => RiskIssueStatus::IN_PROGRESS,
            'created_at' => '2026-10-01 08:00:00',
            'closed_at' => null,
            'resolution_note' => null,
        ]);
        $this->riskIssue(self::RISK_THIRD, attributes: [
            // CLOSED entries qualify through updated_at even if they were closed earlier.
            'closed_at' => '2026-08-20 09:00:00',
        ]);

        $this->fetch()->assertOk()
            ->assertJsonPath('riskIssues.*.riskIssueId', [self::RISK_THIRD, self::RISK_FIRST, self::RISK_SECOND])
            ->assertJsonPath('riskIssues.*.status', ['CLOSED', 'OPEN', 'IN_PROGRESS']);
    }

    #[DataProvider('periodBoundaries')]
    public function test_wib_boundaries_filter_all_timestamp_branches(string $timestampUtc, bool $included): void
    {
        $this->riskIssue(self::RISK_FIRST, $timestampUtc);
        $this->changeRequest(self::CHANGE_FIRST, $timestampUtc);
        $this->changeRequest(self::CHANGE_SECOND, '2026-08-01 09:00:00', [
            'status' => ChangeRequestStatus::APPROVED,
            'is_formal_cr' => true,
            'decided_at' => $timestampUtc,
        ]);
        $this->changeRequest(self::CHANGE_THIRD, '2026-08-02 09:00:00', [
            'status' => ChangeRequestStatus::REJECTED,
            'is_formal_cr' => true,
            'decided_at' => $timestampUtc,
        ]);

        $response = $this->fetch()->assertOk()
            ->assertJsonPath('riskIssues.*.riskIssueId', $included ? [self::RISK_FIRST] : [])
            ->assertJsonCount($included ? 3 : 0, 'changeLog');
        $this->assertEqualsCanonicalizing(
            $included ? [self::CHANGE_FIRST, self::CHANGE_SECOND, self::CHANGE_THIRD] : [],
            $response->json('changeLog.*.changeRequestId'),
        );
    }

    public static function periodBoundaries(): array
    {
        return [
            'one second before 00:00 WIB' => ['2026-08-31 16:59:59', false],
            'first day at 00:00 WIB' => ['2026-08-31 17:00:00', true],
            'last day at 23:30 WIB' => ['2026-09-30 16:30:00', true],
            'last day at 23:59:59 WIB' => ['2026-09-30 16:59:59', true],
            'one second after the period' => ['2026-09-30 17:00:00', false],
        ];
    }

    public function test_controller_passes_immutable_utc_boundaries_independently_of_server_timezone(): void
    {
        $this->mock(ControlDataService::class)->shouldReceive('forPeriod')->once()
            ->withArgs(function (string $projectId, CarbonImmutable $startUtc, CarbonImmutable $endUtc): bool {
                $this->assertSame(self::PROJECT, $projectId);
                $this->assertSame('UTC', $startUtc->timezoneName);
                $this->assertSame('UTC', $endUtc->timezoneName);
                $this->assertSame('2026-08-31 17:00:00', $startUtc->format('Y-m-d H:i:s'));
                $this->assertSame('2026-09-30 16:59:59', $endUtc->format('Y-m-d H:i:s'));

                return true;
            })
            ->andReturn(['riskIssues' => new Collection, 'changeLog' => new Collection]);
        $originalTimezone = date_default_timezone_get();

        try {
            date_default_timezone_set('Pacific/Honolulu');
            $this->fetch()->assertOk()->assertJsonPath('generatedAt', '2026-10-01T02:00:00Z');
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    #[DataProvider('projectMembers')]
    public function test_pm_tl_and_tw_project_members_can_read_control_data(string $account): void
    {
        $this->riskIssue(self::RISK_FIRST);
        $this->fetch(account: $account)->assertOk()->assertJsonPath('riskIssues.0.riskIssueId', self::RISK_FIRST);
    }

    public static function projectMembers(): array
    {
        return [
            'PM' => ['acc-pm-0007'],
            'TL' => ['acc-tl-0042'],
            'TW' => ['acc-tw-0091'],
        ];
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_period_is_400(string $query): void
    {
        $this->fetch(query: $query)->assertStatus(400)->assertExactJson([
            'code' => 'INVALID_PERIOD',
            'message' => 'periodStart dan periodEnd wajib berformat YYYY-MM-DD, dan periodEnd tidak boleh sebelum periodStart',
        ]);
    }

    public static function invalidPeriods(): array
    {
        return [
            'both missing' => [''],
            'start missing' => ['?periodEnd=2026-09-30'],
            'end missing' => ['?periodStart=2026-09-01'],
            'empty start' => ['?periodStart=&periodEnd=2026-09-30'],
            'empty end' => ['?periodStart=2026-09-01&periodEnd='],
            'both empty' => ['?periodStart=&periodEnd='],
            'reversed' => ['?periodStart=2026-09-30&periodEnd=2026-09-01'],
            'invalid start' => ['?periodStart=invalid&periodEnd=2026-09-30'],
            'invalid end' => ['?periodStart=2026-09-01&periodEnd=invalid'],
            'impossible date' => ['?periodStart=2026-02-30&periodEnd=2026-09-30'],
            'short date' => ['?periodStart=2026-9-1&periodEnd=2026-09-30'],
            'start timestamp' => ['?periodStart=2026-09-01T00:00:00Z&periodEnd=2026-09-30'],
            'end timestamp' => ['?periodStart=2026-09-01&periodEnd=2026-09-30T23:59:59Z'],
            'start array' => ['?periodStart[]=2026-09-01&periodEnd=2026-09-30'],
            'end array' => ['?periodStart=2026-09-01&periodEnd[]=2026-09-30'],
        ];
    }

    public function test_missing_authentication_is_401(): void
    {
        $this->getJson('/api/v1/projects/'.self::PROJECT.'/control-data'.self::PERIOD)
            ->assertStatus(401)->assertExactJson(['code' => 'UNAUTHORIZED', 'message' => 'Token tidak ada atau tidak sah']);
    }

    public function test_unknown_token_is_401(): void
    {
        $this->fetch(account: 'acc-unknown')->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_non_member_is_403(): void
    {
        $this->fetch(account: 'acc-tw-0092')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Anda bukan anggota proyek ini']);
    }

    public function test_unknown_project_is_404(): void
    {
        $this->fetch('PRJ-2026-999')->assertStatus(404)
            ->assertExactJson(['code' => 'PROJECT_NOT_FOUND', 'message' => 'Proyek PRJ-2026-999 tidak ditemukan']);
    }

    public function test_no_matching_data_is_200_with_empty_arrays_and_no_wrapper(): void
    {
        $this->fetch()->assertOk()->assertExactJson([
            'projectId' => self::PROJECT,
            'periodStart' => '2026-09-01',
            'periodEnd' => '2026-09-30',
            'generatedAt' => '2026-10-01T02:00:00Z',
            'riskIssues' => [],
            'changeLog' => [],
        ]);
    }

    public function test_risks_are_returned_when_change_log_is_empty(): void
    {
        $this->riskIssue(self::RISK_FIRST);
        $this->fetch()->assertOk()->assertJsonPath('riskIssues.*.riskIssueId', [self::RISK_FIRST])
            ->assertJsonPath('changeLog', []);
    }

    public function test_change_log_is_returned_when_risks_are_empty(): void
    {
        $this->changeRequest(self::CHANGE_FIRST);
        $this->fetch()->assertOk()->assertJsonPath('riskIssues', [])
            ->assertJsonPath('changeLog.*.changeRequestId', [self::CHANGE_FIRST]);
    }

    public function test_same_day_period_and_inactive_project_reads_are_allowed(): void
    {
        $this->riskIssue(self::RISK_FIRST, '2026-09-15 09:00:00', ['project_id' => 'PRJ-2026-015']);

        $this->fetch('PRJ-2026-015', '?periodStart=2026-09-15&periodEnd=2026-09-15')->assertOk()
            ->assertJsonPath('projectId', 'PRJ-2026-015')
            ->assertJsonPath('periodStart', '2026-09-15')
            ->assertJsonPath('periodEnd', '2026-09-15')
            ->assertJsonPath('riskIssues.*.riskIssueId', [self::RISK_FIRST]);
    }

    private function fetch(string $projectId = self::PROJECT, string $query = self::PERIOD, string $account = 'acc-pm-0007'): TestResponse
    {
        return $this->withToken("mock-{$account}")->getJson("/api/v1/projects/{$projectId}/control-data{$query}");
    }

    private function riskIssue(string $id, string $updatedAt = '2026-09-15 09:00:00', array $attributes = []): RiskIssue
    {
        return RiskIssue::forceCreate(array_replace([
            'id' => $id,
            'project_id' => self::PROJECT,
            'type' => RiskIssueType::RISK,
            'title' => 'Risiko keterlambatan data uji',
            'description' => 'Data uji fiktif belum diterima.',
            'status' => RiskIssueStatus::CLOSED,
            'pic_account_id' => 'acc-tl-0042',
            'reported_by' => 'acc-pm-0007',
            'closed_at' => $updatedAt,
            'resolution_note' => 'Data uji cadangan telah disiapkan.',
            'created_at' => '2026-08-01 09:00:00',
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
            'created_by' => 'acc-tw-0091',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $attributes));
    }
}
