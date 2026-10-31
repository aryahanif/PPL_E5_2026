<?php

namespace App\Providers;

use App\Contracts\BaselineDirectory;
use App\Contracts\IdentityProvider;
use App\Contracts\ProjectDirectory;
use App\Contracts\ProjectMemberSource;
use App\Contracts\TaskDirectory;
use App\Data\Account;
use App\Integrations\Http\BearerTokenAuthenticator;
use App\Integrations\Http\FileProjectMemberSource;
use App\Integrations\Http\Hs256Verifier;
use App\Integrations\Http\HttpIdentityProvider;
use App\Integrations\Http\HttpProjectDirectory;
use App\Integrations\Mock\MockBaselineDirectory;
use App\Integrations\Mock\MockIdentityProvider;
use App\Integrations\Mock\MockProjectDirectory;
use App\Integrations\Mock\MockTaskDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PRD §9: each integration is mock or live, chosen in config('e5.modes').
        // Live adapters are scoped, so their per-request memo never leaks into the next request.
        $this->app->scoped(ProjectMemberSource::class, fn () => new FileProjectMemberSource(config('e5.live.project_members_file')));

        $this->app->scoped(IdentityProvider::class, function ($app) {
            return match (config('e5.modes.f1')) {
                'mock' => new MockIdentityProvider(config('e5.mocks.accounts')),
                'live' => new HttpIdentityProvider(
                    $this->required('e5.live.f1.base_url', 'F1_BASE_URL'),
                    config('e5.live.timeout'),
                    config('e5.live.f1.service_token') ?: null,
                    config('e5.live.f1.role_map'),
                    fn () => $app->bound('request') ? $app['request']->bearerToken() : null,
                    $app->make(ProjectMemberSource::class),
                ),
                default => throw new RuntimeException('F1_MODE must be mock or live.'),
            };
        });

        $this->app->scoped(BearerTokenAuthenticator::class, fn ($app) => new BearerTokenAuthenticator(
            new Hs256Verifier($this->required('e5.live.f1.jwt_secret', 'F1_JWT_SECRET')),
            $app->make(IdentityProvider::class),
        ));

        $this->app->scoped(ProjectDirectory::class, function ($app) {
            return match (config('e5.modes.d1')) {
                'mock' => new MockProjectDirectory(config('e5.mocks.projects')),
                'live' => new HttpProjectDirectory(
                    $this->required('e5.live.d1.base_url', 'D1_BASE_URL'),
                    config('e5.live.timeout'),
                    config('e5.live.d1.token') ?: null,
                    config('e5.live.d1.active_statuses'),
                    $app->make(ProjectMemberSource::class),
                ),
                default => throw new RuntimeException('D1_MODE must be mock or live.'),
            };
        });

        $this->app->singleton(BaselineDirectory::class, function () {
            return match (config('e5.modes.e3')) {
                'mock' => new MockBaselineDirectory(config('e5.mocks.baselines')),
                default => throw new RuntimeException('E3_MODE=live is not implemented yet (needs E-3 API).'),
            };
        });

        $this->app->singleton(TaskDirectory::class, function () {
            return match (config('e5.modes.e4')) {
                'mock' => new MockTaskDirectory(config('e5.mocks.tasks')),
                default => throw new RuntimeException('E4_MODE=live is not implemented yet (needs E-4 API).'),
            };
        });
    }

    /** A live setting that must be present; fail at first use with the variable to fill in. */
    private function required(string $configKey, string $envName): string
    {
        return config($configKey) ?: throw new RuntimeException("{$envName} is required when the integration runs in live mode.");
    }

    public function boot(): void
    {
        // Objects and lists are returned bare, as in openapi.yaml.
        JsonResource::withoutWrapping();

        // API. mock: "Authorization: Bearer mock-<accountId>". live: the F-1 JWT, verified by
        // BearerTokenAuthenticator; a mock token is then just an invalid token.
        Auth::viaRequest('mock-token', function (Request $request): ?Account {
            if (config('e5.modes.f1') === 'live') {
                return app(BearerTokenAuthenticator::class)->authenticate($request);
            }

            $token = (string) $request->bearerToken();

            if (! str_starts_with($token, 'mock-')) {
                return null;
            }

            return app(IdentityProvider::class)->find(substr($token, 5));
        });

        // Pages: account chosen on the dev-login screen, kept in the session.
        // In live mode there is no page login yet (needs F-1's login), so no session is accepted.
        Auth::viaRequest('mock-session', function (Request $request): ?Account {
            if (config('e5.modes.f1') === 'live') {
                return null;
            }

            $accountId = $request->hasSession() ? $request->session()->get('accountId') : null;

            return $accountId ? app(IdentityProvider::class)->find($accountId) : null;
        });
    }
}
