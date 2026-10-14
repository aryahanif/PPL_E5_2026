<?php

namespace App\Integrations\Mock;

use App\Contracts\ProjectDirectory;
use App\Data\Project;

/** Reads fake D-1 projects from database/mocks/d1_projects.json. */
class MockProjectDirectory implements ProjectDirectory
{
    /** @var array<string, Project> */
    private array $projects = [];

    public function __construct(string $fixturePath)
    {
        $rows = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $this->projects[$row['projectId']] = new Project(
                $row['projectId'],
                $row['name'],
                $row['active'],
                $row['memberAccountIds'],
            );
        }
    }

    public function find(string $projectId): ?Project
    {
        return $this->projects[$projectId] ?? null;
    }

    public function activeForAccount(string $accountId): array
    {
        return array_values(array_filter(
            $this->projects,
            fn (Project $p) => $p->active && $p->hasMember($accountId),
        ));
    }
}
