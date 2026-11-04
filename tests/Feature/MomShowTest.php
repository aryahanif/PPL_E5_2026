<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API-K05-02 GET /moms/{momId} (getMom). */
class MomShowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const MOM = '019a0000-2222-7222-8222-000000000003';

    private function show(string $id, string $account = '019a0000-f101-7000-8000-000000000007')
    {
        return $this->withToken("mock-{$account}")->getJson("/api/v1/moms/{$id}");
    }

    public function test_member_reads_mom_with_action_items(): void
    {
        $this->show(self::MOM, '019a0000-f101-7000-8000-000000000091')->assertOk()
            ->assertJsonPath('momId', self::MOM)
            ->assertJsonPath('meetingId', '019a0000-1111-7111-8111-000000000003')
            ->assertJsonPath('projectId', '019a0000-d101-7000-8000-000000000014')
            ->assertJsonPath('status', 'FINAL')
            ->assertJsonPath('writtenBy', '019a0000-f101-7000-8000-000000000091')
            ->assertJsonPath('reviewedBy', '019a0000-f101-7000-8000-000000000042')
            ->assertJsonCount(3, 'actionItems')
            ->assertJsonStructure(['actionItems' => [['actionItemId', 'momId', 'projectId', 'description', 'picAccountId', 'dueDate', 'status', 'overdue', 'completedAt', 'createdAt', 'updatedAt']]]);
    }

    public function test_pm_can_read_mom(): void
    {
        $this->show(self::MOM, '019a0000-f101-7000-8000-000000000007')->assertOk()->assertJsonPath('momId', self::MOM);
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
        $this->show(self::MOM, '019a0000-f101-7000-8000-000000000092')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_unknown_mom_is_404(): void
    {
        $this->show('019a0000-2222-7222-8222-999999999999')->assertStatus(404)->assertJsonPath('code', 'MOM_NOT_FOUND');
    }
}
