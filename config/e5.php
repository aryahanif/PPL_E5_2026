<?php

return [

    /*
    | How E-5 talks to other groups' services (PRD §9).
    | mock: read fixtures from database/mocks (default until the real APIs exist)
    | live: call the real F-1 / D-1 APIs (not implemented yet)
    */
    'integration_mode' => env('INTEGRATION_MODE', 'mock'),

    // Data is stored in UTC; pages show and "overdue" is calculated in this timezone.
    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Jakarta'),

    'mocks' => [
        'accounts' => database_path('mocks/f1_accounts.json'),
        'projects' => database_path('mocks/d1_projects.json'),
    ],

];
