<?php

namespace App\Data;

/** A project owned by D-1. E-5 never stores projects; it only reads them. */
final class Project
{
    /** @param list<string> $memberAccountIds */
    public function __construct(
        public readonly string $projectId,
        public readonly string $name,
        public readonly bool $active,
        public readonly array $memberAccountIds,
    ) {}

    public function hasMember(string $accountId): bool
    {
        return in_array($accountId, $this->memberAccountIds, true);
    }
}
