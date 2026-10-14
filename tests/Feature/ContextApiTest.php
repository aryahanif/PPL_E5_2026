<?php

namespace Tests\Feature;

use Tests\TestCase;

/** GET /api/v1/me and GET /api/v1/projects (F-1 / D-1 mocks, no database). */
class ContextApiTest extends TestCase
{
    public function test_me_returns_account_and_active_projects(): void
    {
        $this->withToken('mock-acc-pm-0007')->getJson('/api/v1/me')->assertOk()
            ->assertExactJson([
                'accountId' => 'acc-pm-0007',
                'displayName' => 'PM Contoh',
                'role' => 'PM',
                'projectIds' => ['PRJ-2026-014'],
            ]);
    }

    public function test_me_without_token_is_401(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401)
            ->assertExactJson(['code' => 'UNAUTHORIZED', 'message' => 'Token tidak ada atau tidak sah']);
    }

    public function test_projects_hides_inactive_projects(): void
    {
        // acc-pm-0007 is a member of PRJ-2026-014 (active) and PRJ-2026-015 (inactive).
        $this->withToken('mock-acc-pm-0007')->getJson('/api/v1/projects')->assertOk()
            ->assertExactJson([
                ['projectId' => 'PRJ-2026-014', 'name' => 'Proyek Contoh Sistem Informasi', 'active' => true],
            ]);
    }

    public function test_projects_is_empty_when_only_member_of_inactive_project(): void
    {
        $this->withToken('mock-acc-tw-0092')->getJson('/api/v1/projects')->assertOk()->assertExactJson([]);
    }

    public function test_projects_without_token_is_401(): void
    {
        $this->getJson('/api/v1/projects')->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }
}
