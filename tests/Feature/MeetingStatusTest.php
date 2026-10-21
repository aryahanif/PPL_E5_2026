<?php

namespace Tests\Feature;

use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-01 PATCH /api/v1/meetings/{meetingId}/status (updateMeetingStatus). */
class MeetingStatusTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const SCHEDULED = '11111111-1111-4111-8111-000000000001';

    private const DONE = '11111111-1111-4111-8111-000000000002';

    private function changeStatus(string $id, string $status = 'DONE', string $account = 'acc-pm-0007')
    {
        return $this->withToken("mock-{$account}")->patchJson("/api/v1/meetings/{$id}/status", ['status' => $status]);
    }

    public function test_pm_marks_scheduled_meeting_done_and_history_is_written(): void
    {
        $this->changeStatus(self::SCHEDULED)->assertOk()
            ->assertJsonPath('meetingId', self::SCHEDULED)
            ->assertJsonPath('status', 'DONE');

        $row = StatusHistory::where('entity_id', self::SCHEDULED)->sole();
        $this->assertSame('MEETING', $row->entity_type);
        $this->assertSame('SCHEDULED', $row->from_status);
        $this->assertSame('DONE', $row->to_status);
        $this->assertSame('acc-pm-0007', $row->changed_by);
    }

    public function test_pm_cancels_scheduled_meeting(): void
    {
        $this->changeStatus(self::SCHEDULED, 'CANCELLED')->assertOk()->assertJsonPath('status', 'CANCELLED');
    }

    public function test_invalid_status_value_is_400(): void
    {
        $this->changeStatus(self::SCHEDULED, 'SCHEDULED')->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: status']);
    }

    public function test_without_token_is_401(): void
    {
        $this->patchJson('/api/v1/meetings/'.self::SCHEDULED.'/status', ['status' => 'DONE'])
            ->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_tl_is_403(): void
    {
        $this->changeStatus(self::SCHEDULED, 'DONE', 'acc-tl-0042')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Hanya PM yang dapat mengubah status rapat']);
    }

    public function test_tw_is_403(): void
    {
        $this->changeStatus(self::SCHEDULED, 'DONE', 'acc-tw-0091')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_non_member_is_403(): void
    {
        $this->changeStatus(self::SCHEDULED, 'DONE', 'acc-tw-0092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_meeting_is_404(): void
    {
        $this->changeStatus('11111111-1111-4111-8111-999999999999')->assertStatus(404)
            ->assertJsonPath('code', 'MEETING_NOT_FOUND');
    }

    public function test_transition_from_done_is_409(): void
    {
        $this->changeStatus(self::DONE, 'CANCELLED')->assertStatus(409)
            ->assertExactJson(['code' => 'INVALID_STATUS_TRANSITION', 'message' => 'Status DONE tidak dapat diubah ke CANCELLED']);

        $this->assertSame(0, StatusHistory::where('entity_id', self::DONE)->count());
    }
}
