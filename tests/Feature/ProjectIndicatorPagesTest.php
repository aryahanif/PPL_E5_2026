<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BL-12 on the projects page (screen 2): the PM sees the indicator, other roles do not. */
class ProjectIndicatorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-20 09:00:00');
    }

    public function test_pm_sees_indicator_on_each_project_card(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-pm-0007']);

        $this->get('/projects')->assertOk()
            ->assertSee('Indikator:', false)
            ->assertSeeInOrder(['Proyek Contoh Sistem Informasi', 'ON_TRACK', 'progres 48% dari target 45%'])
            ->assertSeeInOrder(['Proyek Contoh Terlambat', 'DELAYED', 'progres 40% dari target 60%']);
    }

    public function test_tl_does_not_see_the_indicator(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-tl-0042']);

        $this->get('/projects')->assertOk()
            ->assertSee('Proyek Contoh Terlambat')
            ->assertDontSee('Indikator:', false)
            ->assertDontSee('DELAYED')
            ->assertDontSee('ON_TRACK');
    }

    public function test_tw_does_not_see_the_indicator(): void
    {
        $this->post('/dev-login', ['accountId' => 'acc-tw-0091']);

        $this->get('/projects')->assertOk()->assertDontSee('Indikator:', false);
    }
}
