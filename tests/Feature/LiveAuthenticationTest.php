<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\LiveIntegration;
use Tests\TestCase;

/** F-1 and D-1 live, end to end through the API: JWT in, project list out. */
class LiveAuthenticationTest extends TestCase
{
    use LiveIntegration;

    private const PROJECT = '01924b1e-7f32-7a81-9b12-45e8a1234567';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLiveIdentity();
        $this->useLiveProjects([self::PROJECT => ['acc-pm', 'acc-tw']]);

        // F-1 and D-1 answers in one fake (a later Http::fake would not override an earlier one).
        $accounts = $this->f1Accounts();
        $this->fakeHttp([
            'f1.test/identity/accounts/*' => fn ($request) => ($accounts[basename($request->url())] ?? null)
                ? Http::response($accounts[basename($request->url())])
                : Http::response(['code' => 'ACCOUNT_NOT_FOUND', 'message' => 'x'], 404),
            'd1.test/api/v1/projects*' => Http::response([
                'data' => [$this->d1Project(self::PROJECT)],
                'meta' => ['total' => 1, 'page' => 1, 'limit' => 100, 'totalPages' => 1],
            ]),
        ]);
    }

    private function me(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/me');
    }

    public function test_a_valid_f1_token_logs_in_with_the_role_and_name_from_f1(): void
    {
        $this->me($this->jwt(['accountId' => 'acc-pm']))->assertOk()
            ->assertExactJson([
                'accountId' => 'acc-pm',
                'displayName' => 'Rina PM',
                'role' => 'PM',
                'projectIds' => [self::PROJECT],
            ]);
    }

    public function test_the_role_comes_from_f1_not_from_the_token(): void
    {
        // The token still says PM, but F-1 now says this account is a TW.
        $this->me($this->jwt(['accountId' => 'acc-tw', 'roleCode' => 'PM']))->assertOk()
            ->assertJsonPath('role', 'TW');
    }

    public function test_a_member_sees_only_their_projects(): void
    {
        $this->me($this->jwt(['accountId' => 'acc-tl']))->assertOk()->assertJsonPath('projectIds', []);
    }

    public function test_mock_tokens_do_not_work_in_live_mode(): void
    {
        $this->me('mock-acc-pm')->assertStatus(401)->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_bad_tokens_are_401(): void
    {
        $this->me($this->jwt(secret: 'another-secret-another-secret-12345'))->assertStatus(401);
        $this->me($this->jwt(['exp' => time() - 10]))->assertStatus(401);
        $this->me($this->jwt(['accountId' => '']))->assertStatus(401);
        $this->me('not-a-jwt')->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_deactivated_and_locked_accounts_are_401_even_with_a_valid_token(): void
    {
        $this->me($this->jwt(['accountId' => 'acc-off']))->assertStatus(401);
        $this->me($this->jwt(['accountId' => 'acc-locked']))->assertStatus(401);
    }

    public function test_an_account_with_a_role_e5_does_not_use_is_401(): void
    {
        $this->me($this->jwt(['accountId' => 'acc-staff', 'roleCode' => 'EMPLOYEE']))->assertStatus(401);
    }

    public function test_an_account_f1_does_not_know_is_401(): void
    {
        $this->me($this->jwt(['accountId' => 'acc-ghost']))->assertStatus(401);
    }

    public function test_f1_being_down_is_503_with_the_api_error_shape(): void
    {
        $this->fakeHttp(['*' => fn () => throw new ConnectionException('refused')]);

        $this->me($this->jwt())->assertStatus(503)
            ->assertExactJson(['code' => 'UPSTREAM_UNAVAILABLE', 'message' => 'Layanan F-1 tidak dapat dihubungi']);
    }

    public function test_d1_being_down_is_503(): void
    {
        $accounts = $this->f1Accounts();
        $this->fakeHttp([
            'f1.test/*' => Http::response($accounts['acc-pm']),
            'd1.test/*' => fn () => throw new ConnectionException('refused'),
        ]);

        $this->me($this->jwt())->assertStatus(503)->assertJsonPath('code', 'UPSTREAM_UNAVAILABLE');
    }

    public function test_project_endpoints_use_live_membership(): void
    {
        $url = '/api/v1/projects/'.self::PROJECT.'/meetings';
        $this->fakeHttp([
            'f1.test/identity/accounts/*' => fn ($r) => Http::response($this->f1Accounts()[basename($r->url())]),
            'd1.test/api/v1/projects/*' => Http::response($this->d1Project(self::PROJECT)),
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->jwt(['accountId' => 'acc-tl']))->getJson($url)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_dev_login_is_closed_in_live_mode(): void
    {
        $this->get('/dev-login')->assertNotFound();
        $this->post('/dev-login', ['accountId' => 'acc-pm'])->assertNotFound();
    }

    public function test_pages_do_not_accept_a_session_in_live_mode(): void
    {
        $this->withSession(['accountId' => 'acc-pm'])->get('/projects')->assertRedirect('/dev-login');
    }
}
