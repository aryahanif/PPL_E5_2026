<?php

namespace Tests\Feature;

use App\Enums\MomStatus;
use App\Models\ActionItem;
use App\Models\MeetingParticipant;
use App\Models\Mom;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** TW MoM editor, MoM view and TL review pages (US-2, US-3; UI-K05-07). */
class MomPagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const SCHEDULED = '11111111-1111-4111-8111-000000000001';

    private const DONE_NO_MOM = '11111111-1111-4111-8111-000000000002';

    private const DONE_WITH_MOM = '11111111-1111-4111-8111-000000000003';

    private const FINAL_MOM = '22222222-2222-4222-8222-000000000003';

    private const PARTICIPANT_TW = '44444444-4444-4444-8444-000002000003';

    private function login(string $accountId): void
    {
        $this->post('/dev-login', ['accountId' => $accountId]);
    }

    /** The mock guard remembers the first user of a test; forget it to act as someone else. */
    private function switchTo(string $accountId): void
    {
        $this->app['auth']->forgetGuards();
        $this->login($accountId);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'meetingDate' => now()->subDays(2)->toDateString(),
            'discussion' => 'Progres modul login 70%',
            'decision' => 'Server uji dipindah',
            'changeSummary' => '',
            'attendance' => [self::PARTICIPANT_TW],
            'actionItems' => [
                ['description' => 'Siapkan server uji baru', 'picAccountId' => 'acc-tl-0042', 'dueDate' => now()->addDays(3)->toDateString()],
                ['description' => 'Perbarui dokumen kebutuhan', 'picAccountId' => 'acc-tw-0091', 'dueDate' => now()->addDays(5)->toDateString()],
            ],
            'action' => 'draft',
        ];
    }

    private function mom(MomStatus $status, ?string $note = null): Mom
    {
        return Mom::create([
            'meeting_id' => self::DONE_NO_MOM,
            'meeting_date' => now()->subDays(2)->toDateString(),
            'discussion' => 'Bahas modul login',
            'decision' => 'Lanjutkan pengembangan',
            'status' => $status,
            'written_by' => 'acc-tw-0091',
            'review_note' => $note,
        ]);
    }

    // ---- Meeting detail -> MoM links

    public function test_tw_sees_write_mom_on_done_meeting_without_mom(): void
    {
        $this->login('acc-tw-0091');

        $this->get('/meetings/'.self::DONE_NO_MOM)->assertOk()
            ->assertSee('Tulis MoM')
            ->assertSee('/meetings/'.self::DONE_NO_MOM.'/mom/create', false);
    }

    public function test_pm_does_not_see_write_mom_button(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/meetings/'.self::DONE_NO_MOM)->assertOk()->assertDontSee('Tulis MoM');
    }

    public function test_meeting_with_mom_shows_view_link_and_badge(): void
    {
        $this->login('acc-tw-0091');

        $this->get('/meetings/'.self::DONE_WITH_MOM)->assertOk()
            ->assertSee('Lihat MoM')
            ->assertSee('/moms/'.self::FINAL_MOM, false)
            ->assertDontSee('Tulis MoM');
    }

    public function test_scheduled_meeting_has_no_write_mom_button(): void
    {
        $this->login('acc-tw-0091');

        $this->get('/meetings/'.self::SCHEDULED)->assertOk()->assertDontSee('Tulis MoM');
    }

    // ---- Editor (TW)

    public function test_tw_sees_editor_with_wi05_sections(): void
    {
        $this->login('acc-tw-0091');

        $this->get('/meetings/'.self::DONE_NO_MOM.'/mom/create')->assertOk()
            ->assertSee(['Informasi Rapat', 'Kehadiran', 'Pembahasan', 'Keputusan', 'Perubahan / Penyesuaian', 'Action Item'])
            ->assertSee(['Simpan Draft', 'Ajukan Review'])
            ->assertSee('value="'.self::PARTICIPANT_TW.'"', false)
            ->assertSee('Perwakilan Klien');
    }

    public function test_pm_and_tl_get_403_on_editor(): void
    {
        $this->login('acc-pm-0007');
        $this->get('/meetings/'.self::DONE_NO_MOM.'/mom/create')->assertForbidden();
        $this->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form())->assertForbidden();

        $this->switchTo('acc-tl-0042');
        $this->get('/meetings/'.self::DONE_NO_MOM.'/mom/create')->assertForbidden();
        $this->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form())->assertForbidden();

        $this->assertSame(0, Mom::where('meeting_id', self::DONE_NO_MOM)->count());
    }

    public function test_non_member_is_rejected(): void
    {
        $this->login('acc-tw-0092');

        $this->get('/meetings/'.self::DONE_NO_MOM.'/mom/create')->assertForbidden();
        $this->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form())->assertForbidden();
        $this->get('/moms/'.self::FINAL_MOM)->assertForbidden();
        $this->get('/moms/'.self::FINAL_MOM.'/edit')->assertForbidden();
        $this->patch('/moms/'.self::FINAL_MOM.'/status', ['status' => 'FINAL'])->assertForbidden();
    }

    public function test_editor_for_meeting_that_is_not_done_is_409(): void
    {
        $this->login('acc-tw-0091');

        $this->get('/meetings/'.self::SCHEDULED.'/mom/create')->assertStatus(409);
        $this->post('/meetings/'.self::SCHEDULED.'/mom', $this->form())->assertStatus(409);
    }

    public function test_editor_for_meeting_with_mom_redirects_to_view(): void
    {
        $this->login('acc-tw-0091');

        $this->get('/meetings/'.self::DONE_WITH_MOM.'/mom/create')->assertRedirect('/moms/'.self::FINAL_MOM);
        $this->post('/meetings/'.self::DONE_WITH_MOM.'/mom', $this->form())->assertStatus(409);
    }

    public function test_tw_saves_draft_with_two_todo_items_and_attendance(): void
    {
        $this->login('acc-tw-0091');

        $response = $this->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form());

        $mom = Mom::where('meeting_id', self::DONE_NO_MOM)->sole();
        $response->assertRedirect("/moms/{$mom->id}");

        $this->assertSame('DRAFT', $mom->status->value);
        $this->assertSame('acc-tw-0091', $mom->written_by);
        $this->assertNull($mom->change_summary);
        $this->assertSame(2, $mom->actionItems()->count());
        $this->assertSame(['TODO'], $mom->actionItems->pluck('status')->map->value->unique()->all());
        $this->assertTrue(MeetingParticipant::find(self::PARTICIPANT_TW)->attended);

        $row = StatusHistory::where('entity_id', $mom->id)->sole();
        $this->assertNull($row->from_status);
        $this->assertSame('DRAFT', $row->to_status);

        $this->get("/moms/{$mom->id}")->assertOk()
            ->assertSee('MoM tersimpan sebagai draft')
            ->assertSee('Siapkan server uji baru')
            ->assertSee('Perbarui dokumen kebutuhan');
    }

    public function test_tw_submits_for_review_in_one_step(): void
    {
        $this->login('acc-tw-0091');

        $this->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form(['action' => 'submit']));

        $mom = Mom::where('meeting_id', self::DONE_NO_MOM)->sole();
        $this->assertSame('IN_REVIEW', $mom->status->value);
        $this->assertEquals(['DRAFT', 'IN_REVIEW'], StatusHistory::where('entity_id', $mom->id)->pluck('to_status')->sort()->values()->all());
    }

    public function test_save_with_empty_fields_returns_indonesian_errors_and_keeps_input(): void
    {
        $this->login('acc-tw-0091');
        $url = '/meetings/'.self::DONE_NO_MOM.'/mom/create';

        $this->from($url)
            ->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form([
                'meetingDate' => '',
                'discussion' => '',
                'decision' => '',
                'actionItems' => [['description' => '', 'picAccountId' => '', 'dueDate' => '']],
            ]))
            ->assertRedirect($url)
            ->assertSessionHasErrors([
                'meetingDate' => 'Tanggal rapat wajib diisi',
                'discussion' => 'Pembahasan wajib diisi',
                'decision' => 'Keputusan wajib diisi',
                'actionItems.0.description' => 'Deskripsi action item wajib diisi',
                'actionItems.0.picAccountId' => 'PIC wajib dipilih',
                'actionItems.0.dueDate' => 'Tenggat wajib diisi',
            ]);

        $this->assertSame(0, Mom::where('meeting_id', self::DONE_NO_MOM)->count());
    }

    public function test_editor_shows_errors_and_old_input_after_failed_save(): void
    {
        $this->login('acc-tw-0091');
        $url = '/meetings/'.self::DONE_NO_MOM.'/mom/create';

        $this->from($url)->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form([
            'discussion' => '',
            'decision' => 'Keputusan yang tetap tersimpan di form',
        ]));

        $this->get($url)->assertOk()
            ->assertSee('Pembahasan wajib diisi')
            ->assertSee('Keputusan yang tetap tersimpan di form')
            ->assertSee('Siapkan server uji baru');
    }

    public function test_due_date_before_meeting_date_is_rejected(): void
    {
        $this->login('acc-tw-0091');

        $this->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form([
            'actionItems' => [['description' => 'Tugas', 'picAccountId' => 'acc-tl-0042', 'dueDate' => now()->subDays(10)->toDateString()]],
        ]))->assertSessionHasErrors(['actionItems.0.dueDate' => 'Tenggat tidak boleh sebelum tanggal rapat']);
    }

    public function test_pic_outside_project_is_rejected(): void
    {
        $this->login('acc-tw-0091');

        $this->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form([
            'actionItems' => [['description' => 'Tugas', 'picAccountId' => 'acc-tw-0092', 'dueDate' => now()->addDay()->toDateString()]],
        ]))->assertSessionHasErrors('actionItems.0.picAccountId');

        $this->assertSame(0, Mom::where('meeting_id', self::DONE_NO_MOM)->count());
    }

    public function test_save_without_attendance_or_action_items_is_allowed(): void
    {
        $this->login('acc-tw-0091');

        $this->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form(['attendance' => [], 'actionItems' => []]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Mom::where('meeting_id', self::DONE_NO_MOM)->sole()->actionItems()->count());
    }

    public function test_tw_edits_draft_updates_removes_and_adds_rows(): void
    {
        $this->login('acc-tw-0091');

        $mom = $this->mom(MomStatus::DRAFT);
        $keep = $mom->actionItems()->create(['description' => 'Lama', 'pic_account_id' => 'acc-tl-0042', 'due_date' => now()->addDays(2)->toDateString(), 'status' => 'TODO']);
        $drop = $mom->actionItems()->create(['description' => 'Dihapus', 'pic_account_id' => 'acc-tl-0042', 'due_date' => now()->addDays(2)->toDateString(), 'status' => 'TODO']);

        $this->get("/moms/{$mom->id}/edit")->assertOk()->assertSee('Lama')->assertSee('Dihapus');

        $this->put("/moms/{$mom->id}", $this->form([
            'discussion' => 'Diubah',
            'actionItems' => [
                ['actionItemId' => $keep->id, 'description' => 'Lama diperbarui', 'picAccountId' => 'acc-tw-0091', 'dueDate' => now()->addDays(4)->toDateString()],
                ['description' => 'Baru', 'picAccountId' => 'acc-pm-0007', 'dueDate' => now()->addDays(6)->toDateString()],
            ],
        ]))->assertRedirect("/moms/{$mom->id}");

        $this->assertSame('Diubah', $mom->fresh()->discussion);
        $this->assertNull(ActionItem::find($drop->id));
        $this->assertSame('Lama diperbarui', $keep->fresh()->description);
        $this->assertSame(2, $mom->actionItems()->count());
    }

    public function test_revision_shows_review_note_and_tw_resubmits(): void
    {
        $this->login('acc-tw-0091');
        $mom = $this->mom(MomStatus::REVISION, 'Tambahkan detail keputusan server');

        $this->get("/moms/{$mom->id}/edit")->assertOk()
            ->assertSee('Catatan revisi dari TL')
            ->assertSee('Tambahkan detail keputusan server');

        $this->put("/moms/{$mom->id}", $this->form(['action' => 'submit']))->assertRedirect("/moms/{$mom->id}");

        $this->assertSame('IN_REVIEW', $mom->fresh()->status->value);
    }

    public function test_editor_is_closed_for_in_review_and_final(): void
    {
        $this->login('acc-tw-0091');
        $mom = $this->mom(MomStatus::IN_REVIEW);

        $this->get("/moms/{$mom->id}/edit")->assertRedirect("/moms/{$mom->id}");
        $this->put("/moms/{$mom->id}", $this->form())->assertStatus(409);

        $this->get('/moms/'.self::FINAL_MOM.'/edit')->assertRedirect('/moms/'.self::FINAL_MOM);
        $this->put('/moms/'.self::FINAL_MOM, $this->form())->assertStatus(409);
    }

    public function test_non_tw_cannot_open_or_save_edit(): void
    {
        $this->login('acc-pm-0007');
        $mom = $this->mom(MomStatus::DRAFT);

        $this->get("/moms/{$mom->id}/edit")->assertForbidden();
        $this->put("/moms/{$mom->id}", $this->form())->assertForbidden();
    }

    // ---- View and review

    public function test_tw_sees_edit_button_on_draft_and_revision_only(): void
    {
        $this->login('acc-tw-0091');
        $draft = $this->mom(MomStatus::DRAFT);

        $this->get("/moms/{$draft->id}")->assertOk()->assertSee("/moms/{$draft->id}/edit", false);
        $this->get('/moms/'.self::FINAL_MOM)->assertOk()->assertDontSee('/edit', false);
    }

    public function test_final_mom_view_shows_all_content(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/moms/'.self::FINAL_MOM)->assertOk()
            ->assertSee('FINAL')
            ->assertSee('Progres modul login 70%')
            ->assertSee('Server uji dipindah ke lingkungan baru.')
            ->assertSee(['Siapkan server uji baru', 'Perbarui dokumen kebutuhan modul login', 'Konfirmasi jadwal demo ke klien'])
            ->assertSee(['TW Contoh', 'TL Contoh', 'Perwakilan Klien'])
            ->assertDontSee(['Finalkan', 'Minta Revisi', '/edit']);
    }

    public function test_tl_sees_review_buttons_only_when_in_review(): void
    {
        $this->login('acc-tl-0042');
        $inReview = $this->mom(MomStatus::IN_REVIEW);

        $this->get("/moms/{$inReview->id}")->assertOk()->assertSee(['Finalkan', 'Minta Revisi']);
        $this->get('/moms/'.self::FINAL_MOM)->assertOk()->assertDontSee(['Finalkan', 'Minta Revisi']);
    }

    public function test_tl_does_not_see_review_buttons_on_draft(): void
    {
        $this->login('acc-tl-0042');
        $draft = $this->mom(MomStatus::DRAFT);

        $this->get("/moms/{$draft->id}")->assertOk()->assertDontSee(['Finalkan', 'Minta Revisi']);
    }

    public function test_tl_finalizes_in_review_mom(): void
    {
        $this->login('acc-tl-0042');
        $mom = $this->mom(MomStatus::IN_REVIEW);

        $this->patch("/moms/{$mom->id}/status", ['status' => 'FINAL'])->assertRedirect("/moms/{$mom->id}");

        $mom->refresh();
        $this->assertSame('FINAL', $mom->status->value);
        $this->assertSame('acc-tl-0042', $mom->reviewed_by);
        $this->assertNotNull($mom->finalized_at);

        $row = StatusHistory::where('entity_id', $mom->id)->sole();
        $this->assertSame('IN_REVIEW', $row->from_status);
        $this->assertSame('FINAL', $row->to_status);

        $this->get("/moms/{$mom->id}")->assertSee('MoM berhasil difinalkan');
    }

    public function test_tl_request_revision_without_note_fails(): void
    {
        $this->login('acc-tl-0042');
        $mom = $this->mom(MomStatus::IN_REVIEW);

        $this->from("/moms/{$mom->id}")
            ->patch("/moms/{$mom->id}/status", ['status' => 'REVISION', 'reviewNote' => ''])
            ->assertRedirect("/moms/{$mom->id}")
            ->assertSessionHasErrors(['reviewNote' => 'Catatan revisi wajib diisi']);

        $this->assertSame('IN_REVIEW', $mom->fresh()->status->value);
    }

    public function test_tl_request_revision_with_note(): void
    {
        $this->login('acc-tl-0042');
        $mom = $this->mom(MomStatus::IN_REVIEW);

        $this->patch("/moms/{$mom->id}/status", ['status' => 'REVISION', 'reviewNote' => 'Lengkapi keputusan'])
            ->assertRedirect("/moms/{$mom->id}");

        $mom->refresh();
        $this->assertSame('REVISION', $mom->status->value);
        $this->assertSame('Lengkapi keputusan', $mom->review_note);
        $this->assertSame('Lengkapi keputusan', StatusHistory::where('entity_id', $mom->id)->sole()->note);
    }

    public function test_review_of_mom_not_in_review_is_409(): void
    {
        $this->login('acc-tl-0042');
        $draft = $this->mom(MomStatus::DRAFT);

        $this->patch("/moms/{$draft->id}/status", ['status' => 'FINAL'])->assertStatus(409);
        $this->patch('/moms/'.self::FINAL_MOM.'/status', ['status' => 'REVISION', 'reviewNote' => 'x'])->assertStatus(409);
    }

    public function test_invalid_review_status_is_rejected(): void
    {
        $this->login('acc-tl-0042');
        $mom = $this->mom(MomStatus::IN_REVIEW);

        $this->patch("/moms/{$mom->id}/status", ['status' => 'DRAFT'])->assertSessionHasErrors('status');
        $this->assertSame('IN_REVIEW', $mom->fresh()->status->value);
    }

    public function test_tw_cannot_see_or_use_review_actions(): void
    {
        $this->login('acc-tw-0091');
        $mom = $this->mom(MomStatus::IN_REVIEW);

        $this->get("/moms/{$mom->id}")->assertOk()->assertDontSee(['Finalkan', 'Minta Revisi']);
        $this->patch("/moms/{$mom->id}/status", ['status' => 'FINAL'])->assertForbidden();
        $this->assertSame('IN_REVIEW', $mom->fresh()->status->value);
    }

    public function test_pm_cannot_see_or_use_review_actions(): void
    {
        $this->login('acc-pm-0007');
        $mom = $this->mom(MomStatus::IN_REVIEW);

        $this->get("/moms/{$mom->id}")->assertOk()->assertDontSee(['Finalkan', 'Minta Revisi']);
        $this->patch("/moms/{$mom->id}/status", ['status' => 'FINAL'])->assertForbidden();
    }

    public function test_unknown_mom_is_404(): void
    {
        $this->login('acc-tw-0091');

        $this->get('/moms/22222222-2222-4222-8222-999999999999')->assertNotFound();
    }

    public function test_full_flow_write_submit_revise_resubmit_finalize(): void
    {
        $this->login('acc-tw-0091');
        $this->post('/meetings/'.self::DONE_NO_MOM.'/mom', $this->form(['action' => 'submit']));
        $mom = Mom::where('meeting_id', self::DONE_NO_MOM)->sole();

        $this->switchTo('acc-tl-0042');
        $this->patch("/moms/{$mom->id}/status", ['status' => 'REVISION', 'reviewNote' => 'Tambah detail'])->assertRedirect();

        $this->switchTo('acc-tw-0091');
        $this->put("/moms/{$mom->id}", $this->form(['action' => 'submit']));

        $this->switchTo('acc-tl-0042');
        $this->patch("/moms/{$mom->id}/status", ['status' => 'FINAL'])->assertRedirect();

        $this->assertSame('FINAL', $mom->fresh()->status->value);
        // Rows can share one timestamp, so compare as a sorted list instead of by time.
        $this->assertEquals(
            ['DRAFT', 'FINAL', 'IN_REVIEW', 'IN_REVIEW', 'REVISION'],
            StatusHistory::where('entity_id', $mom->id)->pluck('to_status')->sort()->values()->all(),
        );
    }
}
