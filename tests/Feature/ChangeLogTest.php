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

    private const URL = '/api/v1/projects/PRJ-2026-014/change-log';

    private const RECORDED = '66666666-6666-4666-8666-000000000001';

    private const PENDING = '66666666-6666-4666-8666-000000000002';

    private function fetch(string $url = self::URL, string $account = 'acc-pm-0007')
    {
        return $this->withToken("mock-{$account}")->getJson($url);
    }

    public function test_member_sees_all_entries_newest_first_with_links(): void
    {
        ChangeRequest::whereKey(self::PENDING)->update(['created_at' => now()->addMinute(), 'baseline_item_id' => 'BSL-014-01']);
        ChangeRequestTask::create([
            'change_request_id' => self::PENDING, 'task_id' => 'TSK-014-001',
            'added_by' => 'acc-tl-0042', 'created_at' => now(),
        ]);

        $response = $this->fetch(account: 'acc-tw-0091')->assertOk()->assertJsonCount(2);

        $response->assertJsonPath('0.changeRequestId', self::PENDING)
            ->assertJsonPath('0.status', 'PENDING_ANALYSIS')
            ->assertJsonPath('0.momId', '22222222-2222-4222-8222-000000000003')
            ->assertJsonPath('0.baselineItemId', 'BSL-014-01')
            ->assertJsonPath('0.affectedTaskIds', ['TSK-014-001'])
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
        $this->fetch(account: 'acc-tw-0092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_project_is_404(): void
    {
        $this->fetch('/api/v1/projects/PRJ-NOPE/change-log')->assertStatus(404)->assertJsonPath('code', 'PROJECT_NOT_FOUND');
    }
}
