<?php

return [
    'credentials_path' => storage_path('app/google-credentials.json'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/auth/google/callback'),
    'ads_developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
    'scopes' => [
        'https://www.googleapis.com/auth/adwords',
        'https://www.googleapis.com/auth/analytics.readonly',
        'https://www.googleapis.com/auth/webmasters.readonly',
    ],
];
