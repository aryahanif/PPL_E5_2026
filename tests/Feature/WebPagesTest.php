<?php

namespace Tests\Feature;

use App\Contracts\ProjectDirectory;
use App\Data\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Blade screens 1-3: dev login, projects, meetings list. */
class WebPagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_guest_is_sent_to_dev_login(): void
    {
        $this->get('/projects')->assertRedirect('/dev-login');

        $this->get('/dev-login')->assertOk()->assertSee('acc-pm-0007')->assertSee('acc-tw-0092');
    }

    public function test_dev_login_then_projects_and_meetings_pages(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-tw-0091'])->assertRedirect('/projects');

        $this->get('/projects')->assertOk()
            ->assertSee('Proyek Contoh Sistem Informasi')
            ->assertDontSee('Proyek Contoh Nonaktif');

        $this->get('/projects/PRJ-2026-014/meetings')->assertOk()
            ->assertSeeInOrder(['Weekly Meeting Minggu 4', 'Weekly Meeting Minggu 3', 'Weekly Meeting Minggu 2'])
            ->assertSee('FINAL');
    }

    public function test_dev_login_rejects_unknown_account(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-unknown'])->assertStatus(422);
    }

    public function test_meetings_page_for_non_member_is_403(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-tw-0092']);

        $this->get('/projects/PRJ-2026-014/meetings')->assertForbidden();
    }

    public function test_sidebar_menus_use_last_opened_project_on_projects_page(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-pm-0007']);

        $this->get('/projects/PRJ-2026-016/meetings')->assertOk();

        $this->get('/projects')->assertOk()
            ->assertSee('href="/projects/PRJ-2026-016/risk-issues"', false)
            ->assertSee('Change Request');

        $this->get('/action-items')->assertOk()
            ->assertSee('href="/projects/PRJ-2026-016/risk-issues"', false)
            ->assertSee('href="/action-items"', false);
    }

    public function test_project_menus_are_hidden_until_a_project_is_chosen(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-pm-0007']);

        $this->get('/projects')->assertOk()
            ->assertSee('Action Items')
            ->assertDontSee('Rapat</a>', false)
            ->assertDontSee('MoM</a>', false)
            ->assertDontSee('Change Request')
            ->assertDontSee('Risiko &amp; Isu', false);
    }

    public function test_single_active_project_is_used_automatically(): void
    {
        $this->app->instance(ProjectDirectory::class, new class implements ProjectDirectory
        {
            public function find(string $projectId): ?Project
            {
                return $this->activeForAccount('')[0];
            }

            public function activeForAccount(string $accountId): array
            {
                return [new Project('PRJ-2026-014', 'Proyek Tunggal', true, ['acc-tw-0091'])];
            }
        });

        $this->post('/dev-login', ['accountId' => 'acc-tw-0091']);

        $this->get('/projects')->assertOk()
            ->assertSee('href="/projects/PRJ-2026-014/risk-issues"', false);
    }

    public function test_session_project_of_another_account_is_ignored(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-tw-0092']);

        $this->withSession(['currentProjectId' => 'PRJ-2026-014'])
            ->get('/projects')->assertOk()
            ->assertDontSee('PRJ-2026-014')
            ->assertDontSee('Proyek Contoh Sistem Informasi')
            ->assertSessionMissing('currentProjectId');
    }
}
