<?php

namespace App\Policies;

use App\Data\Account;
use App\Enums\Role;
use Illuminate\Auth\Access\Response;

class ChangeRequestPolicy
{
    public function create(Account $account): Response
    {
        return $account->is(Role::PM) || $account->is(Role::TL)
            ? Response::allow()
            : Response::deny('Hanya PM atau TL yang dapat mencatat perubahan');
    }

    public function analyze(Account $account): Response
    {
        return $account->is(Role::TL)
            ? Response::allow()
            : Response::deny('Hanya TL yang dapat menganalisis change request');
    }
}