<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-08 GET /api/v1/projects/{projectId}/indicator (getProjectIndicator). */
class ProjectIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // E-3 targets are dated: pin "today" so the numbers do not drift (target 45 / 60).
        $this->travelTo('2026-09-20 09:00:00');
    }

    private function indicator(string $projectId = 'PRJ-2026-014', string $account = 'acc-pm-0007')
    {
        return $this->withToken("mock-{$account}")->getJson("/api/v1/projects/{$projectId}/indicator");
    }

    public function test_pm_sees_on_track_project(): void
    {
        $this->indicator()->assertOk()->assertExactJson([
            'projectId' => 'PRJ-2026-014',
            'indicator' => 'ON_TRACK',
            'targetProgress' => 45.0,
            'actualProgress' => 48.0,
            'tolerance' => 0.0,
            'asOf' => '2026-09-20',
        ]);
    }

    public function test_pm_sees_delayed_project(): void
    {
        $this->indicator('PRJ-2026-016')->assertOk()
            ->assertJsonPath('indicator', 'DELAYED')
            ->assertJsonPath('targetProgress', 60)
            ->assertJsonPath('actualProgress', 40);
    }

    public function test_indicator_follows_the_date(): void
    {
        // Same progress, earlier date: the 016 target drops to 50 (still behind), 014 to 30.
        $this->travelTo('2026-09-05 09:00:00');

        $this->indicator()->assertOk()->assertJsonPath('targetProgress', 30)->assertJsonPath('indicator', 'ON_TRACK');
    }

    public function test_tolerance_setting_changes_the_result(): void
    {
        config(['e5.indicator.tolerance' => 25.0]); // 016 is 20 points behind

        $this->indicator('PRJ-2026-016')->assertOk()
            ->assertJsonPath('indicator', 'ON_TRACK')
            ->assertJsonPath('tolerance', 25);
    }

    public function test_project_without_target_or_tasks_has_null_indicator(): void
    {
        // PRJ-2026-015 is inactive and has no E-3/E-4 data; reading it is still allowed.
        $this->indicator('PRJ-2026-015')->assertOk()->assertExactJson([
            'projectId' => 'PRJ-2026-015',
            'indicator' => null,
            'targetProgress' => null,
            'actualProgress' => null,
            'tolerance' => 0.0,
            'asOf' => '2026-09-20',
        ]);
    }

    public function test_tl_is_403(): void
    {
        $this->indicator(account: 'acc-tl-0042')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Indikator proyek hanya dapat diakses oleh PM']);
    }

    public function test_tw_is_403(): void
    {
        $this->indicator(account: 'acc-tw-0091')->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_non_member_is_403(): void
    {
        $this->indicator(account: 'acc-tw-0092')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Anda bukan anggota proyek ini']);
    }

    public function test_missing_token_is_401(): void
    {
        $this->getJson('/api/v1/projects/PRJ-2026-014/indicator')->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_unknown_project_is_404(): void
    {
        $this->indicator('PRJ-2026-999')->assertStatus(404)
            ->assertExactJson(['code' => 'PROJECT_NOT_FOUND', 'message' => 'Proyek PRJ-2026-999 tidak ditemukan']);
    }

    /** The indicator is calculated; nobody can set it by hand (FTR-K05-04). */
    public function test_indicator_cannot_be_edited(): void
    {
        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->withToken('mock-acc-pm-0007')->{$method}('/api/v1/projects/PRJ-2026-014/indicator', ['indicator' => 'ON_TRACK'])
                ->assertStatus(405)
                ->assertJsonPath('code', 'METHOD_NOT_ALLOWED');
        }
    }
}
