<?php

namespace App\Policies;

use App\Data\Account;
use App\Enums\Role;
use App\Models\Meeting;
use Illuminate\Auth\Access\Response;

/**
 * Role rules for meetings (PRD §6). Project membership is checked separately
 * by App\Services\ProjectGuard. Laravel finds this policy automatically
 * for App\Models\Meeting.
 */
class MeetingPolicy
{
    /** Any project member can list meetings. */
    public function viewAny(Account $account): Response
    {
        return Response::allow();
    }

    /** US-1: only a PM schedules meetings. Used by the "create meeting" task. */
    public function create(Account $account): Response
    {
        return $account->is(Role::PM)
            ? Response::allow()
            : Response::deny('Hanya PM yang dapat menjadwalkan rapat');
    }
}
