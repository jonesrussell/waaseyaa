<?php

declare(strict_types=1);

/**
 * Default configuration for waaseyaa/northcloud.
 *
 * Apps override by publishing their own config/northcloud.php or setting env vars.
 */
return [
    // Base URL of the North Cloud API (no trailing slash).
    'base_url' => getenv('NORTHCLOUD_BASE_URL') ?: 'https://api.northcloud.one',

    // Bearer token for authenticated endpoints (crawl jobs, link-sources).
    // Leave empty for read-only usage.
    'api_token' => getenv('NORTHCLOUD_API_TOKEN') ?: '',

    // HTTP request timeout in seconds.
    'timeout' => 5,

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],

    'sync' => [
        'default_limit' => 20,
        'topics' => ['indigenous'],
        'min_quality' => 60,
        // Status file written by NcSyncCommand and NcSyncWorker. Absolute path.
        'status_path' => null,
    ],

    'search' => [
        // In-memory cache TTL for search responses (seconds). 0 disables.
        'cache_ttl' => 300,
    ],
];
