<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MoM list page per project (/projects/{projectId}/moms). */
class MomListPageTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const URL = '/projects/PRJ-2026-014/moms';

    public function test_member_sees_moms_of_the_project(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-pm-0007']);

        $this->get(self::URL)->assertOk()
            ->assertSee('Weekly Meeting Minggu 2')
            ->assertDontSee('Weekly Meeting Minggu 3')
            ->assertSee('href="/moms/22222222-2222-4222-8222-000000000003"', false);
    }

    public function test_every_role_can_open_the_list(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-tw-0091']);

        $this->get(self::URL)->assertOk()->assertSee('Weekly Meeting Minggu 2');
    }

    public function test_status_filter_narrows_the_table(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-pm-0007']);

        $this->get(self::URL.'?status=FINAL')->assertOk()->assertSee('Weekly Meeting Minggu 2');

        $this->get(self::URL.'?status=DRAFT')->assertOk()
            ->assertDontSee('Weekly Meeting Minggu 2')
            ->assertSee('Belum ada MoM');
    }

    public function test_project_without_mom_shows_empty_state(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-pm-0007']);

        $this->get('/projects/PRJ-2026-016/moms')->assertOk()->assertSee('Belum ada MoM');
    }

    public function test_sidebar_links_to_the_list(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-pm-0007']);

        $this->get('/projects/PRJ-2026-014/meetings')->assertOk()
            ->assertSee('href="'.self::URL.'"', false);
    }

    public function test_guest_is_sent_to_dev_login(): void
    {
        $this->get(self::URL)->assertRedirect('/dev-login');
    }

    public function test_non_member_is_403(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-tw-0092']);

        $this->get(self::URL)->assertForbidden();
    }

    public function test_unknown_project_is_404(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-pm-0007']);

        $this->get('/projects/PRJ-0000-000/moms')->assertNotFound();
    }
}
