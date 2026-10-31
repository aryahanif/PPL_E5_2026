<?php

namespace Tests\Feature;

use App\Contracts\ProjectDirectory;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\LiveIntegration;
use Tests\TestCase;

/** The real D-1 client (API-K01-07 detail, API-K01-08 list). */
class HttpProjectDirectoryTest extends TestCase
{
    use LiveIntegration;

    private const A = '01924b1e-7f32-7a81-9b12-45e8a1234567';

    private const B = '01924b1e-7f32-7a81-9b12-45e8a7654321';

    private function directory(): ProjectDirectory
    {
        return $this->app->make(ProjectDirectory::class);
    }

    private function d1Detail(array $projects): void
    {
        $this->fakeHttp([
            'd1.test/api/v1/projects/*' => function (Request $request) use ($projects) {
                $key = basename(parse_url($request->url(), PHP_URL_PATH));

                foreach ($projects as $project) {
                    if (in_array($key, [$project['projectId'], $project['canonicalCode']], true)) {
                        return Http::response($project); // like D-1: the UUID and the 6-digit code both work
                    }
                }

                return Http::response(['code' => 'PROJECT_NOT_FOUND', 'message' => 'x', 'details' => ['requestedProjectId' => $key]], 404);
            },
        ]);
    }

    public function test_registered_project_is_active_and_gets_its_members(): void
    {
        $this->useLiveProjects([self::A => ['acc-pm', 'acc-tw']]);
        $this->d1Detail([$this->d1Project(self::A)]);

        $project = $this->directory()->find(self::A);

        $this->assertSame(self::A, $project->projectId);
        $this->assertSame('Proyek '.self::A, $project->name);
        $this->assertTrue($project->active);
        $this->assertTrue($project->hasMember('acc-tw'));
        $this->assertFalse($project->hasMember('acc-tl'));
    }

    public function test_other_d1_statuses_are_not_active(): void
    {
        $this->useLiveProjects([self::A => ['acc-pm']]);

        foreach (['DRAFT', 'UNDER_REVIEW', 'REVISI_HANDOVER'] as $status) {
            $this->d1Detail([$this->d1Project(self::A, $status)]);
            $this->resetAdapters();

            $this->assertFalse($this->directory()->find(self::A)->active, $status);
        }
    }

    public function test_active_statuses_are_configurable(): void
    {
        $this->useLiveProjects([self::A => ['acc-pm']], activeStatuses: ['REGISTERED', 'UNDER_REVIEW']);
        $this->d1Detail([$this->d1Project(self::A, 'UNDER_REVIEW')]);

        $this->assertTrue($this->directory()->find(self::A)->active);
    }

    public function test_unknown_project_is_null(): void
    {
        $this->useLiveProjects([]);
        $this->d1Detail([]);

        $this->assertNull($this->directory()->find('01924b1e-0000-7000-8000-000000000000'));
    }

    public function test_the_canonical_code_is_not_accepted_as_an_id(): void
    {
        $this->useLiveProjects([self::A => ['acc-pm']]);
        $this->d1Detail([$this->d1Project(self::A, code: '202601')]);

        $this->assertNull($this->directory()->find('202601'), 'E-5 keys data by the D-1 UUID only');
    }

    public function test_project_without_known_members_has_none(): void
    {
        $this->useLiveProjects([]);
        $this->d1Detail([$this->d1Project(self::A)]);

        $this->assertSame([], $this->directory()->find(self::A)->memberAccountIds);
    }

    public function test_the_id_is_url_encoded(): void
    {
        $this->useLiveProjects([]);
        $this->d1Detail([]);

        $this->directory()->find('../x?y=1');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/projects/..%2Fx%3Fy%3D1'));
    }

    public function test_d1_down_is_a_503(): void
    {
        $this->useLiveProjects([]);
        $this->fakeHttp(['d1.test/*' => fn () => throw new ConnectionException('refused')]);

        try {
            $this->directory()->find(self::A);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->status);
            $this->assertSame('UPSTREAM_UNAVAILABLE', $e->errorCode);
        }
    }

    public function test_active_projects_of_an_account_come_from_the_list_filtered_by_membership(): void
    {
        $this->useLiveProjects([self::A => ['acc-pm'], self::B => ['acc-tw']]);
        $this->fakeHttp(['d1.test/api/v1/projects*' => Http::response([
            'data' => [$this->d1Project(self::A, code: '202601'), $this->d1Project(self::B, code: '202602')],
            'meta' => ['total' => 2, 'page' => 1, 'limit' => 100, 'totalPages' => 1],
        ])]);

        $projects = $this->directory()->activeForAccount('acc-pm');

        $this->assertSame([self::A], array_map(fn ($p) => $p->projectId, $projects));
        Http::assertSent(fn (Request $r) => $r['status'] === 'REGISTERED' && $r['limit'] === 100 && $r['page'] === 1);
    }

    public function test_every_page_of_the_list_is_read(): void
    {
        $this->useLiveProjects([self::A => ['acc-pm'], self::B => ['acc-pm']]);
        $this->fakeHttp(['d1.test/api/v1/projects*' => function (Request $request) {
            $page = (int) $request['page'];

            return Http::response([
                'data' => [$this->d1Project($page === 1 ? self::A : self::B)],
                'meta' => ['total' => 2, 'page' => $page, 'limit' => 1, 'totalPages' => 2],
            ]);
        }]);

        $projects = $this->directory()->activeForAccount('acc-pm');

        $this->assertSame([self::A, self::B], array_map(fn ($p) => $p->projectId, $projects));
        Http::assertSentCount(2);
    }

    public function test_one_list_call_per_active_status(): void
    {
        $this->useLiveProjects([self::A => ['acc-pm']], activeStatuses: ['REGISTERED', 'UNDER_REVIEW']);
        $this->fakeHttp(['d1.test/api/v1/projects*' => fn (Request $r) => Http::response([
            'data' => $r['status'] === 'REGISTERED' ? [$this->d1Project(self::A)] : [],
            'meta' => ['total' => 1, 'page' => 1, 'limit' => 100, 'totalPages' => 1],
        ])]);

        $this->assertCount(1, $this->directory()->activeForAccount('acc-pm'));
        Http::assertSentCount(2);
    }

    public function test_the_optional_d1_token_is_sent(): void
    {
        $this->useLiveProjects([]);
        config(['e5.live.d1.token' => 'd1-secret']);
        $this->resetAdapters();
        $this->d1Detail([]);

        $this->directory()->find(self::A);

        Http::assertSent(fn (Request $r) => $r->header('Authorization') === ['Bearer d1-secret']);
    }
}
