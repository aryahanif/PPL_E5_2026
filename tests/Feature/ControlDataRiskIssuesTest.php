<?php

namespace Tests\Feature;

use App\Enums\RiskIssueStatus;
use App\Enums\RiskIssueType;
use App\Models\RiskIssue;
use App\Services\ControlDataService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-09 risk/issue query, independent of the control-data stub and change requests. */
class ControlDataRiskIssuesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PROJECT = 'PRJ-2026-016';

    // September 2026 in WIB, expressed as inclusive UTC boundaries.
    private const START = '2026-08-31 17:00:00';

    private const END = '2026-09-30 16:59:59';

    private const FIRST = '55555555-5555-4555-8555-000000000101';

    private const SECOND = '55555555-5555-4555-8555-000000000102';

    private const THIRD = '55555555-5555-4555-8555-000000000103';

    private const FOURTH = '55555555-5555-4555-8555-000000000104';

    public function test_open_risk_is_included_when_unchanged_before_the_period(): void
    {
        $this->riskIssue(self::FIRST, RiskIssueStatus::OPEN, '2026-08-20 09:00:00');

        $this->assertSame([self::FIRST], $this->ids());
    }

    public function test_in_progress_issue_is_included_when_updated_after_the_period(): void
    {
        $this->riskIssue(self::FIRST, RiskIssueStatus::IN_PROGRESS, '2026-10-01 09:00:00', [
            'type' => RiskIssueType::ISSUE,
        ]);

        $this->assertSame([self::FIRST], $this->ids());
    }

    public function test_closed_risks_are_filtered_by_updated_at(): void
    {
        $this->riskIssue(self::FIRST, RiskIssueStatus::CLOSED, '2026-09-15 09:00:00', [
            'closed_at' => '2026-08-20 09:00:00',
        ]);
        $this->riskIssue(self::SECOND, RiskIssueStatus::CLOSED, '2026-08-30 09:00:00');
        $this->riskIssue(self::THIRD, RiskIssueStatus::CLOSED, '2026-10-01 09:00:00', [
            'created_at' => '2026-09-10 09:00:00',
            'closed_at' => '2026-09-20 09:00:00',
        ]);

        $this->assertSame([self::FIRST], $this->ids());
    }

    public function test_other_project_records_are_excluded_from_both_query_branches(): void
    {
        $this->riskIssue(self::FIRST, RiskIssueStatus::CLOSED, '2026-09-15 09:00:00');
        $this->riskIssue(self::SECOND, RiskIssueStatus::CLOSED, '2026-09-15 09:00:00', [
            'project_id' => 'PRJ-2026-014',
        ]);

        // The shared seed also contains OPEN and IN_PROGRESS records in PRJ-2026-014.
        $this->assertSame([self::FIRST], $this->ids());
    }

    public function test_results_are_ordered_by_created_at_ascending(): void
    {
        $this->riskIssue(self::FIRST, RiskIssueStatus::OPEN, '2026-10-01 09:00:00', [
            'created_at' => '2026-09-20 09:00:00',
        ]);
        $this->riskIssue(self::SECOND, RiskIssueStatus::CLOSED, '2026-09-30 09:00:00', [
            'created_at' => '2026-08-01 09:00:00',
        ]);
        $this->riskIssue(self::THIRD, RiskIssueStatus::IN_PROGRESS, '2026-09-15 09:00:00', [
            'type' => RiskIssueType::ISSUE,
            'created_at' => '2026-09-10 09:00:00',
        ]);

        $this->assertSame([self::SECOND, self::THIRD, self::FIRST], $this->ids());
    }

    public function test_period_boundaries_are_inclusive_for_closed_records(): void
    {
        $this->riskIssue(self::FIRST, RiskIssueStatus::CLOSED, self::START);
        $this->riskIssue(self::SECOND, RiskIssueStatus::CLOSED, self::END, [
            'created_at' => '2026-08-02 09:00:00',
        ]);
        $this->riskIssue(self::THIRD, RiskIssueStatus::CLOSED, '2026-08-31 16:59:59');
        $this->riskIssue(self::FOURTH, RiskIssueStatus::CLOSED, '2026-09-30 17:00:00');

        $this->assertSame([self::FIRST, self::SECOND], $this->ids());
    }

    public function test_returns_an_empty_result_when_the_project_has_no_matching_records(): void
    {
        $this->assertSame([], $this->ids());
    }

    private function ids(): array
    {
        return app(ControlDataService::class)->riskIssuesForPeriod(
            self::PROJECT,
            CarbonImmutable::parse(self::START, 'UTC'),
            CarbonImmutable::parse(self::END, 'UTC'),
        )->modelKeys();
    }

    private function riskIssue(string $id, RiskIssueStatus $status, string $updatedAt, array $attributes = []): RiskIssue
    {
        $item = (new RiskIssue)->forceFill(array_replace([
            'id' => $id,
            'project_id' => self::PROJECT,
            'type' => RiskIssueType::RISK,
            'title' => 'Risiko keterlambatan data uji',
            'description' => 'Data uji fiktif belum diterima untuk pengujian modul laporan.',
            'status' => $status,
            'pic_account_id' => 'acc-tl-0042',
            'reported_by' => 'acc-pm-0007',
            'closed_at' => $status === RiskIssueStatus::CLOSED ? $updatedAt : null,
            'resolution_note' => $status === RiskIssueStatus::CLOSED ? 'Data uji cadangan telah disiapkan.' : null,
            'created_at' => '2026-08-01 09:00:00',
            'updated_at' => $updatedAt,
        ], $attributes));
        $item->save();

        return $item;
    }
}
