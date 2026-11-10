<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\ChangeRequestTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeLogTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const URL = '/api/v1/projects/019a0000-d101-7000-8000-000000000014/change-log';

    private const RECORDED = '019a0000-6666-7666-8666-000000000001';

    private const PENDING = '019a0000-6666-7666-8666-000000000002';

    private function fetch(string $url = self::URL, string $account = '019a0000-f101-7000-8000-000000000007')
    {
        return $this->withToken("mock-{$account}")->getJson($url);
    }

    public function test_member_sees_all_entries_newest_first_with_links(): void
    {
        ChangeRequest::whereKey(self::PENDING)->update(['created_at' => now()->addMinute(), 'baseline_item_id' => '019a0000-e301-7000-8000-000000014001']);
        ChangeRequestTask::create([
            'change_request_id' => self::PENDING, 'task_id' => '019a0000-e401-7000-8000-000000014001',
            'added_by' => '019a0000-f101-7000-8000-000000000042', 'created_at' => now(),
        ]);

        $response = $this->fetch(account: '019a0000-f101-7000-8000-000000000091')->assertOk()->assertJsonCount(2);

        $response->assertJsonPath('0.changeRequestId', self::PENDING)
            ->assertJsonPath('0.status', 'PENDING_ANALYSIS')
            ->assertJsonPath('0.momId', '019a0000-2222-7222-8222-000000000003')
            ->assertJsonPath('0.baselineItemId', '019a0000-e301-7000-8000-000000014001')
            ->assertJsonPath('0.affectedTaskIds', ['019a0000-e401-7000-8000-000000014001'])
            ->assertJsonPath('1.changeRequestId', self::RECORDED)
            ->assertJsonPath('1.status', 'RECORDED')
            ->assertJsonPath('1.affectedTaskIds', []);
    }

    public function test_status_filter(): void
    {
        $this->fetch(self::URL.'?status=RECORDED')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.changeRequestId', self::RECORDED);
        $this->fetch(self::URL.'?status=APPROVED')->assertOk()->assertExactJson([]);
    }

    public function test_invalid_status_is_400(): void
    {
        $this->fetch(self::URL.'?status=BOGUS')->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_without_token_is_401(): void
    {
        $this->getJson(self::URL)->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_non_member_is_403(): void
    {
        $this->fetch(account: '019a0000-f101-7000-8000-000000000092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_project_is_404(): void
    {
        $this->fetch('/api/v1/projects/PRJ-NOPE/change-log')->assertStatus(404)->assertJsonPath('code', 'PROJECT_NOT_FOUND');
    }
}
