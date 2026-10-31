<?php

namespace App\Integrations\Http;

use Illuminate\Support\Carbon;

/**
 * Verifies the HS256 JWTs issued by F-1 with the secret shared between the groups
 * (F-1 README: "JWT_SECRET harus sama persis di setiap layanan konsumen").
 * Strict on purpose: only HS256, signature compared in constant time, `exp` is mandatory.
 */
final class Hs256Verifier
{
    public function __construct(private string $secret, private int $leewaySeconds = 0) {}

    /** @return array<string, mixed>|null The claims, or null if the token is not acceptable. */
    public function verify(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3 || $this->secret === '') {
            return null;
        }

        [$header64, $payload64, $signature64] = $parts;

        $header = $this->json($header64);
        $signature = $this->base64($signature64);

        if (($header['alg'] ?? null) !== 'HS256' || $signature === null) {
            return null;
        }

        $expected = hash_hmac('sha256', "{$header64}.{$payload64}", $this->secret, true);

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $claims = $this->json($payload64);
        $now = Carbon::now()->timestamp;

        if ($claims === null || ! isset($claims['exp']) || ! is_numeric($claims['exp'])
            || $now > $claims['exp'] + $this->leewaySeconds) {
            return null;
        }

        if (isset($claims['nbf']) && (! is_numeric($claims['nbf']) || $now < $claims['nbf'] - $this->leewaySeconds)) {
            return null;
        }

        return $claims;
    }

    private function json(string $segment): ?array
    {
        $raw = $this->base64($segment);
        $data = $raw === null ? null : json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    private function base64(string $segment): ?string
    {
        $decoded = base64_decode(strtr($segment, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
