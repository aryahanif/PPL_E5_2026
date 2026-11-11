<?php

namespace Tests\Feature;

use Tests\TestCase;

/** GET /api/v1/me and GET /api/v1/projects (F-1 / D-1 mocks, no database). */
class ContextApiTest extends TestCase
{
    public function test_me_returns_account_and_active_projects(): void
    {
        $this->withToken('mock-019a0000-f101-7000-8000-000000000007')->getJson('/api/v1/me')->assertOk()
            ->assertExactJson([
                'accountId' => '019a0000-f101-7000-8000-000000000007',
                'displayName' => 'PM Contoh',
                'role' => 'PM',
                'projectIds' => ['019a0000-d101-7000-8000-000000000014', '019a0000-d101-7000-8000-000000000016'],
            ]);
    }

    public function test_me_without_token_is_401(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401)
            ->assertExactJson(['code' => 'UNAUTHORIZED', 'message' => 'Token tidak ada atau tidak sah']);
    }

    public function test_projects_hides_inactive_projects(): void
    {
        // 019a0000-f101-7000-8000-000000000007 is a member of 019a0000-d101-7000-8000-000000000014 (active) and 019a0000-d101-7000-8000-000000000015 (inactive).
        $this->withToken('mock-019a0000-f101-7000-8000-000000000007')->getJson('/api/v1/projects')->assertOk()
            ->assertExactJson([
                ['projectId' => '019a0000-d101-7000-8000-000000000014', 'name' => 'Proyek Contoh Sistem Informasi', 'active' => true],
                ['projectId' => '019a0000-d101-7000-8000-000000000016', 'name' => 'Proyek Contoh Terlambat', 'active' => true],
            ]);
    }

    public function test_projects_is_empty_when_only_member_of_inactive_project(): void
    {
        $this->withToken('mock-019a0000-f101-7000-8000-000000000092')->getJson('/api/v1/projects')->assertOk()->assertExactJson([]);
    }

    public function test_projects_without_token_is_401(): void
    {
        $this->getJson('/api/v1/projects')->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }
}
