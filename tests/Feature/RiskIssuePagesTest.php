<?php

namespace Tests\Feature;

use App\Models\RiskIssue;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Risk and issue pages (UI-K05-06). Seed: RISK OPEN with PIC PM, ISSUE IN_PROGRESS with PIC TL. */
class RiskIssuePagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PROJECT = 'PRJ-2026-014';

    private const INACTIVE_PROJECT = 'PRJ-2026-015';

    private const PM_RISK = '55555555-5555-4555-8555-000000000001';

    private const TL_ISSUE = '55555555-5555-4555-8555-000000000002';

    private function login(string $accountId): void
    {
        $this->post('/dev-login', ['accountId' => $accountId]);
    }

    private function url(string $query = ''): string
    {
        return '/projects/'.self::PROJECT.'/risk-issues'.$query;
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'type' => 'RISK',
            'title' => 'Anggota tim cuti panjang',
            'description' => 'Satu developer cuti dua minggu di tengah sprint.',
            'impactLevel' => 'MEDIUM',
            'mitigation' => '',
            'picAccountId' => 'acc-tl-0042',
            'sourceMomId' => '',
        ], $overrides);
    }

    // ---- List

    public function test_pm_sees_list_kpis_and_record_button(): void
    {
        $this->login('acc-pm-0007');

        $this->get($this->url())->assertOk()
            ->assertSee(['Klien terlambat memberi data uji', 'Server uji tidak dapat diakses'])
            ->assertSee(['Terbuka', 'Ditangani', 'Ditutup'])
            ->assertSee('Catat Risiko/Isu');
    }

    public function test_tw_sees_list_without_record_button(): void
    {
        $this->login('acc-tw-0091');

        $this->get($this->url())->assertOk()
            ->assertSee('Klien terlambat memberi data uji')
            ->assertDontSee('Catat Risiko/Isu');
    }

    public function test_status_and_type_filters_narrow_the_list(): void
    {
        $this->login('acc-pm-0007');

        $this->get($this->url('?status=IN_PROGRESS'))->assertOk()
            ->assertSee('Server uji tidak dapat diakses')
            ->assertDontSee('Klien terlambat memberi data uji');

        $this->get($this->url('?type=RISK'))->assertOk()
            ->assertSee('Klien terlambat memberi data uji')
            ->assertDontSee('Server uji tidak dapat diakses');
    }

    public function test_non_member_and_unknown_project_are_rejected(): void
    {
        $this->login('acc-tw-0092');

        $this->get($this->url())->assertForbidden();
        $this->get('/projects/PRJ-0000-000/risk-issues')->assertNotFound();
    }

    public function test_sidebar_links_to_risk_issues(): void
    {
        $this->login('acc-tl-0042');

        $this->get($this->url())->assertOk()->assertSee('href="'.$this->url().'"', false);
    }

    // ---- Record (PM)

    public function test_pm_records_a_risk_and_history_is_written(): void
    {
        $this->login('acc-pm-0007');

        $this->get($this->url('/create'))->assertOk()->assertSee('Informasi Risiko/Isu');

        $response = $this->post($this->url(), $this->valid());

        $item = RiskIssue::where('title', 'Anggota tim cuti panjang')->firstOrFail();
        $response->assertRedirect("/risk-issues/{$item->id}")->assertSessionHas('status', 'Risiko berhasil dicatat');

        $this->assertSame('OPEN', $item->status->value);
        $this->assertSame('acc-pm-0007', $item->reported_by);
        $this->assertNull($item->mitigation);
        $this->assertTrue(StatusHistory::where('entity_id', $item->id)->where('to_status', 'OPEN')->exists());
    }

    public function test_record_shows_errors_for_missing_and_invalid_fields(): void
    {
        $this->login('acc-pm-0007');

        $this->from($this->url('/create'))
            ->post($this->url(), $this->valid(['title' => '', 'type' => 'BUG']))
            ->assertRedirect($this->url('/create'))
            ->assertSessionHasErrors(['title' => 'Judul wajib diisi', 'type' => 'Jenis harus RISK atau ISSUE']);

        $this->post($this->url(), $this->valid(['picAccountId' => 'acc-tw-0092']))
            ->assertSessionHasErrors(['picAccountId' => 'Akun acc-tw-0092 bukan anggota proyek']);
    }

    public function test_tl_cannot_record(): void
    {
        $this->login('acc-tl-0042');

        $this->get($this->url('/create'))->assertForbidden();
        $this->post($this->url(), $this->valid())->assertForbidden();
    }

    public function test_inactive_project_rejects_new_items(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/projects/'.self::INACTIVE_PROJECT.'/risk-issues/create')->assertOk()->assertSee('Proyek tidak aktif');
        $this->post('/projects/'.self::INACTIVE_PROJECT.'/risk-issues', $this->valid(['picAccountId' => 'acc-pm-0007']))
            ->assertStatus(409);
    }

    // ---- Detail and update

    public function test_detail_shows_full_form_to_pm_and_lock_to_tw(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/risk-issues/'.self::PM_RISK)->assertOk()
            ->assertSee(['Klien terlambat memberi data uji', 'Perbarui Risiko', 'name="title"', 'Riwayat Status'], false);
    }

    public function test_tw_sees_detail_read_only(): void
    {
        $this->login('acc-tw-0091');

        $this->get('/risk-issues/'.self::PM_RISK)->assertOk()
            ->assertSee('yang dapat memperbarui')
            ->assertDontSee('Simpan Perubahan');
        $this->patch('/risk-issues/'.self::PM_RISK, ['status' => 'CLOSED', 'resolutionNote' => 'x'])->assertForbidden();
    }

    public function test_tl_pic_sees_limited_form_and_closes_issue(): void
    {
        $this->login('acc-tl-0042');

        $this->get('/risk-issues/'.self::TL_ISSUE)->assertOk()
            ->assertSee('Sebagai PIC')
            ->assertDontSee('name="title"', false);

        $this->patch('/risk-issues/'.self::TL_ISSUE, [
            'status' => 'CLOSED',
            'mitigation' => 'Server cadangan dipakai permanen',
            'resolutionNote' => 'Server utama sudah diperbaiki',
            'title' => 'Diabaikan karena TL tidak boleh mengubah judul',
        ])->assertRedirect('/risk-issues/'.self::TL_ISSUE)->assertSessionHas('status', 'Perubahan berhasil disimpan');

        $item = RiskIssue::findOrFail(self::TL_ISSUE);
        $this->assertSame('CLOSED', $item->status->value);
        $this->assertSame('Server uji tidak dapat diakses', $item->title);
        $this->assertNotNull($item->closed_at);
        $this->assertTrue(StatusHistory::where('entity_id', $item->id)->where('from_status', 'IN_PROGRESS')->where('to_status', 'CLOSED')->exists());
    }

    public function test_closing_without_resolution_note_is_rejected(): void
    {
        $this->login('acc-tl-0042');

        $this->patch('/risk-issues/'.self::TL_ISSUE, ['status' => 'CLOSED', 'resolutionNote' => ''])
            ->assertSessionHasErrors(['resolutionNote' => 'Catatan penyelesaian wajib diisi saat status CLOSED']);

        $this->assertSame('IN_PROGRESS', RiskIssue::findOrFail(self::TL_ISSUE)->status->value);
    }

    public function test_tl_who_is_not_pic_cannot_update(): void
    {
        $this->login('acc-tl-0042');

        $this->get('/risk-issues/'.self::PM_RISK)->assertOk()->assertDontSee('Simpan Perubahan');
        $this->patch('/risk-issues/'.self::PM_RISK, ['status' => 'IN_PROGRESS'])->assertForbidden();
    }

    public function test_pm_updates_fields_and_pic(): void
    {
        $this->login('acc-pm-0007');

        $this->patch('/risk-issues/'.self::PM_RISK, $this->valid([
            'title' => 'Klien terlambat memberi data uji (eskalasi)',
            'impactLevel' => 'HIGH',
            'picAccountId' => 'acc-tl-0042',
            'status' => 'IN_PROGRESS',
            'resolutionNote' => '',
        ]))->assertRedirect('/risk-issues/'.self::PM_RISK);

        $item = RiskIssue::findOrFail(self::PM_RISK);
        $this->assertSame('Klien terlambat memberi data uji (eskalasi)', $item->title);
        $this->assertSame('HIGH', $item->impact_level->value);
        $this->assertSame('acc-tl-0042', $item->pic_account_id);
        $this->assertSame('IN_PROGRESS', $item->status->value);
    }

    public function test_unknown_item_is_404(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/risk-issues/55555555-5555-4555-8555-999999999999')->assertNotFound();
    }
}
