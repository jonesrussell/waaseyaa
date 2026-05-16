<?php

declare(strict_types=1);

namespace Waaseyaa\Cache;

use Waaseyaa\Foundation\Http\RequestContext;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * Resolves a canonical context name to a deterministic short string for the
 * current {@see RequestContext}.
 *
 * **Purpose.** The listing pipeline (`ListingResolver` / `ListingCacheKeyBuilder`,
 * WP05 / WP06) hashes the resolved strings into cache keys (FR-037). Two PHP
 * workers handling the same request state MUST produce the same string, so
 * resolution is intentionally pure and free of process-local randomness.
 *
 * **Resolution table** (`contracts/context-architecture.md`):
 *
 * | Context name           | Source                                  | Format                                       |
 * |------------------------|-----------------------------------------|----------------------------------------------|
 * | `user.roles`           | `RequestContext::roles()`               | sorted ascending, joined with `,`            |
 * | `user.id`              | `RequestContext::accountId()`           | integer rendered as string; `''` if anon     |
 * | `language.content`     | `RequestContext::activeLangcode()`      | langcode; `''` if unset                      |
 * | `language.interface`   | `RequestContext::interfaceLangcode()`   | langcode; `''` if unset                      |
 * | `url.query.<param>`    | `$req->getQueryParams()[<param>] ?? ''` | URL-decoded value (single decode); `''` if absent |
 * | (unregistered name)    | (none)                                  | `''` + WARNING log; caller bypasses cache    |
 *
 * **Unknown-name behaviour.** Per R-11 / FR-035, an unknown context name MUST
 * NOT throw: the resolver logs at `WARNING` level (including the unknown name
 * in the log context) and returns the empty string. Callers (e.g.
 * `Waaseyaa\Listing\ListingResolver`) treat the empty string from an unknown
 * context as a signal to skip the cache write — the listing still resolves,
 * but the result is not stored, and the next call incurs another miss +
 * warning. Throwing here would take down a listing on an extension-package
 * config bug; warn + degrade keeps the feature working while making the
 * misconfiguration visible.
 *
 * **Determinism guarantees:**
 *
 * - `user.roles` sorts the role list **inside the resolver** (`sort($roles)`),
 *   so a `RequestContext` that returns roles in different orders across
 *   workers still produces the same string.
 * - All other resolutions are direct accessor reads (no time, no random, no
 *   filesystem).
 *
 * @api
 *
 * @see ContextRegistry
 * @see ContextNames
 * @see RequestContext
 */
final class ContextResolver
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ContextRegistry $registry,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Resolve a canonical context name to its deterministic string.
     *
     * @param  string $context canonical context name (must be in the registry)
     * @return string deterministic canonical value; `''` for unknown/missing
     */
    public function resolve(string $context, RequestContext $request): string
    {
        if (!$this->registry->has($context)) {
            $this->logger->warning(
                'Unknown context name in resolver',
                ['context' => $context],
            );

            return '';
        }

        return match (true) {
            $context === ContextNames::USER_ROLES => $this->resolveUserRoles($request),
            $context === ContextNames::USER_ID => $this->resolveUserId($request),
            $context === ContextNames::LANGUAGE_CONTENT => $request->activeLangcode() ?? '',
            $context === ContextNames::LANGUAGE_INTERFACE => $request->interfaceLangcode() ?? '',
            str_starts_with($context, ContextNames::URL_QUERY_PREFIX) => $this->resolveUrlQuery($context, $request),
            default => '',
        };
    }

    /**
     * Sorted-ascending, comma-joined role IDs. Empty string for no roles.
     */
    private function resolveUserRoles(RequestContext $request): string
    {
        $roles = $request->roles();

        if ($roles === []) {
            return '';
        }

        sort($roles, SORT_STRING);

        return implode(',', $roles);
    }

    /**
     * Integer account ID as string; empty for anonymous.
     */
    private function resolveUserId(RequestContext $request): string
    {
        $id = $request->accountId();

        return $id === null ? '' : (string) $id;
    }

    /**
     * URL-query param lookup. Extract the param name after the `url.query.`
     * prefix and return its value (URL-decoded at request-construction time).
     */
    private function resolveUrlQuery(string $context, RequestContext $request): string
    {
        $param = substr($context, strlen(ContextNames::URL_QUERY_PREFIX));

        if ($param === '') {
            return '';
        }

        $params = $request->getQueryParams();

        return $params[$param] ?? '';
    }
}
