<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeRequestAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const RECORDED = '66666666-6666-4666-8666-000000000001';

    private const PENDING = '66666666-6666-4666-8666-000000000002';

    private function patchAnalysis(string $id, array $body, string $account = 'acc-tl-0042')
    {
        return $this->withToken("mock-{$account}")->patchJson("/api/v1/change-requests/{$id}/analysis", $body);
    }

    public function test_tl_analyzes_formal_cr_and_links_baseline_tasks_and_history(): void
    {
        $response = $this->patchAnalysis(self::PENDING, [
            'impact' => 'Dampak rendah terhadap jadwal',
            'baselineItemId' => 'BSL-014-01',
            'affectedTaskIds' => ['TSK-014-001', 'TSK-014-002'],
        ])->assertOk()
            ->assertJsonPath('status', 'ANALYZED')
            ->assertJsonPath('impact', 'Dampak rendah terhadap jadwal')
            ->assertJsonPath('baselineItemId', 'BSL-014-01')
            ->assertJsonPath('analyzedBy', 'acc-tl-0042')
            ->assertJsonPath('affectedTaskIds', ['TSK-014-001', 'TSK-014-002']);

        $this->assertSame('ANALYZED', StatusHistory::where('entity_type', ChangeRequest::HISTORY_TYPE)
            ->where('entity_id', self::PENDING)->latest('changed_at')->first()->to_status);
        $this->assertSame('ANALYZED', ChangeRequest::find(self::PENDING)->refresh()->status->value);
        $this->assertSame($response->json('changeRequestId'), self::PENDING);
    }

    public function test_missing_baseline_for_scope_is_400(): void
    {
        $this->patchAnalysis(self::PENDING, ['impact' => 'Dampak', 'affectedTaskIds' => []])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_baseline_or_task_from_another_project_is_400(): void
    {
        $this->patchAnalysis(self::PENDING, [
            'impact' => 'Dampak',
            'baselineItemId' => 'BSL-016-01',
            'affectedTaskIds' => [],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->patchAnalysis(self::PENDING, [
            'impact' => 'Dampak',
            'baselineItemId' => 'BSL-014-01',
            'affectedTaskIds' => ['TSK-016-001'],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_impact_is_required_and_task_ids_must_be_unique(): void
    {
        $this->patchAnalysis(self::PENDING, [
            'baselineItemId' => 'BSL-014-01',
            'affectedTaskIds' => [],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->patchAnalysis(self::PENDING, [
            'impact' => 'Dampak',
            'baselineItemId' => 'BSL-014-01',
            'affectedTaskIds' => ['TSK-014-001', 'TSK-014-001'],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_recorded_entry_cannot_be_analyzed(): void
    {
        $this->patchAnalysis(self::RECORDED, [
            'impact' => 'Dampak',
            'baselineItemId' => 'BSL-014-01',
            'affectedTaskIds' => [],
        ])->assertStatus(409)->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_without_token_is_401(): void
    {
        $this->patchJson('/api/v1/change-requests/'.self::PENDING.'/analysis', [
            'impact' => 'Dampak', 'baselineItemId' => 'BSL-014-01', 'affectedTaskIds' => [],
        ])->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_pm_tw_and_non_member_are_403(): void
    {
        $body = ['impact' => 'Dampak', 'baselineItemId' => 'BSL-014-01', 'affectedTaskIds' => []];
        $this->patchAnalysis(self::PENDING, $body, 'acc-pm-0007')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->patchAnalysis(self::PENDING, $body, 'acc-tw-0091')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->patchAnalysis(self::PENDING, $body, 'acc-tw-0092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_change_request_is_404(): void
    {
        $this->patchAnalysis('66666666-6666-4666-8666-999999999999', [
            'impact' => 'Dampak', 'affectedTaskIds' => [],
        ])->assertStatus(404)->assertJsonPath('code', 'CHANGE_REQUEST_NOT_FOUND');
    }
}