<?php

namespace App\Contracts;

use App\Data\Task;

/** Project tasks and their progress from E-4 (PRD §9). */
interface TaskDirectory
{
    public function find(string $taskId): ?Task;

    /** @return list<Task> */
    public function forProject(string $projectId): array;

    /** Weighted average progress (0-100), or null if the project has no tasks. */
    public function actualProgress(string $projectId): ?float;
}
