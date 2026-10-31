<?php

namespace Tests\Feature;

use App\Integrations\Http\Hs256Verifier;
use Tests\Support\LiveIntegration;
use Tests\TestCase;

class Hs256VerifierTest extends TestCase
{
    use LiveIntegration;

    private function verifier(): Hs256Verifier
    {
        return new Hs256Verifier($this->jwtSecret);
    }

    public function test_valid_token_returns_claims(): void
    {
        $claims = $this->verifier()->verify($this->jwt(['accountId' => 'acc-x']));

        $this->assertSame('acc-x', $claims['accountId']);
        $this->assertSame('PM', $claims['roleCode']);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $this->assertNull($this->verifier()->verify($this->jwt(secret: 'another-secret-another-secret-12345')));
    }

    public function test_tampered_payload_is_rejected(): void
    {
        [$header, , $signature] = explode('.', $this->jwt(['roleCode' => 'TW']));
        $forged = rtrim(strtr(base64_encode(json_encode(['accountId' => 'acc-pm', 'roleCode' => 'PM', 'exp' => time() + 3600])), '+/', '-_'), '=');

        $this->assertNull($this->verifier()->verify("{$header}.{$forged}.{$signature}"));
    }

    public function test_alg_none_is_rejected(): void
    {
        $b64 = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $token = $b64(['alg' => 'none', 'typ' => 'JWT']).'.'.$b64(['accountId' => 'acc-pm', 'exp' => time() + 3600]).'.';

        $this->assertNull($this->verifier()->verify($token));
    }

    public function test_other_algorithms_are_rejected_even_with_a_matching_hmac(): void
    {
        $this->assertNull($this->verifier()->verify($this->jwt(header: ['alg' => 'HS512', 'typ' => 'JWT'])));
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->assertNull($this->verifier()->verify($this->jwt(['exp' => time() - 1])));
    }

    public function test_token_without_exp_is_rejected(): void
    {
        $b64 = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $input = $b64(['alg' => 'HS256']).'.'.$b64(['accountId' => 'acc-pm']);
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $input, $this->jwtSecret, true)), '+/', '-_'), '=');

        $this->assertNull($this->verifier()->verify("{$input}.{$sig}"));
    }

    public function test_not_yet_valid_token_is_rejected(): void
    {
        $this->assertNull($this->verifier()->verify($this->jwt(['nbf' => time() + 600])));
    }

    public function test_malformed_tokens_are_rejected(): void
    {
        foreach (['', 'abc', 'a.b', 'a.b.c.d', '!!!.@@@.###'] as $token) {
            $this->assertNull($this->verifier()->verify($token), "token: {$token}");
        }
    }

    public function test_empty_secret_accepts_nothing(): void
    {
        $this->assertNull((new Hs256Verifier(''))->verify($this->jwt(secret: '')));
    }
}
