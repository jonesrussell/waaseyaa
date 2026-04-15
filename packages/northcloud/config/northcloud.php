<?php

declare(strict_types=1);

/**
 * Default configuration for waaseyaa/northcloud.
 *
 * Apps override by publishing their own config/northcloud.php or setting env vars.
 */
return [
    // Base URL of the North Cloud API (no trailing slash).
    // NorthCloudServiceProvider falls back to the NORTHCLOUD_BASE_URL env var
    // when this is empty or unset; leave as the default in most cases.
    'base_url' => 'https://api.northcloud.one',

    // Bearer token for authenticated endpoints (crawl jobs, link-sources).
    // NorthCloudServiceProvider falls back to the NORTHCLOUD_API_TOKEN env var
    // when this is empty or unset. Leave empty for read-only usage.
    'api_token' => '',

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
