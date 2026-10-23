<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-07 GET /api/v1/projects/{projectId}/risk-issues (listRiskIssues). */
class RiskIssueListTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const RISK = '55555555-5555-4555-8555-000000000001'; // OPEN, MEDIUM, PIC PM, 7 days old

    private const ISSUE = '55555555-5555-4555-8555-000000000002'; // IN_PROGRESS, HIGH, PIC TL, 3 days old

    private function list(string $query = '', string $account = 'acc-pm-0007', string $projectId = 'PRJ-2026-014')
    {
        return $this->withToken("mock-{$account}")->getJson("/api/v1/projects/{$projectId}/risk-issues{$query}");
    }

    public function test_member_gets_items_newest_first_without_wrapper(): void
    {
        $response = $this->list()->assertOk()->assertJsonCount(2)->assertJsonStructure([[
            'riskIssueId', 'projectId', 'type', 'title', 'description', 'impactLevel', 'mitigation', 'status',
            'picAccountId', 'sourceMomId', 'resolutionNote', 'reportedBy', 'closedAt', 'createdAt', 'updatedAt',
        ]]);

        $this->assertSame([self::ISSUE, self::RISK], $response->json('*.riskIssueId'));
        $response->assertJsonPath('0.type', 'ISSUE')
            ->assertJsonPath('0.status', 'IN_PROGRESS')
            ->assertJsonPath('0.impactLevel', 'HIGH')
            ->assertJsonPath('0.sourceMomId', '22222222-2222-4222-8222-000000000003')
            ->assertJsonPath('1.picAccountId', 'acc-pm-0007');
    }

    public function test_tl_and_tw_can_read(): void
    {
        $this->list(account: 'acc-tw-0091')->assertOk()->assertJsonCount(2);

        $this->app['auth']->forgetGuards();
        $this->list(account: 'acc-tl-0042')->assertOk()->assertJsonCount(2);
    }

    public function test_filters(): void
    {
        $this->list('?type=RISK')->assertOk()->assertJsonCount(1)->assertJsonPath('0.riskIssueId', self::RISK);
        $this->list('?status=IN_PROGRESS')->assertOk()->assertJsonCount(1)->assertJsonPath('0.riskIssueId', self::ISSUE);
        $this->list('?impactLevel=MEDIUM')->assertOk()->assertJsonCount(1)->assertJsonPath('0.riskIssueId', self::RISK);
        $this->list('?type=ISSUE&status=CLOSED')->assertOk()->assertExactJson([]);
    }

    public function test_inactive_project_can_still_be_read(): void
    {
        $this->list(projectId: 'PRJ-2026-015')->assertOk()->assertExactJson([]);
    }

    public function test_invalid_filter_is_400(): void
    {
        $this->list('?status=DONE&impactLevel=CRITICAL')->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: status, impactLevel']);
    }

    public function test_missing_token_is_401(): void
    {
        $this->getJson('/api/v1/projects/PRJ-2026-014/risk-issues')->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_non_member_is_403(): void
    {
        $this->list(account: 'acc-tw-0092')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Anda bukan anggota proyek ini']);
    }

    public function test_unknown_project_is_404(): void
    {
        $this->list(projectId: 'PRJ-2026-999')->assertStatus(404)
            ->assertExactJson(['code' => 'PROJECT_NOT_FOUND', 'message' => 'Proyek PRJ-2026-999 tidak ditemukan']);
    }
}
