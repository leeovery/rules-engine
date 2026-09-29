<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Matches are cached by version and facts. Versions never change, so a
    | cached result never goes stale. Set the TTL to null to resolve without
    | the cache unless a lookup asks for it with cache().
    |
    */
    'cache' => [
        'ttl' => 3600, // seconds
        'prefix' => 'rules-engine',
    ],
];
