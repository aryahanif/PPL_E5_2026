<?php

namespace Tests\Feature;

use App\Contracts\BaselineDirectory;
use App\Contracts\IdentityProvider;
use App\Contracts\ProjectDirectory;
use App\Contracts\TaskDirectory;
use App\Integrations\Http\BearerTokenAuthenticator;
use App\Integrations\Http\HttpIdentityProvider;
use App\Integrations\Http\HttpProjectDirectory;
use App\Integrations\Mock\MockIdentityProvider;
use App\Integrations\Mock\MockProjectDirectory;
use RuntimeException;
use Tests\Support\LiveIntegration;
use Tests\TestCase;

class IntegrationModesTest extends TestCase
{
    use LiveIntegration;

    public function test_everything_is_mock_by_default(): void
    {
        $this->assertInstanceOf(MockIdentityProvider::class, $this->app->make(IdentityProvider::class));
        $this->assertInstanceOf(MockProjectDirectory::class, $this->app->make(ProjectDirectory::class));
    }

    public function test_f1_and_d1_can_go_live_while_e3_and_e4_stay_mock(): void
    {
        $this->useLiveIdentity();
        $this->useLiveProjects([]);

        $this->assertInstanceOf(HttpIdentityProvider::class, $this->app->make(IdentityProvider::class));
        $this->assertInstanceOf(HttpProjectDirectory::class, $this->app->make(ProjectDirectory::class));
        $this->assertNotNull($this->app->make(BaselineDirectory::class)->targetProgress('PRJ-2026-014', now()));
        $this->assertNotNull($this->app->make(TaskDirectory::class)->actualProgress('PRJ-2026-014'));
    }

    public function test_one_integration_can_go_live_alone(): void
    {
        $this->useLiveProjects([]);

        $this->assertInstanceOf(MockIdentityProvider::class, $this->app->make(IdentityProvider::class));
        $this->assertInstanceOf(HttpProjectDirectory::class, $this->app->make(ProjectDirectory::class));
    }

    public function test_live_without_a_base_url_fails_with_the_variable_name(): void
    {
        config(['e5.modes.f1' => 'live', 'e5.live.f1.base_url' => null]);
        $this->resetAdapters();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('F1_BASE_URL');
        $this->app->make(IdentityProvider::class);
    }

    public function test_live_without_a_jwt_secret_fails(): void
    {
        config(['e5.modes.f1' => 'live', 'e5.live.f1.base_url' => 'http://f1.test', 'e5.live.f1.jwt_secret' => null]);
        $this->resetAdapters();

        $this->expectExceptionMessage('F1_JWT_SECRET');
        $this->app->make(BearerTokenAuthenticator::class);
    }

    public function test_live_without_a_d1_base_url_fails(): void
    {
        config(['e5.modes.d1' => 'live', 'e5.live.d1.base_url' => '']);
        $this->resetAdapters();

        $this->expectExceptionMessage('D1_BASE_URL');
        $this->app->make(ProjectDirectory::class);
    }

    public function test_e3_and_e4_live_are_still_not_implemented(): void
    {
        config(['e5.modes.e3' => 'live', 'e5.modes.e4' => 'live']);
        $this->app->forgetInstance(BaselineDirectory::class);
        $this->app->forgetInstance(TaskDirectory::class);

        $this->expectExceptionMessage('E3_MODE=live is not implemented yet');
        $this->app->make(BaselineDirectory::class);
    }
}
