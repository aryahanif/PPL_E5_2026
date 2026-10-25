<?php

namespace Tests\Feature;

use App\Models\ActionItem;
use App\Models\Mom;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-04 PATCH /api/v1/action-items/{actionItemId}/status (updateActionItemStatus). */
class ActionItemStatusTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const TODO = '33333333-3333-4333-8333-000000000001'; // PIC TL

    private const OVERDUE = '33333333-3333-4333-8333-000000000002'; // PIC TW, IN_PROGRESS

    private const DONE = '33333333-3333-4333-8333-000000000003'; // PIC PM

    private function change(string $id, array $body, string $account)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken("mock-{$account}")->patchJson("/api/v1/action-items/{$id}/status", $body);
    }

    public function test_pic_moves_forward_and_history_is_recorded(): void
    {
        $this->change(self::TODO, ['status' => 'IN_PROGRESS'], 'acc-tl-0042')->assertOk()
            ->assertJsonPath('actionItemId', self::TODO)
            ->assertJsonPath('status', 'IN_PROGRESS')
            ->assertJsonPath('completedAt', null);

        $this->change(self::TODO, ['status' => 'DONE'], 'acc-tl-0042')->assertOk()
            ->assertJsonPath('status', 'DONE')
            ->assertJsonPath('overdue', false);

        $this->assertNotNull(ActionItem::find(self::TODO)->completed_at);

        $rows = StatusHistory::where('entity_id', self::TODO)->get();
        $this->assertCount(2, $rows);
        $this->assertSame('ACTION_ITEM', $rows[0]->entity_type);
        $this->assertEqualsCanonicalizing(['IN_PROGRESS', 'DONE'], $rows->pluck('to_status')->all());
    }

    public function test_overdue_item_can_still_be_completed(): void
    {
        $this->change(self::OVERDUE, ['status' => 'DONE'], 'acc-tw-0091')->assertOk()
            ->assertJsonPath('status', 'DONE')
            ->assertJsonPath('overdue', false);
    }

    public function test_invalid_status_value_is_400(): void
    {
        $this->change(self::TODO, ['status' => 'TODO'], 'acc-tl-0042')->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_without_token_is_401(): void
    {
        $this->patchJson('/api/v1/action-items/'.self::TODO.'/status', ['status' => 'IN_PROGRESS'])
            ->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_pm_who_is_not_pic_is_403(): void
    {
        $this->change(self::TODO, ['status' => 'IN_PROGRESS'], 'acc-pm-0007')->assertStatus(403)
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => 'Hanya PIC yang dapat mengubah status action item']);
    }

    public function test_non_member_is_403(): void
    {
        $this->change(self::TODO, ['status' => 'IN_PROGRESS'], 'acc-tw-0092')->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_item_is_404(): void
    {
        $this->change('33333333-3333-4333-8333-999999999999', ['status' => 'DONE'], 'acc-tw-0091')
            ->assertStatus(404)->assertJsonPath('code', 'ACTION_ITEM_NOT_FOUND');
    }

    public function test_done_to_in_progress_is_409(): void
    {
        $this->change(self::DONE, ['status' => 'IN_PROGRESS'], 'acc-pm-0007')->assertStatus(409)
            ->assertExactJson(['code' => 'INVALID_STATUS_TRANSITION', 'message' => 'Status DONE tidak dapat diubah ke IN_PROGRESS']);

        $this->assertSame(0, StatusHistory::where('entity_id', self::DONE)->count());
    }

    public function test_todo_to_done_is_409(): void
    {
        $this->change(self::TODO, ['status' => 'DONE'], 'acc-tl-0042')->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_item_of_non_final_mom_is_409(): void
    {
        Mom::query()->update(['status' => 'REVISION']);

        $this->change(self::TODO, ['status' => 'IN_PROGRESS'], 'acc-tl-0042')->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }
}
