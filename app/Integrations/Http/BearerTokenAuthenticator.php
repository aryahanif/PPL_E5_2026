<?php

namespace App\Integrations\Http;

use App\Data\Account;
use Illuminate\Http\Request;

/**
 * API authentication against the real F-1: "Authorization: Bearer <F-1 JWT>".
 * 1. verify the signature and expiry locally (shared secret), 2. read accountId from the claims,
 * 3. ask F-1 who that is NOW, so a deactivated account or a changed role takes effect before the
 * token expires. The role comes from F-1's answer, not from the token.
 */
class BearerTokenAuthenticator
{
    public function __construct(
        private Hs256Verifier $verifier,
        private HttpIdentityProvider $identity,
    ) {}

    public function authenticate(Request $request): ?Account
    {
        $token = (string) $request->bearerToken();
        $claims = $token === '' ? null : $this->verifier->verify($token);
        $accountId = $claims['accountId'] ?? null;

        if (! is_string($accountId) || $accountId === '') {
            return null;
        }

        return $this->identity->resolve($accountId, $token);
    }
}
