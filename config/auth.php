<?php

/*
| Accounts belong to F-1, so E-5 has no users table and no passwords.
| Both guards resolve an App\Data\Account through the IdentityProvider
| (see AppServiceProvider::boot).
*/

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => null,
    ],

    'guards' => [
        // Blade pages: account picked on /dev-login, stored in the session.
        'web' => [
            'driver' => 'mock-session',
        ],

        // JSON API: Authorization: Bearer mock-<accountId>
        'api' => [
            'driver' => 'mock-token',
        ],
    ],

    'providers' => [],

    'passwords' => [],

    'password_timeout' => 10800,

];
