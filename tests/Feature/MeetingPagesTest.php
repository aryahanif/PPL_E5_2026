<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PM screens for US-1: schedule meeting, meeting detail, mark done/cancelled (UI-K05-01). */
class MeetingPagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PROJECT = 'PRJ-2026-014';

    private const INACTIVE = 'PRJ-2026-015';

    private const SCHEDULED = '11111111-1111-4111-8111-000000000001';

    private const DONE = '11111111-1111-4111-8111-000000000002';

    private function login(string $accountId): void
    {
        $this->post('/dev-login', ['accountId' => $accountId]);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Weekly Meeting Minggu 5',
            'date' => now()->addDays(3)->toDateString(),
            'time' => '09:00',
            'location' => 'Ruang Rapat 1',
            'participants' => ['acc-pm-0007', 'acc-tw-0091'],
            'clientNames' => 'Perwakilan Klien, Perwakilan Klien',
        ], $overrides);
    }

    public function test_pm_sees_schedule_button_and_form(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/projects/'.self::PROJECT.'/meetings')->assertOk()->assertSee('Jadwalkan Rapat');

        $this->get('/projects/'.self::PROJECT.'/meetings/create')->assertOk()
            ->assertSee('Judul rapat')
            ->assertSee(['value="acc-pm-0007"', 'value="acc-tl-0042"', 'value="acc-tw-0091"'], false)
            ->assertSee(['PM Contoh', 'TL Contoh', 'TW Contoh']);
    }

    public function test_status_tab_filters_meeting_list(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/projects/'.self::PROJECT.'/meetings?status=DONE')->assertOk()
            ->assertSee(['Weekly Meeting Minggu 3', 'Weekly Meeting Minggu 2'])
            ->assertDontSee('Weekly Meeting Minggu 4');
    }

    public function test_tw_does_not_see_schedule_button_and_form_is_403(): void
    {
        $this->login('acc-tw-0091');

        $this->get('/projects/'.self::PROJECT.'/meetings')->assertOk()->assertDontSee('Jadwalkan Rapat');
        $this->get('/projects/'.self::PROJECT.'/meetings/create')->assertForbidden();
    }

    public function test_pm_schedules_meeting_with_internal_and_client_participants(): void
    {
        $this->login('acc-pm-0007');

        $response = $this->post('/projects/'.self::PROJECT.'/meetings', $this->form());

        $meeting = Meeting::where('title', 'Weekly Meeting Minggu 5')->sole();
        $response->assertRedirect("/meetings/{$meeting->id}");

        $this->assertSame('SCHEDULED', $meeting->status->value);
        $this->assertSame('acc-pm-0007', $meeting->created_by);
        $this->assertSame('02:00', $meeting->scheduled_at->format('H:i')); // 09:00 WIB = 02:00 UTC
        $this->assertSame(3, $meeting->participants()->count()); // duplicate client name is dropped

        $row = StatusHistory::where('entity_id', $meeting->id)->sole();
        $this->assertNull($row->from_status);
        $this->assertSame('SCHEDULED', $row->to_status);

        $this->get("/meetings/{$meeting->id}")->assertOk()
            ->assertSee('Rapat berhasil dijadwalkan')
            ->assertSee('PM Contoh')
            ->assertSee('Perwakilan Klien');
    }

    public function test_schedule_with_empty_fields_shows_indonesian_errors(): void
    {
        $this->login('acc-pm-0007');

        $this->from('/projects/'.self::PROJECT.'/meetings/create')
            ->post('/projects/'.self::PROJECT.'/meetings', $this->form(['title' => '', 'date' => '', 'time' => '', 'participants' => []]))
            ->assertRedirect('/projects/'.self::PROJECT.'/meetings/create')
            ->assertSessionHasErrors([
                'title' => 'Judul rapat wajib diisi',
                'date' => 'Tanggal rapat wajib diisi',
                'time' => 'Jam rapat wajib diisi',
                'participants' => 'Minimal satu peserta wajib dipilih',
            ]);

        $this->assertSame(0, Meeting::where('title', '')->count());
    }

    public function test_schedule_with_non_member_participant_is_rejected(): void
    {
        $this->login('acc-pm-0007');

        $this->post('/projects/'.self::PROJECT.'/meetings', $this->form(['participants' => ['acc-tw-0092']]))
            ->assertSessionHasErrors(['participants.0' => 'Peserta harus anggota proyek']);
    }

    public function test_inactive_project_shows_message_and_post_is_409(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/projects/'.self::INACTIVE.'/meetings/create')->assertOk()
            ->assertSee('Proyek tidak ditemukan atau tidak aktif, pengajuan rapat ditolak')
            ->assertDontSee('Simpan Jadwal');

        $this->post('/projects/'.self::INACTIVE.'/meetings', $this->form(['participants' => ['acc-pm-0007']]))
            ->assertStatus(409);
    }

    public function test_tw_cannot_post_schedule(): void
    {
        $this->login('acc-tw-0091');

        $this->post('/projects/'.self::PROJECT.'/meetings', $this->form())->assertForbidden();
    }

    public function test_pm_sees_status_buttons_on_scheduled_meeting_and_marks_done(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/meetings/'.self::SCHEDULED)->assertOk()
            ->assertSee('Weekly Meeting Minggu 4')
            ->assertSee('Tandai Selesai')
            ->assertSee('Batalkan Rapat');

        $this->patch('/meetings/'.self::SCHEDULED.'/status', ['status' => 'DONE'])
            ->assertRedirect('/meetings/'.self::SCHEDULED);

        $this->assertSame('DONE', Meeting::find(self::SCHEDULED)->status->value);

        $this->get('/meetings/'.self::SCHEDULED)->assertOk()
            ->assertSee('Status rapat diubah menjadi DONE')
            ->assertDontSee('Tandai Selesai');
    }

    public function test_pm_cancels_scheduled_meeting(): void
    {
        $this->login('acc-pm-0007');

        $this->patch('/meetings/'.self::SCHEDULED.'/status', ['status' => 'CANCELLED'])
            ->assertRedirect('/meetings/'.self::SCHEDULED);

        $this->assertSame('CANCELLED', Meeting::find(self::SCHEDULED)->status->value);
    }

    public function test_done_meeting_cannot_change_status_again(): void
    {
        $this->login('acc-pm-0007');

        $this->patch('/meetings/'.self::DONE.'/status', ['status' => 'CANCELLED'])->assertStatus(409);
    }

    public function test_tl_sees_detail_without_buttons_and_patch_is_403(): void
    {
        $this->login('acc-tl-0042');

        $this->get('/meetings/'.self::SCHEDULED)->assertOk()->assertDontSee('Tandai Selesai');
        $this->patch('/meetings/'.self::SCHEDULED.'/status', ['status' => 'DONE'])->assertForbidden();
    }

    public function test_non_member_meeting_detail_is_403(): void
    {
        $this->login('acc-tw-0092');

        $this->get('/meetings/'.self::SCHEDULED)->assertForbidden();
    }

    public function test_unknown_meeting_is_404(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/meetings/11111111-1111-4111-8111-999999999999')->assertNotFound();
    }
}
