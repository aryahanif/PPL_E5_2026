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
