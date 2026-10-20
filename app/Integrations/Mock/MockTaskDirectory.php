<?php

namespace App\Integrations\Mock;

use App\Contracts\TaskDirectory;
use App\Data\Task;

/** Reads fake E-4 tasks from database/mocks/e4_tasks.json. */
class MockTaskDirectory implements TaskDirectory
{
    /** @var array<string, Task> */
    private array $tasks = [];

    public function __construct(string $fixturePath)
    {
        $rows = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $this->tasks[$row['taskId']] = new Task(
                $row['taskId'],
                $row['projectId'],
                $row['title'],
                $row['weight'],
                $row['progress'],
            );
        }
    }

    public function find(string $taskId): ?Task
    {
        return $this->tasks[$taskId] ?? null;
    }

    public function forProject(string $projectId): array
    {
        return array_values(array_filter($this->tasks, fn (Task $t) => $t->projectId === $projectId));
    }

    public function actualProgress(string $projectId): ?float
    {
        $tasks = $this->forProject($projectId);
        $weight = array_sum(array_map(fn (Task $t) => $t->weight, $tasks));

        if ($weight <= 0) {
            return null;
        }

        return array_sum(array_map(fn (Task $t) => $t->weight * $t->progress, $tasks)) / $weight;
    }
}
