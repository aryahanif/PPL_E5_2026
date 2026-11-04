<?php

namespace App\Integrations\Http;

use App\Contracts\IdentityProvider;
use App\Contracts\ProjectMemberSource;
use App\Data\Account;
use App\Enums\Role;
use App\Exceptions\ApiException;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Real F-1 client (API-F1-01 GET /identity/accounts/{accountId}).
 *
 * - Only `active` accounts are returned; inactive and locked ones are "not found" for E-5, so
 *   they can neither log in nor be picked as PIC or participant.
 * - F-1 role codes are translated with the configured map; a role E-5 does not use
 *   (EMPLOYEE, SPECIALIST, ...) gives null. F-1 has no TL/TW role yet, see doc/INTEGRASI_LIVE.md.
 * - Credentials: the caller's own bearer token (F-1 accepts any valid token for any account),
 *   otherwise the optional service token. With neither, nothing is sent and null is returned.
 */
class HttpIdentityProvider implements IdentityProvider
{
    /** @var array<string, ?Account> */
    private array $memo = [];

    /**
     * @param  array<string, string>  $roleMap  F-1 roleCode => E-5 role (PM, TL, TW)
     * @param  Closure(): ?string  $currentToken  the bearer token of the request being served
     */
    public function __construct(
        private string $baseUrl,
        private int $timeout,
        private ?string $serviceToken,
        private array $roleMap,
        private Closure $currentToken,
        private ProjectMemberSource $members,
    ) {}

    public function find(string $accountId): ?Account
    {
        return $this->resolve($accountId, null);
    }

    /** Accounts of every project member the member source knows (F-1 has no open "list accounts"). */
    public function all(): array
    {
        $accounts = array_map(fn (string $id) => $this->find($id), $this->members->allAccountIds());

        return array_values(array_filter($accounts));
    }

    /** Like find(), but authenticating with an explicit token (the one just verified). */
    public function resolve(string $accountId, ?string $token): ?Account
    {
        if (array_key_exists($accountId, $this->memo)) {
            return $this->memo[$accountId];
        }

        $token ??= ($this->currentToken)() ?: $this->serviceToken;

        if (! $token) {
            Log::warning("No credential to ask F-1 about account {$accountId}.");

            return null;
        }

        $response = UpstreamCall::get('F-1', rtrim($this->baseUrl, '/').'/identity/accounts/'.rawurlencode($accountId), [], $token, $this->timeout);

        if ($response->status() === 404 || $response->status() === 400) {
            return $this->memo[$accountId] = null;
        }

        if ($response->status() === 401 || $response->status() === 403) {
            Log::warning("F-1 refused the credential while looking up account {$accountId} ({$response->status()}).");

            return null;
        }

        $identity = $response->json();

        if (! $response->successful() || ! is_array($identity) || ($identity['accountId'] ?? null) !== $accountId
            || ! is_string($identity['roleCode'] ?? null) || ! is_string($identity['status'] ?? null)) {
            throw new ApiException(503, 'UPSTREAM_INVALID_RESPONSE', 'Jawaban F-1 tidak sesuai kontrak');
        }

        return $this->memo[$accountId] = $this->toAccount($identity);
    }

    /** @param array<string, mixed> $identity */
    private function toAccount(array $identity): ?Account
    {
        if ($identity['status'] !== 'active') {
            return null;
        }

        $role = Role::tryFrom($this->roleMap[$identity['roleCode']] ?? '');

        if (! $role) {
            return null;
        }

        return new Account($identity['accountId'], (string) ($identity['name'] ?? $identity['accountId']), $role);
    }
}
