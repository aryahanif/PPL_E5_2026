<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Change request pages (UI-K05-05). Seed: one RECORDED adjustment and one formal SCOPE CR in PENDING_ANALYSIS. */
class ChangeRequestPagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PROJECT = '019a0000-d101-7000-8000-000000000014';

    private const FINAL_MOM = '019a0000-2222-7222-8222-000000000003';

    private const RECORDED = '019a0000-6666-7666-8666-000000000001';

    private const PENDING = '019a0000-6666-7666-8666-000000000002';

    private function login(string $accountId): void
    {
        $this->post('/dev-login', ['accountId' => $accountId]);
    }

    private function url(string $suffix = ''): string
    {
        return '/projects/'.self::PROJECT.'/change-requests'.$suffix;
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'momId' => self::FINAL_MOM,
            'changeDescription' => 'Klien meminta ekspor laporan ke Excel',
            'source' => 'USER',
            'changeType' => 'TASK',
            'isFormalCr' => '1',
        ], $overrides);
    }

    /** Moves the seeded pending CR to ANALYZED as the TL. */
    private function analyzed(): ChangeRequest
    {
        return ChangeRequest::findOrFail(self::PENDING)->forceFill([
            'status' => 'ANALYZED', 'impact' => 'Tambah 3 hari', 'baseline_item_id' => '019a0000-e301-7000-8000-000000014001', 'analyzed_by' => '019a0000-f101-7000-8000-000000000042',
        ]);
    }

    // ---- List

    public function test_pm_sees_change_log_and_record_button(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000007');

        $this->get($this->url())->assertOk()
            ->assertSee(['Penyesuaian label tombol login', 'Penambahan validasi autentikasi'])
            ->assertSee('Catat Perubahan');
    }

    public function test_tw_sees_change_log_without_record_button(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000091');

        $this->get($this->url())->assertOk()
            ->assertSee('Penyesuaian label tombol login')
            ->assertDontSee('Catat Perubahan');
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000042');

        $this->get($this->url('?status=RECORDED'))->assertOk()
            ->assertSee('Penyesuaian label tombol login')
            ->assertDontSee('Penambahan validasi autentikasi');
    }

    public function test_non_member_is_403(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000092');

        $this->get($this->url())->assertForbidden();
    }

    // ---- Record

    public function test_tl_records_formal_cr_as_pending_analysis(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000042');

        $this->get($this->url('/create'))->assertOk()->assertSee('Weekly Meeting Minggu 2');

        $response = $this->post($this->url(), $this->valid());

        $item = ChangeRequest::where('change_description', 'Klien meminta ekspor laporan ke Excel')->firstOrFail();
        $response->assertRedirect("/change-requests/{$item->id}");
        $this->assertSame('PENDING_ANALYSIS', $item->status->value);
        $this->assertSame('019a0000-f101-7000-8000-000000000042', $item->created_by);
        $this->assertDatabaseHas('status_histories', ['entity_id' => $item->id, 'from_status' => null, 'to_status' => 'PENDING_ANALYSIS']);
    }

    public function test_pm_records_adjustment_as_recorded(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000007');

        $this->post($this->url(), $this->valid(['isFormalCr' => '0']));

        $this->assertSame('RECORDED', ChangeRequest::where('change_description', 'Klien meminta ekspor laporan ke Excel')->firstOrFail()->status->value);
    }

    public function test_tw_cannot_record(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000091');

        $this->get($this->url('/create'))->assertForbidden();
        $this->post($this->url(), $this->valid())->assertForbidden();
    }

    public function test_empty_fields_are_rejected(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000007');

        $this->from($this->url('/create'))
            ->post($this->url(), ['momId' => '', 'changeDescription' => '', 'source' => '', 'changeType' => '', 'isFormalCr' => ''])
            ->assertRedirect($this->url('/create'))
            ->assertSessionHasErrors(['momId', 'changeDescription', 'source', 'changeType', 'isFormalCr']);
    }

    public function test_non_final_mom_is_rejected(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000007');

        $this->post($this->url(), $this->valid(['momId' => '019a0000-2222-7222-8222-000000000099']))
            ->assertSessionHasErrors('momId');
    }

    // ---- Analyze (TL)

    public function test_tl_sees_analysis_form_and_saves_it(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000042');

        $this->get('/change-requests/'.self::PENDING)->assertOk()->assertSee('Simpan Analisis');

        $this->patch('/change-requests/'.self::PENDING.'/analysis', [
            'impact' => 'Tambah 3 hari pada modul autentikasi',
            'baselineItemId' => '019a0000-e301-7000-8000-000000014001',
            'affectedTaskIds' => ['019a0000-e401-7000-8000-000000014001'],
        ])->assertRedirect('/change-requests/'.self::PENDING);

        $item = ChangeRequest::with('affectedTasks')->findOrFail(self::PENDING);
        $this->assertSame('ANALYZED', $item->status->value);
        $this->assertSame(['019a0000-e401-7000-8000-000000014001'], $item->affectedTasks->pluck('task_id')->all());
        $this->assertDatabaseHas('status_histories', ['entity_id' => self::PENDING, 'from_status' => 'PENDING_ANALYSIS', 'to_status' => 'ANALYZED']);
    }

    public function test_scope_cr_without_baseline_is_rejected(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000042');

        $this->patch('/change-requests/'.self::PENDING.'/analysis', ['impact' => 'Tambah 3 hari'])
            ->assertSessionHasErrors('baselineItemId');

        $this->assertSame('PENDING_ANALYSIS', ChangeRequest::findOrFail(self::PENDING)->status->value);
    }

    public function test_pm_cannot_analyze(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000007');

        $this->get('/change-requests/'.self::PENDING)->assertOk()->assertDontSee('Simpan Analisis');
        $this->patch('/change-requests/'.self::PENDING.'/analysis', ['impact' => 'x', 'baselineItemId' => '019a0000-e301-7000-8000-000000014001'])
            ->assertForbidden();
    }

    // ---- Decide (PM, TL)

    public function test_pm_approves_analyzed_cr(): void
    {
        $this->analyzed()->save();
        $this->login('019a0000-f101-7000-8000-000000000007');

        $this->get('/change-requests/'.self::PENDING)->assertOk()->assertSee('Simpan Keputusan');

        $this->patch('/change-requests/'.self::PENDING.'/decision', ['decision' => 'APPROVED', 'decisionNote' => 'Sesuai anggaran'])
            ->assertRedirect('/change-requests/'.self::PENDING);

        $item = ChangeRequest::findOrFail(self::PENDING);
        $this->assertSame('APPROVED', $item->status->value);
        $this->assertSame('019a0000-f101-7000-8000-000000000007', $item->decided_by);
        $this->assertTrue(StatusHistory::where('entity_id', self::PENDING)->where('to_status', 'APPROVED')->exists());
    }

    public function test_decision_needs_a_reason(): void
    {
        $this->analyzed()->save();
        $this->login('019a0000-f101-7000-8000-000000000042');

        $this->patch('/change-requests/'.self::PENDING.'/decision', ['decision' => 'REJECTED', 'decisionNote' => ''])
            ->assertSessionHasErrors('decisionNote');
    }

    public function test_tw_cannot_decide(): void
    {
        $this->analyzed()->save();
        $this->login('019a0000-f101-7000-8000-000000000091');

        $this->get('/change-requests/'.self::PENDING)->assertOk()->assertDontSee('Simpan Keputusan');
        $this->patch('/change-requests/'.self::PENDING.'/decision', ['decision' => 'APPROVED', 'decisionNote' => 'ok'])
            ->assertForbidden();
    }

    public function test_recorded_adjustment_cannot_be_decided(): void
    {
        $this->login('019a0000-f101-7000-8000-000000000007');

        $this->patch('/change-requests/'.self::RECORDED.'/decision', ['decision' => 'APPROVED', 'decisionNote' => 'ok'])
            ->assertStatus(409);
    }
}
