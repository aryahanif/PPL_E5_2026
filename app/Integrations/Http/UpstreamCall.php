<?php

namespace App\Integrations\Http;

use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One GET to another group's service. Network failures and 5xx become 503
 * UPSTREAM_UNAVAILABLE, never "not found": a down F-1/D-1 must not look like a missing record.
 * 2xx and 4xx are returned for the caller to interpret.
 */
final class UpstreamCall
{
    public static function get(string $service, string $url, array $query = [], ?string $token = null, int $timeout = 3): Response
    {
        try {
            $request = Http::acceptJson()->timeout($timeout)->connectTimeout(min($timeout, 2));
            $response = ($token ? $request->withToken($token) : $request)->get($url, $query);
        } catch (ConnectionException $e) {
            Log::warning("{$service} unreachable: {$url}", ['error' => $e->getMessage()]);

            throw new ApiException(503, 'UPSTREAM_UNAVAILABLE', "Layanan {$service} tidak dapat dihubungi");
        }

        if ($response->serverError()) {
            Log::warning("{$service} answered {$response->status()}: {$url}");

            throw new ApiException(503, 'UPSTREAM_UNAVAILABLE', "Layanan {$service} sedang bermasalah");
        }

        return $response;
    }
}
