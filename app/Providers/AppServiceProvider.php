<?php

namespace App\Providers;

use App\Contracts\IdentityProvider;
use App\Contracts\ProjectDirectory;
use App\Data\Account;
use App\Integrations\Mock\MockIdentityProvider;
use App\Integrations\Mock\MockProjectDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PRD §9: swap mock adapters for real F-1 / D-1 clients via INTEGRATION_MODE.
        $this->app->singleton(IdentityProvider::class, function () {
            return match (config('e5.integration_mode')) {
                'mock' => new MockIdentityProvider(config('e5.mocks.accounts')),
                default => throw new RuntimeException('INTEGRATION_MODE=live is not implemented yet (needs F-1 API).'),
            };
        });

        $this->app->singleton(ProjectDirectory::class, function () {
            return match (config('e5.integration_mode')) {
                'mock' => new MockProjectDirectory(config('e5.mocks.projects')),
                default => throw new RuntimeException('INTEGRATION_MODE=live is not implemented yet (needs D-1 API).'),
            };
        });
    }

    public function boot(): void
    {
        // Objects and lists are returned bare, as in openapi.yaml.
        JsonResource::withoutWrapping();

        // API: "Authorization: Bearer mock-<accountId>" (until F-1 publishes its token scheme).
        Auth::viaRequest('mock-token', function (Request $request): ?Account {
            $token = (string) $request->bearerToken();

            if (! str_starts_with($token, 'mock-')) {
                return null;
            }

            return app(IdentityProvider::class)->find(substr($token, 5));
        });

        // Pages: account chosen on the dev-login screen, kept in the session.
        Auth::viaRequest('mock-session', function (Request $request): ?Account {
            $accountId = $request->hasSession() ? $request->session()->get('accountId') : null;

            return $accountId ? app(IdentityProvider::class)->find($accountId) : null;
        });
    }
}
