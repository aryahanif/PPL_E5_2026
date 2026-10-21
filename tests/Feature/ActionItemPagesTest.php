<?php

namespace Tests\Feature;

use App\Enums\MomStatus;
use App\Models\ActionItem;
use App\Models\Mom;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Action item page for US-4 (UI-K05-04). Seed: TL item TODO, TW item IN_PROGRESS (overdue), PM item DONE. */
class ActionItemPagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PROJECT = 'PRJ-2026-014';

    private const EMPTY_PROJECT = 'PRJ-2026-016';

    private const TL_ITEM = '33333333-3333-4333-8333-000000000001';

    private const TW_ITEM = '33333333-3333-4333-8333-000000000002';

    private const PM_ITEM = '33333333-3333-4333-8333-000000000003';

    private function login(string $accountId): void
    {
        $this->post('/dev-login', ['accountId' => $accountId]);
    }

    private function url(string $tab = ''): string
    {
        return '/projects/'.self::PROJECT.'/action-items'.($tab ? "?tab={$tab}" : '');
    }

    /** An item of a MoM that is not FINAL yet; it must stay hidden and locked. */
    private function draftItem(): ActionItem
    {
        $mom = Mom::create([
            'meeting_id' => '11111111-1111-4111-8111-000000000002',
            'meeting_date' => now()->subDays(2)->toDateString(),
            'discussion' => 'Bahas',
            'decision' => 'Lanjut',
            'status' => MomStatus::DRAFT,
            'written_by' => 'acc-tw-0091',
        ]);

        return $mom->actionItems()->create([
            'description' => 'Item dari MoM draft',
            'pic_account_id' => 'acc-tw-0091',
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'TODO',
        ]);
    }

    // ---- Listing

    public function test_tw_sees_only_own_items_and_no_all_tab(): void
    {
        $this->login('acc-tw-0091');

        $this->get($this->url())->assertOk()
            ->assertSee('Milik saya')
            ->assertDontSee('Semua di proyek')
            ->assertSee('Perbarui dokumen kebutuhan modul login')
            ->assertDontSee('Siapkan server uji baru')
            ->assertDontSee('Konfirmasi jadwal demo ke klien');
    }

    public function test_tw_cannot_widen_the_list_with_tab_all(): void
    {
        $this->login('acc-tw-0091');

        $this->get($this->url('all'))->assertOk()
            ->assertSee('Perbarui dokumen kebutuhan modul login')
            ->assertDontSee('Siapkan server uji baru')
            ->assertDontSee('Konfirmasi jadwal demo ke klien');
    }

    public function test_pm_mine_tab_shows_only_own_and_all_tab_shows_everything(): void
    {
        $this->login('acc-pm-0007');

        $this->get($this->url())->assertOk()
            ->assertSee(['Milik saya', 'Semua di proyek'])
            ->assertSee('Konfirmasi jadwal demo ke klien')
            ->assertDontSee('Siapkan server uji baru');

        $this->get($this->url('all'))->assertOk()
            ->assertSee(['Siapkan server uji baru', 'Perbarui dokumen kebutuhan modul login', 'Konfirmasi jadwal demo ke klien']);
    }

    public function test_tl_sees_all_tab_and_kpis(): void
    {
        $this->login('acc-tl-0042');

        $this->get($this->url('all'))->assertOk()
            ->assertSee('Semua di proyek')
            ->assertViewHas('overdue', 1)
            ->assertViewHas('counts', fn ($counts) => $counts->sortKeys()->all() === ['DONE' => 1, 'IN_PROGRESS' => 1, 'TODO' => 1])
            ->assertSee(['Total', 'TODO', 'IN_PROGRESS', 'DONE', 'Overdue']);
    }

    public function test_overdue_badge_is_separate_and_only_for_late_items(): void
    {
        $this->login('acc-tl-0042');

        $html = $this->get($this->url('all'))->assertOk()->assertSee('IN_PROGRESS')->getContent();

        // Three items in the project, only the late, not-DONE one gets the badge.
        $this->assertSame(1, substr_count($html, 'OVERDUE'));
    }

    public function test_done_item_is_not_flagged_overdue(): void
    {
        $this->login('acc-pm-0007'); // PM's own item is DONE and past due

        $this->get($this->url())->assertOk()->assertSee('Konfirmasi jadwal demo')->assertDontSee('OVERDUE');
    }

    public function test_items_of_non_final_mom_are_hidden(): void
    {
        $this->draftItem();
        $this->login('acc-tw-0091');

        $this->get($this->url())->assertOk()->assertDontSee('Item dari MoM draft');
    }

    public function test_empty_project_shows_empty_state(): void
    {
        $this->login('acc-pm-0007');

        $this->get('/projects/'.self::EMPTY_PROJECT.'/action-items')->assertOk()->assertSee('Belum ada action item');
    }

    public function test_non_member_and_unknown_project_are_rejected(): void
    {
        $this->login('acc-tw-0092');

        $this->get($this->url())->assertForbidden();
        $this->get('/projects/PRJ-0000-000/action-items')->assertNotFound();
    }

    public function test_sidebar_links_to_action_items(): void
    {
        $this->login('acc-pm-0007');

        $this->get($this->url())->assertOk()->assertSee('href="/projects/'.self::PROJECT.'/action-items"', false);
    }

    // ---- Status changes

    public function test_pic_sees_status_form_only_on_own_items(): void
    {
        $this->login('acc-pm-0007');

        $this->get($this->url('all'))->assertOk()
            ->assertDontSee('/action-items/'.self::TL_ITEM.'/status', false)
            ->assertDontSee('/action-items/'.self::TW_ITEM.'/status', false)
            ->assertSee('Hanya PIC');
    }

    public function test_tw_pic_sees_form_for_own_item(): void
    {
        $this->login('acc-tw-0091');

        $this->get($this->url())->assertOk()
            ->assertSee('/action-items/'.self::TW_ITEM.'/status', false)
            ->assertSee('Simpan');
    }

    public function test_pic_moves_overdue_item_forward_and_history_is_written(): void
    {
        $this->login('acc-tw-0091');

        $this->patch('/action-items/'.self::TW_ITEM.'/status', ['status' => 'DONE'])->assertRedirect();

        $item = ActionItem::find(self::TW_ITEM);
        $this->assertSame('DONE', $item->status->value);
        $this->assertNotNull($item->completed_at);

        $row = StatusHistory::where('entity_id', self::TW_ITEM)->sole();
        $this->assertSame('IN_PROGRESS', $row->from_status);
        $this->assertSame('DONE', $row->to_status);
        $this->assertSame('acc-tw-0091', $row->changed_by);
    }

    public function test_pic_starts_todo_item_and_sees_toast(): void
    {
        $this->login('acc-tl-0042'); // TL is PIC of the TODO item

        $this->from($this->url('all'))->patch('/action-items/'.self::TL_ITEM.'/status', ['status' => 'IN_PROGRESS'])
            ->assertRedirect($this->url('all'));

        $this->assertSame('IN_PROGRESS', ActionItem::find(self::TL_ITEM)->status->value);
        $this->get($this->url('all'))->assertSee('Status action item diubah menjadi IN_PROGRESS');
    }

    public function test_skipping_a_step_is_409(): void
    {
        $this->login('acc-tl-0042');

        $this->patch('/action-items/'.self::TL_ITEM.'/status', ['status' => 'DONE'])->assertStatus(409);
        $this->assertSame('TODO', ActionItem::find(self::TL_ITEM)->status->value);
    }

    public function test_going_backwards_is_409(): void
    {
        $this->login('acc-pm-0007'); // PIC of the DONE item

        $this->patch('/action-items/'.self::PM_ITEM.'/status', ['status' => 'IN_PROGRESS'])->assertStatus(409);
        $this->assertSame('DONE', ActionItem::find(self::PM_ITEM)->status->value);
    }

    public function test_non_pic_status_change_is_rejected(): void
    {
        $this->login('acc-tw-0091');

        $this->patch('/action-items/'.self::TL_ITEM.'/status', ['status' => 'IN_PROGRESS'])->assertForbidden();
        $this->assertSame('TODO', ActionItem::find(self::TL_ITEM)->status->value);
    }

    public function test_pm_cannot_change_status_of_item_they_do_not_own(): void
    {
        $this->login('acc-pm-0007');

        $this->patch('/action-items/'.self::TW_ITEM.'/status', ['status' => 'DONE'])->assertForbidden();
    }

    public function test_unknown_or_empty_status_is_rejected_and_item_keeps_status(): void
    {
        $this->login('acc-tw-0091');

        $this->from($this->url())->patch('/action-items/'.self::TW_ITEM.'/status', ['status' => 'SELESAI'])
            ->assertRedirect($this->url())
            ->assertSessionHasErrors('status');
        $this->patch('/action-items/'.self::TW_ITEM.'/status', ['status' => ''])->assertSessionHasErrors('status');
        $this->patch('/action-items/'.self::TW_ITEM.'/status', ['status' => 'TODO'])->assertSessionHasErrors('status');

        $this->assertSame('IN_PROGRESS', ActionItem::find(self::TW_ITEM)->status->value);
    }

    public function test_item_of_non_final_mom_cannot_change_status(): void
    {
        $item = $this->draftItem();
        $this->login('acc-tw-0091');

        $this->patch("/action-items/{$item->id}/status", ['status' => 'IN_PROGRESS'])->assertStatus(409);
    }

    public function test_non_member_cannot_change_status(): void
    {
        $this->login('acc-tw-0092');

        $this->patch('/action-items/'.self::TW_ITEM.'/status', ['status' => 'DONE'])->assertForbidden();
    }

    public function test_unknown_item_is_404(): void
    {
        $this->login('acc-tw-0091');

        $this->patch('/action-items/33333333-3333-4333-8333-999999999999/status', ['status' => 'DONE'])->assertNotFound();
    }
}
