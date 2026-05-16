<?php

declare(strict_types=1);

namespace Waaseyaa\Cache;

/**
 * Canonical context-name constants for the cache-context architecture.
 *
 * These identifiers are the **stable surface** consumed by:
 *
 * - {@see ContextRegistry} — seeds its whitelist from these constants.
 * - {@see ContextResolver} — dispatches resolution on these literal strings.
 * - `Waaseyaa\Listing\ListingResult::cacheContexts()` (FR-024, FR-048) — declares
 *   which contexts a listing's cache key depends on, using these literal strings.
 *
 * The string **values** are themselves stable (they appear inside emitted cache
 * keys via {@see ContextResolver::resolve()}); a non-additive change would break
 * cache-key parity across PHP workers.
 *
 * `URL_QUERY_PREFIX` is a prefix sentinel — concatenate the query-param name
 * (e.g. `ContextNames::URL_QUERY_PREFIX . 'page'` -> `'url.query.page'`). The
 * registry recognises any string with this prefix as canonical via prefix-match
 * (see {@see ContextRegistry::has()}).
 *
 * @api
 *
 * @see ContextRegistry
 * @see ContextResolver
 */
final class ContextNames
{
    public const string USER_ROLES = 'user.roles';

    public const string USER_ID = 'user.id';

    public const string LANGUAGE_CONTENT = 'language.content';

    public const string LANGUAGE_INTERFACE = 'language.interface';

    /**
     * Prefix for URL-query context names. Concatenate the param: `url.query.page`.
     */
    public const string URL_QUERY_PREFIX = 'url.query.';

    /**
     * Static-only class. Never instantiated.
     */
    private function __construct() {}
}
