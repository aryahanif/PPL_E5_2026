<?php

namespace Tests\Feature;

use App\Enums\MeetingStatus;
use App\Enums\MomStatus;
use App\Models\Meeting;
use App\Models\RiskIssue;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-07 POST /api/v1/projects/{projectId}/risk-issues (createRiskIssue). */
class RiskIssueCreateTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const MOM = '22222222-2222-4222-8222-000000000003'; // FINAL MoM of PRJ-2026-014

    private function create(array $body = [], string $account = 'acc-pm-0007', string $projectId = 'PRJ-2026-014')
    {
        return $this->withToken("mock-{$account}")->postJson("/api/v1/projects/{$projectId}/risk-issues", $body + [
            'type' => 'ISSUE',
            'title' => 'Build CI gagal',
            'description' => 'Pipeline CI gagal sejak pembaruan dependensi.',
            'picAccountId' => 'acc-tl-0042',
        ]);
    }

    public function test_pm_creates_item_open_with_reporter_and_history(): void
    {
        $response = $this->create()->assertCreated();

        $id = $response->json('riskIssueId');
        $this->assertSame([
            'riskIssueId' => $id,
            'projectId' => 'PRJ-2026-014',
            'type' => 'ISSUE',
            'title' => 'Build CI gagal',
            'description' => 'Pipeline CI gagal sejak pembaruan dependensi.',
            'impactLevel' => null,
            'mitigation' => null,
            'status' => 'OPEN',
            'picAccountId' => 'acc-tl-0042',
            'sourceMomId' => null,
            'resolutionNote' => null,
            'reportedBy' => 'acc-pm-0007',
            'closedAt' => null,
        ], collect($response->json())->except('createdAt', 'updatedAt')->all());
        $response->assertJsonStructure(['createdAt', 'updatedAt']);

        $row = StatusHistory::where('entity_id', $id)->sole();
        $this->assertSame(['RISK_ISSUE', null, 'OPEN', 'acc-pm-0007'],
            [$row->entity_type, $row->from_status, $row->to_status, $row->changed_by]);
    }

    public function test_optional_fields_are_saved(): void
    {
        $this->create([
            'type' => 'RISK',
            'impactLevel' => 'HIGH',
            'mitigation' => 'Kunci versi dependensi',
            'sourceMomId' => self::MOM,
        ])->assertCreated()
            ->assertJsonPath('type', 'RISK')
            ->assertJsonPath('impactLevel', 'HIGH')
            ->assertJsonPath('mitigation', 'Kunci versi dependensi')
            ->assertJsonPath('sourceMomId', self::MOM);
    }

    public function test_missing_required_fields_is_400_and_nothing_is_saved(): void
    {
        $this->withToken('mock-acc-pm-0007')->postJson('/api/v1/projects/PRJ-2026-014/risk-issues', [])
            ->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: type, title, description, picAccountId']);

        $this->assertSame(2, RiskIssue::count());
    }

    public function test_invalid_values_are_400(): void
    {
        $this->create(['title' => str_repeat('a', 151)])->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: title']);

        $this->create(['type' => 'PROBLEM', 'impactLevel' => 'CRITICAL'])->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: type, impactLevel']);
    }

    public function test_pic_outside_project_is_400(): void
    {
        $this->create(['picAccountId' => 'acc-tw-0092'])->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: picAccountId']);
    }

    public function test_source_mom_of_other_project_is_400(): void
    {
        $meeting = Meeting::create([
            'project_id' => 'PRJ-2026-016', 'title' => 'Rapat proyek lain', 'scheduled_at' => now()->subDay(),
            'status' => MeetingStatus::DONE, 'created_by' => 'acc-pm-0007',
        ]);
        $otherMom = $meeting->mom()->create([
            'meeting_date' => now()->toDateString(), 'discussion' => 'x', 'decision' => 'y',
            'status' => MomStatus::FINAL, 'written_by' => 'acc-tw-0091',
        ]);

        $this->create(['sourceMomId' => $otherMom->id])->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: sourceMomId']);
    }

    public function test_missing_token_is_401(): void
    {
        $this->postJson('/api/v1/projects/PRJ-2026-014/risk-issues', [])->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_tl_is_403(): void
    {
        $this->create(account: 'acc-tl-0042')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Hanya PM yang dapat mencatat risiko/isu']);
    }

    public function test_tw_is_403(): void
    {
        $this->create(account: 'acc-tw-0091')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Hanya PM yang dapat mencatat risiko/isu']);

        $this->assertSame(2, RiskIssue::count());
    }

    public function test_non_member_is_403(): void
    {
        $this->create(account: 'acc-tw-0092')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Anda bukan anggota proyek ini']);
    }

    public function test_unknown_project_is_404(): void
    {
        $this->create(projectId: 'PRJ-2026-999')->assertStatus(404)
            ->assertJsonPath('code', 'PROJECT_NOT_FOUND');
    }

    public function test_inactive_project_is_409(): void
    {
        $this->create(['picAccountId' => 'acc-pm-0007'], projectId: 'PRJ-2026-015')->assertStatus(409)
            ->assertExactJson(['code' => 'PROJECT_INACTIVE', 'message' => 'Proyek PRJ-2026-015 tidak aktif']);
    }
}
