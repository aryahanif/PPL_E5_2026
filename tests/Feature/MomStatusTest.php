<?php

namespace Tests\Feature;

use App\Enums\MomStatus;
use App\Models\Mom;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-03 PATCH /api/v1/moms/{momId}/status (updateMomStatus). */
class MomStatusTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const DONE_NO_MOM = '11111111-1111-4111-8111-000000000002';

    private const FINAL_MOM = '22222222-2222-4222-8222-000000000003';

    private function draftMom(MomStatus $status = MomStatus::DRAFT): Mom
    {
        return Mom::create([
            'meeting_id' => self::DONE_NO_MOM,
            'meeting_date' => now()->subDays(2)->toDateString(),
            'discussion' => 'Bahasan',
            'decision' => 'Keputusan',
            'status' => $status,
            'written_by' => 'acc-tw-0091',
        ]);
    }

    private function change(string $id, array $body, string $account)
    {
        // The mock guard caches the first user per test; reset it so one test can act as TW then TL.
        $this->app['auth']->forgetGuards();

        return $this->withToken("mock-{$account}")->patchJson("/api/v1/moms/{$id}/status", $body);
    }

    public function test_full_review_flow_records_history(): void
    {
        $mom = $this->draftMom();
        $mom->actionItems()->create([
            'description' => 'Tindak lanjut', 'pic_account_id' => 'acc-tl-0042',
            'due_date' => now()->addDays(3)->toDateString(), 'status' => 'TODO',
        ]);

        $this->change($mom->id, ['status' => 'IN_REVIEW'], 'acc-tw-0091')->assertOk()
            ->assertJsonPath('momId', $mom->id)->assertJsonPath('status', 'IN_REVIEW');

        $this->change($mom->id, ['status' => 'REVISION', 'reviewNote' => 'Poin 2 belum jelas'], 'acc-tl-0042')->assertOk()
            ->assertJsonPath('status', 'REVISION')
            ->assertJsonPath('reviewNote', 'Poin 2 belum jelas')
            ->assertJsonPath('finalizedAt', null);

        $this->change($mom->id, ['status' => 'IN_REVIEW'], 'acc-tw-0091')->assertOk();

        $this->change($mom->id, ['status' => 'FINAL'], 'acc-tl-0042')->assertOk()
            ->assertJsonPath('status', 'FINAL')
            ->assertJsonPath('reviewedBy', 'acc-tl-0042')
            ->assertJsonCount(1, 'actionItems')
            ->assertJsonPath('actionItems.0.description', 'Tindak lanjut');

        $this->assertNotNull($mom->refresh()->finalized_at);

        $rows = StatusHistory::where('entity_id', $mom->id)->orderBy('changed_at')->orderBy('id')->get();
        $this->assertSame(4, $rows->count());
        $this->assertSame('Poin 2 belum jelas', $rows->firstWhere('to_status', 'REVISION')->note);
        $this->assertSame('MOM', $rows[0]->entity_type);
        $this->assertEqualsCanonicalizing(['IN_REVIEW', 'REVISION', 'IN_REVIEW', 'FINAL'], $rows->pluck('to_status')->all());
    }

    public function test_revision_without_note_is_400(): void
    {
        $mom = $this->draftMom(MomStatus::IN_REVIEW);

        $this->change($mom->id, ['status' => 'REVISION'], 'acc-tl-0042')->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->assertSame(0, StatusHistory::where('entity_id', $mom->id)->count());
    }

    public function test_invalid_status_value_is_400(): void
    {
        $this->change($this->draftMom()->id, ['status' => 'DRAFT'], 'acc-tw-0091')->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_without_token_is_401(): void
    {
        $this->patchJson('/api/v1/moms/'.self::FINAL_MOM.'/status', ['status' => 'IN_REVIEW'])
            ->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_tw_cannot_finalize_is_403(): void
    {
        $mom = $this->draftMom(MomStatus::IN_REVIEW);

        $this->change($mom->id, ['status' => 'FINAL'], 'acc-tw-0091')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Hanya TL yang dapat mereview MoM']);
    }

    public function test_tl_cannot_submit_is_403(): void
    {
        $mom = $this->draftMom();

        $this->change($mom->id, ['status' => 'IN_REVIEW'], 'acc-tl-0042')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Hanya TW yang dapat mengajukan review MoM']);
    }

    public function test_non_member_is_403(): void
    {
        $this->change($this->draftMom()->id, ['status' => 'IN_REVIEW'], 'acc-tw-0092')->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_mom_is_404(): void
    {
        $this->change('22222222-2222-4222-8222-999999999999', ['status' => 'IN_REVIEW'], 'acc-tw-0091')
            ->assertStatus(404)->assertJsonPath('code', 'MOM_NOT_FOUND');
    }

    public function test_final_to_in_review_is_409(): void
    {
        $this->change(self::FINAL_MOM, ['status' => 'IN_REVIEW'], 'acc-tw-0091')->assertStatus(409)
            ->assertExactJson(['code' => 'INVALID_STATUS_TRANSITION', 'message' => 'Status FINAL tidak dapat diubah ke IN_REVIEW']);

        $this->assertSame(0, StatusHistory::where('entity_id', self::FINAL_MOM)->count());
    }

    public function test_draft_to_final_is_409(): void
    {
        $this->change($this->draftMom()->id, ['status' => 'FINAL'], 'acc-tl-0042')->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }
}
