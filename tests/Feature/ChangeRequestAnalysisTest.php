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

    private const RECORDED = '019a0000-6666-7666-8666-000000000001';

    private const PENDING = '019a0000-6666-7666-8666-000000000002';

    private function patchAnalysis(string $id, array $body, string $account = '019a0000-f101-7000-8000-000000000042')
    {
        return $this->withToken("mock-{$account}")->patchJson("/api/v1/change-requests/{$id}/analysis", $body);
    }

    public function test_tl_analyzes_formal_cr_and_links_baseline_tasks_and_history(): void
    {
        $response = $this->patchAnalysis(self::PENDING, [
            'impact' => 'Dampak rendah terhadap jadwal',
            'baselineItemId' => '019a0000-e301-7000-8000-000000014001',
            'affectedTaskIds' => ['019a0000-e401-7000-8000-000000014001', '019a0000-e401-7000-8000-000000014002'],
        ])->assertOk()
            ->assertJsonPath('status', 'ANALYZED')
            ->assertJsonPath('impact', 'Dampak rendah terhadap jadwal')
            ->assertJsonPath('baselineItemId', '019a0000-e301-7000-8000-000000014001')
            ->assertJsonPath('analyzedBy', '019a0000-f101-7000-8000-000000000042')
            ->assertJsonPath('affectedTaskIds', ['019a0000-e401-7000-8000-000000014001', '019a0000-e401-7000-8000-000000014002']);

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
            'baselineItemId' => '019a0000-e301-7000-8000-000000016001',
            'affectedTaskIds' => [],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->patchAnalysis(self::PENDING, [
            'impact' => 'Dampak',
            'baselineItemId' => '019a0000-e301-7000-8000-000000014001',
            'affectedTaskIds' => ['019a0000-e401-7000-8000-000000016001'],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_impact_is_required_and_task_ids_must_be_unique(): void
    {
        $this->patchAnalysis(self::PENDING, [
            'baselineItemId' => '019a0000-e301-7000-8000-000000014001',
            'affectedTaskIds' => [],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->patchAnalysis(self::PENDING, [
            'impact' => 'Dampak',
            'baselineItemId' => '019a0000-e301-7000-8000-000000014001',
            'affectedTaskIds' => ['019a0000-e401-7000-8000-000000014001', '019a0000-e401-7000-8000-000000014001'],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_recorded_entry_cannot_be_analyzed(): void
    {
        $this->patchAnalysis(self::RECORDED, [
            'impact' => 'Dampak',
            'baselineItemId' => '019a0000-e301-7000-8000-000000014001',
            'affectedTaskIds' => [],
        ])->assertStatus(409)->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_without_token_is_401(): void
    {
        $this->patchJson('/api/v1/change-requests/'.self::PENDING.'/analysis', [
            'impact' => 'Dampak', 'baselineItemId' => '019a0000-e301-7000-8000-000000014001', 'affectedTaskIds' => [],
        ])->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_pm_tw_and_non_member_are_403(): void
    {
        $body = ['impact' => 'Dampak', 'baselineItemId' => '019a0000-e301-7000-8000-000000014001', 'affectedTaskIds' => []];
        $this->patchAnalysis(self::PENDING, $body, '019a0000-f101-7000-8000-000000000007')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->patchAnalysis(self::PENDING, $body, '019a0000-f101-7000-8000-000000000091')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->patchAnalysis(self::PENDING, $body, '019a0000-f101-7000-8000-000000000092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_change_request_is_404(): void
    {
        $this->patchAnalysis('019a0000-6666-7666-8666-999999999999', [
            'impact' => 'Dampak', 'affectedTaskIds' => [],
        ])->assertStatus(404)->assertJsonPath('code', 'CHANGE_REQUEST_NOT_FOUND');
    }
}