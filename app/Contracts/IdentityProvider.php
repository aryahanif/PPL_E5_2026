<?php

namespace App\Contracts;

use App\Data\Account;

/** Accounts and roles from F-1 (PRD §9). */
interface IdentityProvider
{
    public function find(string $accountId): ?Account;

    /** @return list<Account> */
    public function all(): array;
}
