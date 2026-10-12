<?php

declare(strict_types=1);

return [
    'platform_domain' => env('SHOPLINE_PLATFORM_DOMAIN', 'platform.com'),
    'guest_token_ttl' => (int) env('SHOPLINE_GUEST_TOKEN_TTL', 7200),
    'max_page_size' => 100,
    'product_image_disk' => env('SHOPLINE_PRODUCT_IMAGE_DISK', 'public'),
    'supported_business_types' => [
        'fashion', 'electronics', 'grocery', 'pharmacy', 'restaurant', 'jewelry',
        'books', 'beauty', 'home', 'sports', 'toys', 'other',
    ],
];
