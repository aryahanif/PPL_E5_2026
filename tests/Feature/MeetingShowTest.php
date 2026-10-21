<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-01 GET /api/v1/meetings/{meetingId} (getMeeting). */
class MeetingShowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const DONE_WITH_MOM = '11111111-1111-4111-8111-000000000003';

    private function show(string $id, string $account = 'acc-tw-0091')
    {
        return $this->withToken("mock-{$account}")->getJson("/api/v1/meetings/{$id}");
    }

    public function test_member_gets_detail_with_mom(): void
    {
        $this->show(self::DONE_WITH_MOM)->assertOk()
            ->assertJsonStructure([
                'meetingId', 'projectId', 'title', 'scheduledAt', 'location', 'status',
                'participants' => [['participantId', 'participantType', 'accountId', 'displayName', 'attended']],
                'momId', 'momStatus', 'createdAt', 'updatedAt',
            ])
            ->assertJsonPath('meetingId', self::DONE_WITH_MOM)
            ->assertJsonPath('momId', '22222222-2222-4222-8222-000000000003')
            ->assertJsonPath('momStatus', 'FINAL');
    }

    public function test_without_token_is_401(): void
    {
        $this->getJson('/api/v1/meetings/'.self::DONE_WITH_MOM)->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_non_member_is_403(): void
    {
        $this->show(self::DONE_WITH_MOM, 'acc-tw-0092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_meeting_is_404(): void
    {
        $this->show('11111111-1111-4111-8111-999999999999')->assertStatus(404)
            ->assertJsonPath('code', 'MEETING_NOT_FOUND');
    }
}
