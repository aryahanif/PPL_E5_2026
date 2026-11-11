<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-01 GET /api/v1/projects/{projectId}/meetings (reference endpoint). */
class MeetingListTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function list(string $projectId = '019a0000-d101-7000-8000-000000000014', string $account = '019a0000-f101-7000-8000-000000000007', string $query = '')
    {
        return $this->withToken("mock-{$account}")->getJson("/api/v1/projects/{$projectId}/meetings{$query}");
    }

    public function test_member_gets_meetings_newest_first_without_wrapper(): void
    {
        $response = $this->list()->assertOk()->assertJsonCount(3);

        $response->assertJsonStructure([[
            'meetingId', 'projectId', 'title', 'scheduledAt', 'location', 'status',
            'participants' => [['participantId', 'participantType', 'accountId', 'displayName', 'attended']],
            'momId', 'momStatus', 'createdAt', 'updatedAt',
        ]]);

        $this->assertSame(
            ['Weekly Meeting Minggu 4', 'Weekly Meeting Minggu 3', 'Weekly Meeting Minggu 2'],
            $response->json('*.title'),
        );

        // Meeting 3 has a FINAL MoM; internal names come from F-1, clients keep their stored name.
        $response->assertJsonPath('2.momId', '019a0000-2222-7222-8222-000000000003')
            ->assertJsonPath('2.momStatus', 'FINAL')
            ->assertJsonPath('0.momId', null);

        $participants = collect($response->json('0.participants'));
        $this->assertContains('PM Contoh', $participants->pluck('displayName'));
        $this->assertSame(
            ['participantType' => 'CLIENT', 'accountId' => null, 'displayName' => 'Perwakilan Klien'],
            collect($participants->firstWhere('participantType', 'CLIENT'))->only('participantType', 'accountId', 'displayName')->all(),
        );
    }

    public function test_status_filter(): void
    {
        $this->list(query: '?status=DONE')->assertOk()->assertJsonCount(2)
            ->assertJsonPath('*.status', ['DONE', 'DONE']);

        $this->list(query: '?status=CANCELLED')->assertOk()->assertExactJson([]);
    }

    public function test_invalid_status_is_400(): void
    {
        $this->list(query: '?status=FOO')->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: status']);
    }

    public function test_missing_or_unknown_token_is_401(): void
    {
        $this->getJson('/api/v1/projects/019a0000-d101-7000-8000-000000000014/meetings')->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');

        $this->list(account: 'acc-unknown')->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_non_member_is_403(): void
    {
        $this->list(account: '019a0000-f101-7000-8000-000000000092')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Anda bukan anggota proyek ini']);
    }

    public function test_unknown_project_is_404(): void
    {
        $this->list('019a0000-d101-7000-8000-000000000999')->assertStatus(404)
            ->assertExactJson(['code' => 'PROJECT_NOT_FOUND', 'message' => 'Proyek 019a0000-d101-7000-8000-000000000999 tidak ditemukan']);
    }
}
