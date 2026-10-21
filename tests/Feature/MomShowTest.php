<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-02 GET /moms/{momId} (getMom). */
class MomShowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const MOM = '22222222-2222-4222-8222-000000000003';

    private function show(string $id, string $account = 'acc-pm-0007')
    {
        return $this->withToken("mock-{$account}")->getJson("/api/v1/moms/{$id}");
    }

    public function test_member_reads_mom_with_action_items(): void
    {
        $this->show(self::MOM, 'acc-tw-0091')->assertOk()
            ->assertJsonPath('momId', self::MOM)
            ->assertJsonPath('meetingId', '11111111-1111-4111-8111-000000000003')
            ->assertJsonPath('projectId', 'PRJ-2026-014')
            ->assertJsonPath('status', 'FINAL')
            ->assertJsonPath('writtenBy', 'acc-tw-0091')
            ->assertJsonPath('reviewedBy', 'acc-tl-0042')
            ->assertJsonCount(3, 'actionItems')
            ->assertJsonStructure(['actionItems' => [['actionItemId', 'momId', 'projectId', 'description', 'picAccountId', 'dueDate', 'status', 'overdue', 'completedAt', 'createdAt', 'updatedAt']]]);
    }

    public function test_pm_can_read_mom(): void
    {
        $this->show(self::MOM, 'acc-pm-0007')->assertOk()->assertJsonPath('momId', self::MOM);
    }

    public function test_overdue_is_computed(): void
    {
        $items = collect($this->show(self::MOM)->json('actionItems'))->keyBy('description');

        $this->assertTrue($items['Perbarui dokumen kebutuhan modul login']['overdue']);
        $this->assertFalse($items['Siapkan server uji baru']['overdue']);
        $this->assertFalse($items['Konfirmasi jadwal demo ke klien']['overdue']);
    }

    public function test_without_token_is_401(): void
    {
        $this->getJson('/api/v1/moms/'.self::MOM)->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_non_member_is_403(): void
    {
        $this->show(self::MOM, 'acc-tw-0092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_mom_is_404(): void
    {
        $this->show('22222222-2222-4222-8222-999999999999')->assertStatus(404)->assertJsonPath('code', 'MOM_NOT_FOUND');
    }
}
