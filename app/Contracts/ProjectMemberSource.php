<?php

namespace App\Contracts;

/**
 * Who is a member of a project, as F-1 account ids.
 *
 * Neither D-1 (it only knows amEmployeeId / pmEmployeeId) nor F-1 (it knows accounts, not
 * projects) publishes this today, so it is its own seam: replace the implementation once the
 * owning group (D-1 or F-2) exposes project members. See doc/INTEGRASI_LIVE.md.
 */
interface ProjectMemberSource
{
    /** @return list<string> F-1 accountIds of the project's members; empty if unknown. */
    public function memberAccountIds(string $projectId): array;

    /** @return list<string> Every account id the source knows, for building name lookups. */
    public function allAccountIds(): array;
}
