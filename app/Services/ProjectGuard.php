<?php

namespace App\Services;

use App\Contracts\ProjectDirectory;
use App\Data\Account;
use App\Data\Project;
use App\Exceptions\ApiException;

/**
 * Checks a projectId from the URL against D-1 and the caller's membership.
 * Use it at the start of every endpoint that has {projectId}.
 */
class ProjectGuard
{
    public function __construct(private ProjectDirectory $projects) {}

    /** 404 PROJECT_NOT_FOUND if unknown, 403 FORBIDDEN if the account is not a member. */
    public function forMember(string $projectId, Account $account): Project
    {
        $project = $this->projects->find($projectId);

        if (! $project) {
            throw new ApiException(404, 'PROJECT_NOT_FOUND', "Proyek {$projectId} tidak ditemukan");
        }

        if (! $project->hasMember($account->accountId)) {
            throw new ApiException(403, 'FORBIDDEN', 'Anda bukan anggota proyek ini');
        }

        return $project;
    }

    /** Same as forMember, plus 409 PROJECT_INACTIVE for inactive projects (e.g. creating meetings). */
    public function forActiveMember(string $projectId, Account $account): Project
    {
        $project = $this->forMember($projectId, $account);

        if (! $project->active) {
            throw new ApiException(409, 'PROJECT_INACTIVE', "Proyek {$projectId} tidak aktif");
        }

        return $project;
    }
}
