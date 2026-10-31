<?php

namespace Tests\Feature;

use App\Enums\MomStatus;
use App\Models\ChangeRequest;
use App\Models\Meeting;
use App\Models\Mom;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeRequestCreateTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const FINAL_MOM = '22222222-2222-4222-8222-000000000003';

    private function body(array $overrides = []): array
    {
        return array_replace([
            'momId' => self::FINAL_MOM,
            'changeDescription' => 'Menambahkan validasi pada autentikasi',
            'source' => 'USER',
            'changeType' => 'SCOPE',
            'isFormalCr' => false,
        ], $overrides);
    }

    private function postChange(array $body, string $account = 'acc-pm-0007')
    {
        return $this->withToken("mock-{$account}")->postJson('/api/v1/change-requests', $body);
    }

    public function test_pm_records_non_formal_change_and_history(): void
    {
        $response = $this->postChange($this->body())->assertCreated()
            ->assertJsonPath('projectId', 'PRJ-2026-014')
            ->assertJsonPath('momId', self::FINAL_MOM)
            ->assertJsonPath('isFormalCr', false)
            ->assertJsonPath('status', 'RECORDED')
            ->assertJsonPath('createdBy', 'acc-pm-0007');

        $history = StatusHistory::where('entity_type', ChangeRequest::HISTORY_TYPE)
            ->where('entity_id', $response->json('changeRequestId'))->sole();

        $this->assertNull($history->from_status);
        $this->assertSame('RECORDED', $history->to_status);
        $this->assertSame('acc-pm-0007', $history->changed_by);
    }

    public function test_tl_records_formal_cr_as_pending_analysis(): void
    {
        $response = $this->postChange($this->body(['isFormalCr' => true]), 'acc-tl-0042')
            ->assertCreated()->assertJsonPath('status', 'PENDING_ANALYSIS');

        $this->assertSame('PENDING_ANALYSIS', StatusHistory::where('entity_id', $response->json('changeRequestId'))->sole()->to_status);
    }

    public function test_required_fields_and_enum_values_are_400(): void
    {
        $this->postChange([])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->postChange($this->body(['changeType' => 'OTHER']))->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_mom_must_be_final(): void
    {
        Mom::create([
            'meeting_id' => '11111111-1111-4111-8111-000000000002',
            'meeting_date' => now()->subDays(2)->toDateString(),
            'discussion' => 'Diskusi',
            'decision' => 'Keputusan',
            'status' => MomStatus::DRAFT,
            'written_by' => 'acc-tw-0091',
        ]);

        $this->postChange($this->body(['momId' => Mom::where('meeting_id', '11111111-1111-4111-8111-000000000002')->value('id')]))
            ->assertStatus(409)->assertJsonPath('code', 'MOM_NOT_FINAL');
    }

    public function test_inactive_project_is_409(): void
    {
        $meeting = Meeting::create([
            'project_id' => 'PRJ-2026-015',
            'title' => 'Rapat proyek nonaktif',
            'scheduled_at' => now(),
            'location' => null,
            'status' => 'DONE',
            'created_by' => 'acc-pm-0007',
        ]);
        $mom = Mom::create([
            'meeting_id' => $meeting->id,
            'meeting_date' => now()->toDateString(),
            'discussion' => 'Diskusi',
            'decision' => 'Keputusan',
            'status' => MomStatus::FINAL,
            'written_by' => 'acc-tw-0091',
        ]);

        $this->postChange($this->body(['momId' => $mom->id]))
            ->assertStatus(409)->assertJsonPath('code', 'PROJECT_INACTIVE');
    }

    public function test_without_token_is_401(): void
    {
        $this->postJson('/api/v1/change-requests', $this->body())->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_tw_and_non_member_are_403(): void
    {
        $this->postChange($this->body(), 'acc-tw-0091')->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
        $this->postChange($this->body(), 'acc-tw-0092')->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_mom_is_404(): void
    {
        $this->postChange($this->body(['momId' => '22222222-2222-4222-8222-999999999999']))
            ->assertStatus(404)->assertJsonPath('code', 'MOM_NOT_FOUND');
    }
}