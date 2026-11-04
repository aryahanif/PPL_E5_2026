<?php

namespace Tests\Feature;

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

        $this->get('/dev-login')->assertOk()->assertSee('019a0000-f101-7000-8000-000000000007')->assertSee('019a0000-f101-7000-8000-000000000092');
    }

    public function test_dev_login_then_projects_and_meetings_pages(): void
    {
        $this->post('/dev-login', ['accountId' => '019a0000-f101-7000-8000-000000000091'])->assertRedirect('/projects');

        $this->get('/projects')->assertOk()
            ->assertSee('Proyek Contoh Sistem Informasi')
            ->assertDontSee('Proyek Contoh Nonaktif');

        $this->get('/projects/019a0000-d101-7000-8000-000000000014/meetings')->assertOk()
            ->assertSeeInOrder(['Weekly Meeting Minggu 4', 'Weekly Meeting Minggu 3', 'Weekly Meeting Minggu 2'])
            ->assertSee('FINAL');
    }

    public function test_dev_login_rejects_unknown_account(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-unknown'])->assertStatus(422);
    }

    public function test_meetings_page_for_non_member_is_403(): void
    {
        $this->post('/dev-login', ['accountId' => '019a0000-f101-7000-8000-000000000092']);

        $this->get('/projects/019a0000-d101-7000-8000-000000000014/meetings')->assertForbidden();
    }
}
