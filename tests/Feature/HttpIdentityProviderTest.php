<?php

namespace Tests\Feature;

use App\Contracts\IdentityProvider;
use App\Enums\Role;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\LiveIntegration;
use Tests\TestCase;

/** The real F-1 client (API-F1-01 GET /identity/accounts/{accountId}). */
class HttpIdentityProviderTest extends TestCase
{
    use LiveIntegration;

    private function provider(): IdentityProvider
    {
        return $this->app->make(IdentityProvider::class);
    }

    private function asCaller(string $token = 'caller-token'): void
    {
        $this->app['request']->headers->set('Authorization', "Bearer {$token}");
    }

    public function test_active_account_with_a_known_role_is_returned(): void
    {
        $this->useLiveIdentity();
        $this->asCaller();

        $account = $this->provider()->find('acc-tl');

        $this->assertSame('acc-tl', $account->accountId);
        $this->assertSame('Toni TL', $account->displayName);
        $this->assertSame(Role::TL, $account->role);
    }

    public function test_the_callers_token_is_forwarded_to_f1(): void
    {
        $this->useLiveIdentity();
        $this->asCaller('abc.def.ghi');

        $this->provider()->find('acc-pm');

        Http::assertSent(fn (Request $r) => $r->url() === 'http://f1.test/identity/accounts/acc-pm'
            && $r->header('Authorization') === ['Bearer abc.def.ghi']);
    }

    public function test_service_token_is_used_when_there_is_no_caller_token(): void
    {
        $this->useLiveIdentity(serviceToken: 'service-token');

        $this->assertNotNull($this->provider()->find('acc-pm'));

        Http::assertSent(fn (Request $r) => $r->header('Authorization') === ['Bearer service-token']);
    }

    public function test_without_any_credential_f1_is_not_called(): void
    {
        $this->useLiveIdentity();

        $this->assertNull($this->provider()->find('acc-pm'));

        Http::assertNothingSent();
    }

    public function test_an_account_is_asked_for_only_once_per_request(): void
    {
        $this->useLiveIdentity();
        $this->asCaller();

        $this->provider()->find('acc-pm');
        $this->provider()->find('acc-pm');

        Http::assertSentCount(1);
    }

    public function test_unknown_account_is_null(): void
    {
        $this->useLiveIdentity();
        $this->asCaller();

        $this->assertNull($this->provider()->find('acc-nobody'));
    }

    public function test_inactive_and_locked_accounts_are_null(): void
    {
        $this->useLiveIdentity();
        $this->asCaller();

        $this->assertNull($this->provider()->find('acc-off'));
        $this->assertNull($this->provider()->find('acc-locked'));
    }

    public function test_a_role_e5_does_not_use_is_null(): void
    {
        $this->useLiveIdentity();
        $this->asCaller();

        $this->assertNull($this->provider()->find('acc-staff'));
    }

    public function test_role_map_translates_f1_roles(): void
    {
        $this->useLiveIdentity(roleMap: ['PM' => 'PM', 'EMPLOYEE' => 'TW']);
        $this->asCaller();

        $this->assertSame(Role::TW, $this->provider()->find('acc-staff')->role);
        $this->assertNull($this->provider()->find('acc-tl'), 'TL is not in this map');
    }

    public function test_f1_refusing_the_credential_gives_null_not_an_error(): void
    {
        $this->useLiveIdentity();
        $this->fakeHttp(['f1.test/*' => Http::response(['code' => 'INVALID_TOKEN', 'message' => 'x'], 401)]);
        $this->asCaller();

        $this->assertNull($this->provider()->find('acc-pm'));
    }

    public function test_f1_down_is_a_503_not_a_missing_account(): void
    {
        $this->useLiveIdentity();
        $this->fakeHttp(['f1.test/*' => fn () => throw new ConnectionException('refused')]);
        $this->asCaller();

        try {
            $this->provider()->find('acc-pm');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->status);
            $this->assertSame('UPSTREAM_UNAVAILABLE', $e->errorCode);
        }
    }

    public function test_f1_server_error_is_a_503(): void
    {
        $this->useLiveIdentity();
        $this->fakeHttp(['f1.test/*' => Http::response('boom', 500)]);
        $this->asCaller();

        $this->expectException(ApiException::class);
        $this->provider()->find('acc-pm');
    }

    public function test_an_answer_about_another_account_is_rejected(): void
    {
        $this->useLiveIdentity();
        $this->fakeHttp(['f1.test/*' => Http::response($this->f1Accounts()['acc-tw'])]);
        $this->asCaller();

        try {
            $this->provider()->find('acc-pm');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('UPSTREAM_INVALID_RESPONSE', $e->errorCode);
        }
    }

    public function test_all_lists_the_active_members_the_source_knows(): void
    {
        $this->useLiveIdentity();
        $this->useLiveProjects(['p1' => ['acc-pm', 'acc-tw'], 'p2' => ['acc-pm', 'acc-off', 'acc-staff']]);
        $this->asCaller();

        $this->assertSame(['acc-pm', 'acc-tw'], array_map(fn ($a) => $a->accountId, $this->provider()->all()));
    }
}
