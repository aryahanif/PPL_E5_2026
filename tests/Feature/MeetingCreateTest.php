<?php

namespace Tests\Feature;

use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-01 POST /api/v1/projects/{projectId}/meetings (createMeeting). */
class MeetingCreateTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function body(array $override = []): array
    {
        return $override + [
            'title' => 'Weekly Meeting Minggu 5',
            'scheduledAt' => '2026-10-26T02:00:00Z',
            'location' => 'Ruang Rapat 2',
            'participants' => [
                ['participantType' => 'INTERNAL', 'accountId' => 'acc-pm-0007'],
                ['participantType' => 'INTERNAL', 'accountId' => 'acc-tl-0042'],
                ['participantType' => 'CLIENT', 'displayName' => 'Perwakilan Klien'],
            ],
        ];
    }

    private function postMeeting(array $body, string $projectId = 'PRJ-2026-014', string $account = 'acc-pm-0007')
    {
        return $this->withToken("mock-{$account}")->postJson("/api/v1/projects/{$projectId}/meetings", $body);
    }

    public function test_pm_creates_meeting_and_history_row(): void
    {
        $response = $this->postMeeting($this->body())->assertCreated()
            ->assertJsonStructure([
                'meetingId', 'projectId', 'title', 'scheduledAt', 'location', 'status',
                'participants' => [['participantId', 'participantType', 'accountId', 'displayName', 'attended']],
                'momId', 'momStatus', 'createdAt', 'updatedAt',
            ])
            ->assertJsonPath('projectId', 'PRJ-2026-014')
            ->assertJsonPath('status', 'SCHEDULED')
            ->assertJsonPath('scheduledAt', '2026-10-26T02:00:00Z')
            ->assertJsonPath('momId', null)
            ->assertJsonPath('momStatus', null)
            ->assertJsonCount(3, 'participants');

        $names = $response->json('participants.*.displayName');
        sort($names);
        $this->assertSame(['PM Contoh', 'Perwakilan Klien', 'TL Contoh'], $names);

        $history = StatusHistory::where('entity_id', $response->json('meetingId'))->get();
        $this->assertCount(1, $history);
        $this->assertNull($history[0]->from_status);
        $this->assertSame('SCHEDULED', $history[0]->to_status);
        $this->assertSame('acc-pm-0007', $history[0]->changed_by);
    }

    public function test_validation_errors_are_400(): void
    {
        $cases = [
            'title' => $this->body(['title' => '']),
            'scheduledAt' => $this->body(['scheduledAt' => 'bukan-tanggal']),
            'location' => $this->body(['location' => str_repeat('x', 201)]),
            'participants' => $this->body(['participants' => []]),
            'participants.0.accountId' => $this->body(['participants' => [['participantType' => 'INTERNAL']]]),
            'participants.0.displayName' => $this->body(['participants' => [['participantType' => 'CLIENT']]]),
            'participants.0.accountId, participants.1.accountId' => $this->body(['participants' => [
                ['participantType' => 'INTERNAL', 'accountId' => 'acc-pm-0007'],
                ['participantType' => 'INTERNAL', 'accountId' => 'acc-pm-0007'],
            ]]),
        ];

        foreach ($cases as $field => $body) {
            $this->postMeeting($body)->assertStatus(400)
                ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: '.$field]);
        }
    }

    public function test_internal_participant_outside_project_is_400(): void
    {
        $this->postMeeting($this->body(['participants' => [['participantType' => 'INTERNAL', 'accountId' => 'acc-tw-0092']]]))
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_without_token_is_401(): void
    {
        $this->postJson('/api/v1/projects/PRJ-2026-014/meetings', $this->body())->assertStatus(401)
            ->assertExactJson(['code' => 'UNAUTHORIZED', 'message' => 'Token tidak ada atau tidak sah']);
    }

    public function test_tl_is_403(): void
    {
        $this->postMeeting($this->body(), account: 'acc-tl-0042')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Hanya PM yang dapat menjadwalkan rapat']);
    }

    public function test_tw_is_403(): void
    {
        $this->postMeeting($this->body(), account: 'acc-tw-0091')->assertStatus(403)
            ->assertJsonPath('message', 'Hanya PM yang dapat menjadwalkan rapat');
    }

    public function test_non_member_is_403(): void
    {
        $this->postMeeting($this->body(), account: 'acc-tw-0092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_project_is_404(): void
    {
        $this->postMeeting($this->body(), 'PRJ-2026-999')->assertStatus(404)
            ->assertExactJson(['code' => 'PROJECT_NOT_FOUND', 'message' => 'Proyek PRJ-2026-999 tidak ditemukan']);
    }

    public function test_inactive_project_is_409(): void
    {
        $this->postMeeting($this->body(), 'PRJ-2026-015')->assertStatus(409)
            ->assertJsonPath('code', 'PROJECT_INACTIVE');
    }
}
