<?php

namespace Tests\Feature;

use App\Models\ActionItem;
use App\Models\MeetingParticipant;
use App\Models\Mom;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-02 POST /meetings/{id}/mom (createMom) and PUT /moms/{id} (updateMom). */
class MomWriteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const SCHEDULED = '019a0000-1111-7111-8111-000000000001';

    private const DONE_NO_MOM = '019a0000-1111-7111-8111-000000000002';

    private const DONE_WITH_MOM = '019a0000-1111-7111-8111-000000000003';

    private const FINAL_MOM = '019a0000-2222-7222-8222-000000000003';

    private const PARTICIPANT_TW = '019a0000-4444-7444-8444-000002000003';

    private const PARTICIPANT_CLIENT = '019a0000-4444-7444-8444-000002000004';

    private function body(array $override = []): array
    {
        return $override + [
            'meetingDate' => now()->subDays(2)->toDateString(),
            'discussion' => 'Progres modul login 70%',
            'decision' => 'Server uji dipindah',
            'changeSummary' => null,
            'attendance' => [self::PARTICIPANT_TW],
            'actionItems' => [[
                'description' => 'Siapkan server uji baru',
                'picAccountId' => '019a0000-f101-7000-8000-000000000042',
                'dueDate' => now()->addDays(3)->toDateString(),
            ]],
        ];
    }

    private function create(array $body, string $meeting = self::DONE_NO_MOM, string $account = '019a0000-f101-7000-8000-000000000091')
    {
        return $this->withToken("mock-{$account}")->postJson("/api/v1/meetings/{$meeting}/mom", $body);
    }

    private function edit(string $momId, array $body, string $account = '019a0000-f101-7000-8000-000000000091')
    {
        return $this->withToken("mock-{$account}")->putJson("/api/v1/moms/{$momId}", $body);
    }

    public function test_tw_creates_draft_mom_with_todo_items_attendance_and_history(): void
    {
        $response = $this->create($this->body())->assertCreated()
            ->assertJsonPath('meetingId', self::DONE_NO_MOM)
            ->assertJsonPath('projectId', '019a0000-d101-7000-8000-000000000014')
            ->assertJsonPath('status', 'DRAFT')
            ->assertJsonPath('writtenBy', '019a0000-f101-7000-8000-000000000091')
            ->assertJsonPath('reviewedBy', null)
            ->assertJsonPath('finalizedAt', null)
            ->assertJsonCount(1, 'actionItems')
            ->assertJsonPath('actionItems.0.status', 'TODO')
            ->assertJsonPath('actionItems.0.picAccountId', '019a0000-f101-7000-8000-000000000042')
            ->assertJsonPath('actionItems.0.overdue', false);

        $momId = $response->json('momId');
        $row = StatusHistory::where('entity_id', $momId)->sole();
        $this->assertSame('MOM', $row->entity_type);
        $this->assertNull($row->from_status);
        $this->assertSame('DRAFT', $row->to_status);
        $this->assertSame('019a0000-f101-7000-8000-000000000091', $row->changed_by);

        $this->assertTrue(MeetingParticipant::find(self::PARTICIPANT_TW)->attended);
        $this->assertFalse(MeetingParticipant::find(self::PARTICIPANT_CLIENT)->attended);
    }

    public function test_create_with_empty_action_items_is_allowed(): void
    {
        $this->create($this->body(['actionItems' => []]))->assertCreated()->assertJsonCount(0, 'actionItems');
    }

    public function test_missing_required_fields_is_400(): void
    {
        $this->create([])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->create($this->body(['discussion' => '']))->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_due_date_before_meeting_date_is_400(): void
    {
        $body = $this->body();
        $body['actionItems'][0]['dueDate'] = now()->subDays(10)->toDateString();

        $this->create($body)->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_attendance_of_another_meeting_is_400(): void
    {
        $this->create($this->body(['attendance' => ['019a0000-4444-7444-8444-000003000001']]))
            ->assertStatus(400)->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: attendance']);
    }

    public function test_pic_outside_project_is_400(): void
    {
        $body = $this->body();
        $body['actionItems'][0]['picAccountId'] = '019a0000-f101-7000-8000-000000000092';

        $this->create($body)->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertSame(0, Mom::where('meeting_id', self::DONE_NO_MOM)->count());
    }

    public function test_without_token_is_401(): void
    {
        $this->postJson('/api/v1/meetings/'.self::DONE_NO_MOM.'/mom', $this->body())
            ->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_pm_is_403(): void
    {
        $this->create($this->body(), self::DONE_NO_MOM, '019a0000-f101-7000-8000-000000000007')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Hanya TW yang dapat menulis MoM']);
    }

    public function test_tl_is_403(): void
    {
        $this->create($this->body(), self::DONE_NO_MOM, '019a0000-f101-7000-8000-000000000042')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_non_member_is_403(): void
    {
        $this->create($this->body(), self::DONE_NO_MOM, '019a0000-f101-7000-8000-000000000092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_meeting_is_404(): void
    {
        $this->create($this->body(), '019a0000-1111-7111-8111-999999999999')->assertStatus(404)
            ->assertJsonPath('code', 'MEETING_NOT_FOUND');
    }

    public function test_meeting_not_done_is_409(): void
    {
        $this->create($this->body(), self::SCHEDULED)->assertStatus(409)->assertJsonPath('code', 'MEETING_NOT_DONE');
    }

    public function test_second_mom_for_a_meeting_is_409(): void
    {
        $this->create($this->body(), self::DONE_WITH_MOM)->assertStatus(409)->assertJsonPath('code', 'MOM_ALREADY_EXISTS');
    }

    public function test_update_replaces_content_and_syncs_action_items(): void
    {
        $created = $this->create($this->body([
            'actionItems' => [
                ['description' => 'Dipertahankan', 'picAccountId' => '019a0000-f101-7000-8000-000000000042', 'dueDate' => now()->addDays(3)->toDateString()],
                ['description' => 'Dihapus', 'picAccountId' => '019a0000-f101-7000-8000-000000000091', 'dueDate' => now()->addDays(4)->toDateString()],
            ],
        ]))->assertCreated();

        $momId = $created->json('momId');
        $keptId = collect($created->json('actionItems'))->firstWhere('description', 'Dipertahankan')['actionItemId'];

        $this->edit($momId, $this->body([
            'discussion' => 'Diskusi baru',
            'attendance' => [self::PARTICIPANT_CLIENT],
            'actionItems' => [
                ['actionItemId' => $keptId, 'description' => 'Diperbarui', 'picAccountId' => '019a0000-f101-7000-8000-000000000007', 'dueDate' => now()->addDays(5)->toDateString()],
                ['description' => 'Baru', 'picAccountId' => '019a0000-f101-7000-8000-000000000091', 'dueDate' => now()->addDays(6)->toDateString()],
            ],
        ]))->assertOk()
            ->assertJsonPath('momId', $momId)
            ->assertJsonPath('discussion', 'Diskusi baru')
            ->assertJsonCount(2, 'actionItems');

        $this->assertSame('Diperbarui', ActionItem::find($keptId)->description);
        $this->assertSame('019a0000-f101-7000-8000-000000000007', ActionItem::find($keptId)->pic_account_id);
        $this->assertSame(0, ActionItem::where('description', 'Dihapus')->count());
        $this->assertSame('TODO', ActionItem::where('description', 'Baru')->sole()->status->value);
        $this->assertFalse(MeetingParticipant::find(self::PARTICIPANT_TW)->attended);
        $this->assertTrue(MeetingParticipant::find(self::PARTICIPANT_CLIENT)->attended);
    }

    public function test_update_with_foreign_action_item_id_is_400(): void
    {
        $momId = $this->create($this->body())->json('momId');
        $body = $this->body();
        $body['actionItems'][0]['actionItemId'] = '019a0000-3333-7333-8333-000000000001';

        $this->edit($momId, $body)->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_update_invalid_body_is_400(): void
    {
        $momId = $this->create($this->body())->json('momId');

        $this->edit($momId, [])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_update_without_token_is_401(): void
    {
        $this->putJson('/api/v1/moms/'.self::FINAL_MOM, $this->body())->assertStatus(401);
    }

    public function test_update_by_tl_is_403(): void
    {
        $this->edit(self::FINAL_MOM, $this->body(), '019a0000-f101-7000-8000-000000000042')->assertStatus(403)
            ->assertJsonPath('message', 'Hanya TW yang dapat mengubah MoM');
    }

    public function test_update_by_non_member_is_403(): void
    {
        $this->edit(self::FINAL_MOM, $this->body(), '019a0000-f101-7000-8000-000000000092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_update_unknown_mom_is_404(): void
    {
        $this->edit('019a0000-2222-7222-8222-999999999999', $this->body())->assertStatus(404)
            ->assertJsonPath('code', 'MOM_NOT_FOUND');
    }

    public function test_update_final_mom_is_409(): void
    {
        $this->edit(self::FINAL_MOM, $this->body())->assertStatus(409)->assertJsonPath('code', 'MOM_NOT_EDITABLE');
    }
}
