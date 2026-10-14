<?php

namespace App\Data;

use App\Enums\Role;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account owned by F-1. E-5 never stores accounts; it only reads them.
 * Implements Authenticatable so $request->user(), policies and
 * $this->authorize() work as usual.
 */
final class Account implements Authenticatable
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $displayName,
        public readonly Role $role,
    ) {}

    public function is(Role $role): bool
    {
        return $this->role === $role;
    }

    public function getAuthIdentifierName(): string
    {
        return 'accountId';
    }

    public function getAuthIdentifier(): string
    {
        return $this->accountId;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
