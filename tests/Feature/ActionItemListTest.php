<?php

namespace Tests\Feature;

use App\Models\Mom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-04 GET /api/v1/action-items (listActionItems). */
class ActionItemListTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const TODO = '019a0000-3333-7333-8333-000000000001';

    private const OVERDUE = '019a0000-3333-7333-8333-000000000002';

    private const DONE = '019a0000-3333-7333-8333-000000000003';

    private function list(string $account, string $query = '')
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken("mock-{$account}")->getJson('/api/v1/action-items'.$query);
    }

    public function test_pm_sees_all_items_sorted_by_due_date_with_exact_shape(): void
    {
        $response = $this->list('019a0000-f101-7000-8000-000000000007')->assertOk()->assertJsonCount(3);

        $this->assertSame([self::DONE, self::OVERDUE, self::TODO], $response->json('*.actionItemId'));
        $response->assertJsonPath('1.projectId', '019a0000-d101-7000-8000-000000000014')
            ->assertJsonPath('1.picAccountId', '019a0000-f101-7000-8000-000000000091')
            ->assertJsonPath('1.status', 'IN_PROGRESS')
            ->assertJsonPath('1.overdue', true)
            ->assertJsonPath('1.completedAt', null)
            ->assertJsonPath('0.overdue', false)
            ->assertJsonStructure(['*' => ['actionItemId', 'momId', 'projectId', 'description', 'picAccountId', 'dueDate', 'status', 'overdue', 'completedAt', 'createdAt', 'updatedAt']]);
    }

    public function test_tl_sees_all_items(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000042')->assertOk()->assertJsonCount(3);
    }

    public function test_tw_sees_only_own_items(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000091')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.actionItemId', self::OVERDUE);
    }

    public function test_mine_limits_pm_to_own_items(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000007', '?mine=true')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.actionItemId', self::DONE);
    }

    public function test_overdue_filter(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000007', '?overdue=true')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.actionItemId', self::OVERDUE);
    }

    public function test_status_filter(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000007', '?status=TODO')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.actionItemId', self::TODO);
    }

    public function test_project_filter(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000007', '?projectId=019a0000-d101-7000-8000-000000000014')->assertOk()->assertJsonCount(3);
        $this->list('019a0000-f101-7000-8000-000000000007', '?projectId=019a0000-d101-7000-8000-000000000016')->assertOk()->assertExactJson([]);
    }

    public function test_non_member_gets_empty_list(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000092')->assertOk()->assertExactJson([]);
    }

    public function test_item_of_non_final_mom_is_hidden(): void
    {
        Mom::query()->update(['status' => 'IN_REVIEW']);

        $this->list('019a0000-f101-7000-8000-000000000007')->assertOk()->assertExactJson([]);
    }

    public function test_invalid_status_is_400(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000007', '?status=DELAYED')->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_invalid_overdue_is_400(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000007', '?overdue=maybe')->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_without_token_is_401(): void
    {
        $this->getJson('/api/v1/action-items')->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_unknown_project_is_404(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000007', '?projectId=PRJ-NOPE')->assertStatus(404)
            ->assertJsonPath('code', 'PROJECT_NOT_FOUND');
    }

    public function test_project_filter_for_non_member_is_403(): void
    {
        $this->list('019a0000-f101-7000-8000-000000000092', '?projectId=019a0000-d101-7000-8000-000000000014')->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}
