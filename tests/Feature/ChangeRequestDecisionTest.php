<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeSource;
use App\Enums\ChangeType;
use App\Models\ChangeRequest;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeRequestDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const RECORDED = '66666666-6666-4666-8666-000000000001';

    private const PENDING = '66666666-6666-4666-8666-000000000002';

    private const ANALYZED = '66666666-6666-4666-8666-000000000003';

    private function makeAnalyzed(ChangeRequestStatus $status = ChangeRequestStatus::ANALYZED): void
    {
        ChangeRequest::forceCreate([
            'id' => self::ANALYZED,
            'project_id' => 'PRJ-2026-014',
            'mom_id' => '22222222-2222-4222-8222-000000000003',
            'change_description' => 'Penambahan validasi login',
            'source' => ChangeSource::USER,
            'change_type' => ChangeType::SCOPE,
            'is_formal_cr' => true,
            'baseline_item_id' => 'BSL-014-01',
            'impact' => 'Dampak rendah',
            'analyzed_by' => 'acc-tl-0042',
            'status' => $status,
            'created_by' => 'acc-pm-0007',
        ]);
    }

    private function decide(string $id, array $body, string $account = 'acc-pm-0007')
    {
        return $this->withToken("mock-{$account}")->patchJson("/api/v1/change-requests/{$id}/decision", $body);
    }

    public function test_pm_approves_analyzed_cr_and_history_has_note(): void
    {
        $this->makeAnalyzed();

        $this->decide(self::ANALYZED, ['decision' => 'APPROVED', 'decisionNote' => 'Disetujui'])
            ->assertOk()
            ->assertJsonPath('changeRequestId', self::ANALYZED)
            ->assertJsonPath('status', 'APPROVED')
            ->assertJsonPath('decisionNote', 'Disetujui')
            ->assertJsonPath('decidedBy', 'acc-pm-0007')
            ->assertJsonPath('affectedTaskIds', []);

        $changeRequest = ChangeRequest::find(self::ANALYZED);
        $this->assertNotNull($changeRequest->decided_at);

        $history = StatusHistory::where('entity_type', ChangeRequest::HISTORY_TYPE)
            ->where('entity_id', self::ANALYZED)->latest('changed_at')->first();
        $this->assertSame('ANALYZED', $history->from_status);
        $this->assertSame('APPROVED', $history->to_status);
        $this->assertSame('Disetujui', $history->note);
        $this->assertSame('acc-pm-0007', $history->changed_by);
    }

    public function test_tl_rejects_analyzed_cr(): void
    {
        $this->makeAnalyzed();

        $this->decide(self::ANALYZED, ['decision' => 'REJECTED', 'decisionNote' => 'Di luar anggaran'], 'acc-tl-0042')
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('decidedBy', 'acc-tl-0042');
    }

    public function test_missing_note_or_bad_decision_is_400(): void
    {
        $this->makeAnalyzed();

        $this->decide(self::ANALYZED, ['decision' => 'APPROVED'])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->decide(self::ANALYZED, ['decision' => 'APPROVED', 'decisionNote' => ''])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->decide(self::ANALYZED, ['decision' => 'ANALYZED', 'decisionNote' => 'x'])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertSame('ANALYZED', ChangeRequest::find(self::ANALYZED)->status->value);
    }

    public function test_without_token_is_401(): void
    {
        $this->patchJson('/api/v1/change-requests/'.self::PENDING.'/decision', [
            'decision' => 'APPROVED', 'decisionNote' => 'x',
        ])->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_tw_is_403_with_message_and_non_member_is_403(): void
    {
        $this->makeAnalyzed();
        $body = ['decision' => 'APPROVED', 'decisionNote' => 'x'];

        $this->decide(self::ANALYZED, $body, 'acc-tw-0091')->assertStatus(403)->assertExactJson([
            'code' => 'FORBIDDEN',
            'message' => 'Hanya PM atau TL yang dapat memutuskan change request',
        ]);
        $this->decide(self::ANALYZED, $body, 'acc-tw-0092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame('ANALYZED', ChangeRequest::find(self::ANALYZED)->status->value);
    }

    public function test_unknown_change_request_is_404(): void
    {
        $this->decide('66666666-6666-4666-8666-999999999999', ['decision' => 'APPROVED', 'decisionNote' => 'x'])
            ->assertStatus(404)->assertJsonPath('code', 'CHANGE_REQUEST_NOT_FOUND');
    }

    public function test_pending_and_recorded_cr_cannot_be_decided(): void
    {
        $body = ['decision' => 'APPROVED', 'decisionNote' => 'x'];

        $this->decide(self::PENDING, $body)->assertStatus(409)->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
        $this->decide(self::RECORDED, $body)->assertStatus(409)->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_decided_cr_cannot_be_decided_again(): void
    {
        $this->makeAnalyzed(ChangeRequestStatus::APPROVED);

        $this->decide(self::ANALYZED, ['decision' => 'REJECTED', 'decisionNote' => 'x'])
            ->assertStatus(409)->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
        $this->assertSame('APPROVED', ChangeRequest::find(self::ANALYZED)->status->value);
    }
}
