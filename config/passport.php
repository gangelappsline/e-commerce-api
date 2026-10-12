<?php

declare(strict_types=1);

return [
    'guard' => 'api',
    'middleware' => [],
    'private_key' => env('PASSPORT_PRIVATE_KEY'),
    'public_key' => env('PASSPORT_PUBLIC_KEY'),
    'connection' => env('PASSPORT_CONNECTION'),
];
