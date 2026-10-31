<?php

namespace App\Integrations\Http;

use App\Contracts\ProjectDirectory;
use App\Contracts\ProjectMemberSource;
use App\Data\Project;

/**
 * Real D-1 client (API-K01-07 GET /projects/{projectId}, API-K01-08 GET /projects).
 *
 * - "Active" = D-1 status is one of the configured statuses (default REGISTERED).
 * - E-5 keys all its data by D-1's UUID. D-1 also answers to the 6-digit canonicalCode, which
 *   must NOT be accepted here, or the same project would be stored under two ids.
 * - Members do not come from D-1 (it has none); they come from the ProjectMemberSource.
 */
class HttpProjectDirectory implements ProjectDirectory
{
    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 20;

    /** @var array<string, ?Project> */
    private array $memo = [];

    /** @param list<string> $activeStatuses */
    public function __construct(
        private string $baseUrl,
        private int $timeout,
        private ?string $token,
        private array $activeStatuses,
        private ProjectMemberSource $members,
    ) {}

    public function find(string $projectId): ?Project
    {
        if (array_key_exists($projectId, $this->memo)) {
            return $this->memo[$projectId];
        }

        $response = UpstreamCall::get('D-1', $this->url('/projects/'.rawurlencode($projectId)), [], $this->token, $this->timeout);

        $row = $response->json();

        if (! $response->successful() || ! is_array($row) || ($row['projectId'] ?? null) !== $projectId) {
            return $this->memo[$projectId] = null;
        }

        return $this->memo[$projectId] = $this->toProject($row);
    }

    public function activeForAccount(string $accountId): array
    {
        $found = [];

        foreach ($this->activeStatuses as $status) {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $response = UpstreamCall::get('D-1', $this->url('/projects'), [
                    'status' => $status,
                    'page' => $page,
                    'limit' => self::PAGE_SIZE,
                ], $this->token, $this->timeout);

                $body = $response->json();

                if (! $response->successful() || ! is_array($body)) {
                    break;
                }

                foreach ($body['data'] ?? [] as $row) {
                    $project = $this->toProject($row);

                    if ($project->active && $project->hasMember($accountId)) {
                        $found[$project->projectId] = $this->memo[$project->projectId] = $project;
                    }
                }

                if ($page >= (int) ($body['meta']['totalPages'] ?? 1)) {
                    break;
                }
            }
        }

        return array_values($found);
    }

    /** @param array<string, mixed> $row */
    private function toProject(array $row): Project
    {
        return new Project(
            (string) $row['projectId'],
            (string) ($row['projectName'] ?? $row['projectId']),
            in_array($row['status'] ?? null, $this->activeStatuses, true),
            $this->members->memberAccountIds((string) $row['projectId']),
        );
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
