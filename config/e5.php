<?php

return [

    /*
    | How E-5 talks to other groups' services (PRD §9).
    | mock: read fixtures from database/mocks (default until the real APIs exist)
    | live: call the real service. Implemented for F-1 and D-1 only; E-3 and E-4 stay mock.
    |
    | INTEGRATION_MODE is the default for every integration; F1_MODE, D1_MODE, E3_MODE and
    | E4_MODE override it one by one, e.g. INTEGRATION_MODE=mock + F1_MODE=live + D1_MODE=live.
    */
    'integration_mode' => env('INTEGRATION_MODE', 'mock'),

    'modes' => [
        'f1' => env('F1_MODE', env('INTEGRATION_MODE', 'mock')),
        'd1' => env('D1_MODE', env('INTEGRATION_MODE', 'mock')),
        'e3' => env('E3_MODE', env('INTEGRATION_MODE', 'mock')),
        'e4' => env('E4_MODE', env('INTEGRATION_MODE', 'mock')),
    ],

    /*
    | Settings used only when an integration runs in `live` mode. See doc/INTEGRASI_LIVE.md.
    */
    'live' => [
        // Seconds to wait for another group's service before answering 503 UPSTREAM_UNAVAILABLE.
        'timeout' => (int) env('UPSTREAM_TIMEOUT', 3),

        'f1' => [
            // Contract: https://api.anaheim-nimbus.internal/api/v1. F-1's demo server has NO /api/v1.
            'base_url' => env('F1_BASE_URL'),
            // Must be identical to F-1's JWT_SECRET; tokens are verified locally with it.
            'jwt_secret' => env('F1_JWT_SECRET'),
            // Optional token for lookups made without a user request. F-1 has no service token yet.
            'service_token' => env('F1_SERVICE_TOKEN'),
            // F-1 roleCode => E-5 role. F-1 only has CEO, COO, CTO, ADMIN, AM, PM, EMPLOYEE,
            // SPECIALIST: there is no TL or TW yet, so only PM works until F-1 adds them or the
            // class decides a mapping (e.g. F1_ROLE_MAP="PM:PM,SPECIALIST:TL,EMPLOYEE:TW").
            'role_map' => (function () {
                $pairs = [];

                foreach (array_filter(explode(',', (string) env('F1_ROLE_MAP', 'PM:PM,TL:TL,TW:TW'))) as $pair) {
                    [$from, $to] = array_pad(array_map('trim', explode(':', $pair, 2)), 2, '');

                    if ($from !== '' && $to !== '') {
                        $pairs[$from] = $to;
                    }
                }

                return $pairs;
            })(),
        ],

        'd1' => [
            // e.g. http://localhost:3000/api/v1
            'base_url' => env('D1_BASE_URL'),
            // D-1 has no authentication yet; sent as a Bearer token if set.
            'token' => env('D1_TOKEN'),
            // D-1 project statuses that count as an active project for E-5.
            'active_statuses' => array_values(array_filter(array_map('trim', explode(',', (string) env('D1_ACTIVE_STATUSES', 'REGISTERED'))))),
        ],

        // Interim source of project members (nobody publishes them yet): {"<D-1 projectId>": ["<F-1 accountId>"]}.
        'project_members_file' => env('E5_PROJECT_MEMBERS_FILE', storage_path('app/project_members.json')),
    ],

    // Data is stored in UTC; pages show and "overdue" is calculated in this timezone.
    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Jakarta'),

    /*
    | BL-12 safe/late indicator. A project is DELAYED when actual progress is lower than
    | target progress by more than `tolerance` percentage points. Q-10 (threshold) is
    | still open with the partner, so it is configuration, not code.
    */
    'indicator' => [
        'tolerance' => (float) env('INDICATOR_TOLERANCE', 0),
    ],

    'mocks' => [
        'accounts' => database_path('mocks/f1_accounts.json'),
        'projects' => database_path('mocks/d1_projects.json'),
        'baselines' => database_path('mocks/e3_baselines.json'),
        'tasks' => database_path('mocks/e4_tasks.json'),
    ],

];
