<?php

namespace App\Policies;

use App\Data\Account;
use App\Data\Project;
use App\Enums\Role;
use Illuminate\Auth\Access\Response;

/**
 * Role rules on a D-1 project (PRD §6; Rancangan Antarmuka UI-K05-06; Hak Akses per Role).
 * Membership is checked separately by ProjectGuard. Laravel finds this policy for App\Data\Project.
 */
class ProjectPolicy
{
    /** BL-12: the safe/late indicator is visible to the PM only. */
    public function viewIndicator(Account $account, Project $project): Response
    {
        return $account->is(Role::PM)
            ? Response::allow()
            : Response::deny('Indikator proyek hanya dapat diakses oleh PM');
    }
}
