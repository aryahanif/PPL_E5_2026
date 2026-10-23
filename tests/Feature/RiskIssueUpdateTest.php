<?php

namespace Tests\Feature;

use App\Models\RiskIssue;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-07 PATCH /api/v1/risk-issues/{riskIssueId} (updateRiskIssue). */
class RiskIssueUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const RISK = '55555555-5555-4555-8555-000000000001'; // OPEN, PIC PM

    private const ISSUE = '55555555-5555-4555-8555-000000000002'; // IN_PROGRESS, PIC TL

    private function change(string $id, array $body, string $account = 'acc-pm-0007')
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken("mock-{$account}")->patchJson("/api/v1/risk-issues/{$id}", $body);
    }

    private function history(string $id)
    {
        return StatusHistory::where('entity_type', 'RISK_ISSUE')->where('entity_id', $id)->get();
    }

    public function test_pm_changes_every_field_and_pic_change_is_recorded(): void
    {
        $this->change(self::RISK, [
            'type' => 'ISSUE',
            'title' => 'Data uji belum diterima',
            'description' => 'Klien belum mengirim data uji.',
            'impactLevel' => 'HIGH',
            'mitigation' => 'Pakai data uji buatan',
            'picAccountId' => 'acc-tl-0042',
            'sourceMomId' => '22222222-2222-4222-8222-000000000003',
            'status' => 'IN_PROGRESS',
        ])->assertOk()
            ->assertJsonPath('type', 'ISSUE')
            ->assertJsonPath('title', 'Data uji belum diterima')
            ->assertJsonPath('impactLevel', 'HIGH')
            ->assertJsonPath('mitigation', 'Pakai data uji buatan')
            ->assertJsonPath('picAccountId', 'acc-tl-0042')
            ->assertJsonPath('sourceMomId', '22222222-2222-4222-8222-000000000003')
            ->assertJsonPath('status', 'IN_PROGRESS');

        $rows = $this->history(self::RISK)->map(fn ($r) => [$r->from_status, $r->to_status, $r->note, $r->changed_by])->all();
        $this->assertEqualsCanonicalizing([
            ['OPEN', 'OPEN', 'PIC diubah ke acc-tl-0042', 'acc-pm-0007'],
            ['OPEN', 'IN_PROGRESS', null, 'acc-pm-0007'],
        ], $rows);
    }

    public function test_pm_closes_open_item_directly(): void
    {
        $this->change(self::RISK, ['status' => 'CLOSED', 'resolutionNote' => 'Data uji sudah diterima'])->assertOk()
            ->assertJsonPath('status', 'CLOSED')
            ->assertJsonPath('resolutionNote', 'Data uji sudah diterima');

        $this->assertNotNull(RiskIssue::find(self::RISK)->closed_at);
        $this->assertSame('Data uji sudah diterima', $this->history(self::RISK)->sole()->note);
    }

    public function test_tl_pic_updates_status_mitigation_and_resolution_note(): void
    {
        $response = $this->change(self::ISSUE, [
            'status' => 'CLOSED',
            'mitigation' => 'Server cadangan dipakai permanen',
            'resolutionNote' => 'Server dipindah, testing berjalan normal',
        ], 'acc-tl-0042')->assertOk()
            ->assertJsonPath('status', 'CLOSED')
            ->assertJsonPath('mitigation', 'Server cadangan dipakai permanen')
            ->assertJsonPath('resolutionNote', 'Server dipindah, testing berjalan normal');

        $this->assertNotNull($response->json('closedAt'));

        $row = $this->history(self::ISSUE)->sole();
        $this->assertSame(['IN_PROGRESS', 'CLOSED', 'acc-tl-0042'], [$row->from_status, $row->to_status, $row->changed_by]);
    }

    public function test_sending_current_status_is_not_a_change(): void
    {
        $this->change(self::ISSUE, ['status' => 'IN_PROGRESS', 'mitigation' => 'Cek ulang jaringan'])->assertOk()
            ->assertJsonPath('mitigation', 'Cek ulang jaringan');

        $this->assertCount(0, $this->history(self::ISSUE));
    }

    public function test_closing_without_resolution_note_is_400(): void
    {
        $this->change(self::ISSUE, ['status' => 'CLOSED'], 'acc-tl-0042')->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: resolutionNote']);

        $this->assertSame('IN_PROGRESS', RiskIssue::find(self::ISSUE)->status->value);
    }

    public function test_invalid_body_is_400(): void
    {
        $this->change(self::RISK, [])->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: body']);

        $this->change(self::RISK, ['status' => 'DONE', 'title' => ''])->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: title, status']);

        $this->change(self::RISK, ['picAccountId' => 'acc-tw-0092'])->assertStatus(400)
            ->assertExactJson(['code' => 'VALIDATION_ERROR', 'message' => 'Data tidak valid: picAccountId']);
    }

    public function test_invalid_transitions_are_409(): void
    {
        $this->change(self::ISSUE, ['status' => 'OPEN'])->assertStatus(409)
            ->assertExactJson(['code' => 'INVALID_STATUS_TRANSITION', 'message' => 'Status IN_PROGRESS tidak dapat diubah ke OPEN']);

        $this->change(self::ISSUE, ['status' => 'CLOSED', 'resolutionNote' => 'Selesai'])->assertOk();

        $this->change(self::ISSUE, ['status' => 'IN_PROGRESS', 'title' => 'Judul baru'])->assertStatus(409)
            ->assertExactJson(['code' => 'INVALID_STATUS_TRANSITION', 'message' => 'Status CLOSED tidak dapat diubah ke IN_PROGRESS']);

        // The rejected request changes nothing, not even the title.
        $this->assertSame('Server uji tidak dapat diakses', RiskIssue::find(self::ISSUE)->title);
    }

    public function test_missing_token_is_401(): void
    {
        $this->patchJson('/api/v1/risk-issues/'.self::RISK, ['status' => 'IN_PROGRESS'])->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_tw_is_403(): void
    {
        $this->change(self::RISK, ['status' => 'IN_PROGRESS'], 'acc-tw-0091')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Hanya PM atau TL yang dapat mengubah risiko/isu']);
    }

    public function test_tl_on_item_of_someone_else_is_403(): void
    {
        $this->change(self::RISK, ['status' => 'IN_PROGRESS'], 'acc-tl-0042')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'TL hanya dapat mengubah risiko/isu yang PIC-nya dirinya']);

        $this->assertSame('OPEN', RiskIssue::find(self::RISK)->status->value);
    }

    public function test_tl_changing_other_fields_is_403(): void
    {
        $this->change(self::ISSUE, ['title' => 'Judul baru'], 'acc-tl-0042')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'TL hanya dapat mengubah status, mitigasi, dan catatan penyelesaian']);

        $this->change(self::ISSUE, ['picAccountId' => 'acc-pm-0007'], 'acc-tl-0042')->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->assertSame('acc-tl-0042', RiskIssue::find(self::ISSUE)->pic_account_id);
    }

    public function test_non_member_is_403(): void
    {
        $this->change(self::RISK, ['status' => 'IN_PROGRESS'], 'acc-tw-0092')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Anda bukan anggota proyek ini']);
    }

    public function test_unknown_id_is_404(): void
    {
        $this->change('00000000-0000-4000-8000-000000000000', ['status' => 'IN_PROGRESS'])->assertStatus(404)
            ->assertExactJson(['code' => 'RISK_ISSUE_NOT_FOUND', 'message' => 'Data tidak ditemukan']);
    }
}
