<?php

namespace App\Data;

/** A project task owned by E-4. E-5 never stores tasks; it only reads them. */
final class Task
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $projectId,
        public readonly string $title,
        public readonly float $weight,
        public readonly float $progress,
    ) {}
}
