<?php

declare(strict_types=1);

return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),
    'prefix' => env('SCOUT_PREFIX', ''),
    'queue' => env('SCOUT_QUEUE', true),
    'after_commit' => true,
    'chunk' => ['searchable' => 500, 'unsearchable' => 500],
    'soft_delete' => true,
    'identify' => env('SCOUT_IDENTIFY', false),
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            'products' => [
                'filterableAttributes' => ['business_id', 'is_active', 'category_id', 'brand_id'],
                'sortableAttributes' => ['price', 'published_at', 'created_at'],
            ],
        ],
    ],
    'algolia' => ['id' => env('ALGOLIA_APP_ID', ''), 'secret' => env('ALGOLIA_SECRET', ''), 'index-settings' => []],
];
