<?php

namespace App\Contracts;

use App\Data\Project;

/** Projects and their members from D-1 (PRD §9). */
interface ProjectDirectory
{
    public function find(string $projectId): ?Project;

    /** Active projects the account is a member of. @return list<Project> */
    public function activeForAccount(string $accountId): array;
}
