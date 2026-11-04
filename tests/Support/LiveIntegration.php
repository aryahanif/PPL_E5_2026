<?php

namespace Tests\Support;

use App\Contracts\IdentityProvider;
use App\Contracts\ProjectDirectory;
use App\Contracts\ProjectMemberSource;
use App\Integrations\Http\BearerTokenAuthenticator;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Helpers to run F-1 / D-1 in live mode against faked HTTP, shaped like the real services. */
trait LiveIntegration
{
    protected string $jwtSecret = 'unit-test-secret-at-least-32-characters';

    /** Real F-1 seed accounts (F-1 repo, seed/seed-accounts.json), as GET /identity/accounts/{id} answers. */
    protected function f1Accounts(): array
    {
        return [
            'acc-pm' => ['accountId' => 'acc-pm', 'employeeId' => 'emp-1', 'name' => 'Rina PM', 'roleCode' => 'PM', 'position' => null, 'status' => 'active'],
            'acc-tl' => ['accountId' => 'acc-tl', 'employeeId' => 'emp-2', 'name' => 'Toni TL', 'roleCode' => 'TL', 'position' => null, 'status' => 'active'],
            'acc-tw' => ['accountId' => 'acc-tw', 'employeeId' => 'emp-3', 'name' => 'Tia TW', 'roleCode' => 'TW', 'position' => null, 'status' => 'active'],
            'acc-staff' => ['accountId' => 'acc-staff', 'employeeId' => 'emp-4', 'name' => 'Dwi Staf', 'roleCode' => 'EMPLOYEE', 'position' => 'Staf Operasional', 'status' => 'active'],
            'acc-locked' => ['accountId' => 'acc-locked', 'employeeId' => 'emp-5', 'name' => 'Ami Terkunci', 'roleCode' => 'TW', 'position' => null, 'status' => 'locked'],
            'acc-off' => ['accountId' => 'acc-off', 'employeeId' => 'emp-6', 'name' => 'Ica Nonaktif', 'roleCode' => 'PM', 'position' => null, 'status' => 'inactive'],
        ];
    }

    protected function useLiveIdentity(array $roleMap = ['PM' => 'PM', 'TL' => 'TL', 'TW' => 'TW'], ?string $serviceToken = null): void
    {
        config([
            'e5.modes.f1' => 'live',
            'e5.live.f1.base_url' => 'http://f1.test',
            'e5.live.f1.jwt_secret' => $this->jwtSecret,
            'e5.live.f1.service_token' => $serviceToken,
            'e5.live.f1.role_map' => $roleMap,
        ]);

        $this->fakeF1();
        $this->resetAdapters();
    }

    /** @param array<string, list<string>> $members D-1 projectId => F-1 accountIds */
    protected function useLiveProjects(array $members, array $activeStatuses = ['REGISTERED']): void
    {
        $file = tempnam(sys_get_temp_dir(), 'members');
        file_put_contents($file, json_encode($members));

        config([
            'e5.modes.d1' => 'live',
            'e5.live.d1.base_url' => 'http://d1.test/api/v1',
            'e5.live.d1.token' => null,
            'e5.live.d1.active_statuses' => $activeStatuses,
            'e5.live.project_members_file' => $file,
        ]);

        $this->resetAdapters();
    }

    /** Replace every earlier fake (Http::fake alone only adds stubs after the existing ones). */
    protected function fakeHttp(array $stubs): void
    {
        Http::swap(new Factory);
        Http::fake($stubs);
    }

    protected function fakeF1(?array $accounts = null): void
    {
        $accounts ??= $this->f1Accounts();

        $this->fakeHttp([
            'f1.test/identity/accounts/*' => function (Request $request) use ($accounts) {
                if (! $request->hasHeader('Authorization')) {
                    return Http::response(['code' => 'INVALID_TOKEN', 'message' => 'Token tidak ada.'], 401);
                }

                $id = basename(parse_url($request->url(), PHP_URL_PATH));

                return isset($accounts[$id])
                    ? Http::response($accounts[$id])
                    : Http::response(['code' => 'ACCOUNT_NOT_FOUND', 'message' => 'accountId tidak ditemukan.'], 404);
            },
        ]);
    }

    protected function resetAdapters(): void
    {
        foreach ([IdentityProvider::class, ProjectDirectory::class, ProjectMemberSource::class, BearerTokenAuthenticator::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        $this->app['auth']->forgetGuards();
    }

    /** An F-1 style token: HS256, claims accountId + roleCode + iat + exp. */
    protected function jwt(array $claims = [], ?string $secret = null, array $header = ['alg' => 'HS256', 'typ' => 'JWT']): string
    {
        $claims += ['accountId' => 'acc-pm', 'roleCode' => 'PM', 'iat' => time(), 'exp' => time() + 3600];
        $b64 = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $signingInput = $b64($header).'.'.$b64($claims);
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $signingInput, $secret ?? $this->jwtSecret, true)), '+/', '-_'), '=');

        return "{$signingInput}.{$signature}";
    }

    /** What D-1 returns for one project (D-1 repo: prisma Project + auditLogs, with the real field names). */
    protected function d1Project(string $projectId, string $status = 'REGISTERED', string $code = '202601'): array
    {
        return [
            'projectId' => $projectId,
            'canonicalCode' => $code,
            'projectName' => "Proyek {$projectId}",
            'clientId' => 'CL-001',
            'clientName' => 'PT Cakra Infrastruktur',
            'amEmployeeId' => 'SDM-0012',
            'amName' => 'Andi Maulana',
            'pmEmployeeId' => 'SDM-0004',
            'pmName' => 'Budi Santoso',
            'startDate' => '2026-03-01',
            'targetEndDate' => '2026-08-31',
            'indicativeValue' => 950000000,
            'status' => $status,
            'poReceived' => true,
            'documentsComplete' => true,
            'rejectionNotes' => null,
            'createdAt' => '2026-02-15T08:30:00.000Z',
            'updatedAt' => '2026-02-18T10:00:00.000Z',
            'auditLogs' => [],
        ];
    }
}
