<?php

return [
    'paths' => ['api/v1/*'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MOBILE_CORS_ALLOWED_ORIGINS', 'https://localhost,capacitor://localhost,http://localhost:5173')),
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
