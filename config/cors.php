<?php

declare(strict_types=1);

return [
    'paths' => ['api/*', 'oauth/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Request-ID'],
    'max_age' => 0,
    'supports_credentials' => false,
];
