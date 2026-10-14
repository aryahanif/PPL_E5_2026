<?php

namespace App\Integrations\Mock;

use App\Contracts\IdentityProvider;
use App\Data\Account;
use App\Enums\Role;

/** Reads fake F-1 accounts from database/mocks/f1_accounts.json. */
class MockIdentityProvider implements IdentityProvider
{
    /** @var array<string, Account> */
    private array $accounts = [];

    public function __construct(string $fixturePath)
    {
        $rows = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $this->accounts[$row['accountId']] = new Account(
                $row['accountId'],
                $row['displayName'],
                Role::from($row['role']),
            );
        }
    }

    public function find(string $accountId): ?Account
    {
        return $this->accounts[$accountId] ?? null;
    }

    public function all(): array
    {
        return array_values($this->accounts);
    }
}
