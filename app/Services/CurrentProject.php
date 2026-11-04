<?php

namespace App\Services;

use App\Contracts\ProjectDirectory;
use App\Data\Account;
use App\Data\Project;
use App\Exceptions\ApiException;
use Illuminate\Http\Request;

/**
 * The project the sidebar works on: the page's project, else the last one opened
 * (kept in the session), else the account's only active project.
 */
class CurrentProject
{
    private const SESSION_KEY = 'currentProjectId';

    public function __construct(
        private ProjectGuard $guard,
        private ProjectDirectory $directory,
        private Request $request,
    ) {}

    public function resolve(Account $account, ?Project $page = null): ?Project
    {
        $session = $this->request->hasSession() ? $this->request->session() : null;
        $projectId = $page?->projectId ?? $session?->get(self::SESSION_KEY);
        $project = $projectId ? $this->forMember($projectId, $account) : null;

        if ($project) {
            $session?->put(self::SESSION_KEY, $project->projectId);

            return $project;
        }

        $session?->forget(self::SESSION_KEY);
        $active = $this->directory->activeForAccount($account->accountId);

        return count($active) === 1 ? $active[0] : null;
    }

    /** The session may outlive a membership, so a rejected project just counts as not chosen. */
    private function forMember(string $projectId, Account $account): ?Project
    {
        try {
            return $this->guard->forMember($projectId, $account);
        } catch (ApiException) {
            return null;
        }
    }
}
