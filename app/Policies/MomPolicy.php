<?php

namespace App\Policies;

use App\Data\Account;
use App\Enums\Role;
use App\Models\Mom;
use Illuminate\Auth\Access\Response;

/**
 * Role rules for MoM (PRD §6). Project membership is checked separately by
 * App\Services\ProjectGuard. Status rules (e.g. only DRAFT/REVISION can be
 * edited) live in the controller/service, not here.
 *
 * Used by API-K05-02 (create, update, view) and API-K05-03 (submit, review).
 */
class MomPolicy
{
    /** Any project member can read a MoM. */
    public function view(Account $account, Mom $mom): Response
    {
        return Response::allow();
    }

    /** API-K05-02: only a TW writes the MoM (Q-01). */
    public function create(Account $account): Response
    {
        return $account->is(Role::TW)
            ? Response::allow()
            : Response::deny('Hanya TW yang dapat menulis MoM');
    }

    /** API-K05-02: only a TW edits the MoM (while DRAFT or REVISION; checked in the controller). */
    public function update(Account $account, Mom $mom): Response
    {
        return $account->is(Role::TW)
            ? Response::allow()
            : Response::deny('Hanya TW yang dapat mengubah MoM');
    }

    /** API-K05-03: only a TW submits the MoM for review (DRAFT/REVISION → IN_REVIEW). */
    public function submit(Account $account, Mom $mom): Response
    {
        return $account->is(Role::TW)
            ? Response::allow()
            : Response::deny('Hanya TW yang dapat mengajukan review MoM');
    }

    /** API-K05-03: only a TL finalizes or requests revision (IN_REVIEW → FINAL/REVISION). Assumption, Q-11. */
    public function review(Account $account, Mom $mom): Response
    {
        return $account->is(Role::TL)
            ? Response::allow()
            : Response::deny('Hanya TL yang dapat mereview MoM');
    }
}
