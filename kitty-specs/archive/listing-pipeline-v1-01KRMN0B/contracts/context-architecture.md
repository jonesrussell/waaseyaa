# Contract: ContextRegistry + ContextResolver + canonical context names

**Stability scope:** Charter §5.Y
**FRs covered:** FR-035, FR-036
**Owned by:** WP04

## ContextRegistry

```php
namespace Waaseyaa\Cache;

final class ContextRegistry
{
    public function __construct()
    {
        // Seeded with all canonical names listed below.
    }

    /**
     * @param non-empty-string $name  Must match [a-z][a-z0-9_.]*
     * @throws \InvalidArgumentException on invalid format
     */
    public function register(string $name): void;

    public function has(string $name): bool;

    /** @return list<non-empty-string> */
    public function all(): array;
}
```

**Seeded canonical names (registered automatically):**
- `user.roles`
- `user.id`
- `language.content`
- `language.interface`
- `url.query.*` (the prefix; specific `url.query.<param>` names are recognised by prefix match)

**Extension packages register additional names via `ContextRegistry::register()` from their `ServiceProvider::boot()`.**

**Format:** `^[a-z][a-z0-9_.]*$` — lowercase, dots and underscores after first char. (Distinct from tag regex which also allows `:` and `-`.)

## ContextResolver

```php
namespace Waaseyaa\Cache;

final class ContextResolver
{
    public function __construct(
        private readonly ContextRegistry $registry,
        private readonly LoggerInterface $logger = new NullLogger(),
    );

    /**
     * @return string  Deterministic canonical string for the current request. Empty string on unknown/missing data.
     */
    public function resolve(string $context, RequestContext $request): string;
}
```

**Stability commitment:** `resolve()` signature is stable. Return is always `string` — `null` is never returned; unknown contexts return empty string and log a warning.

## Resolution rules

| Context name | Source | Canonical string format | Empty/missing case |
|---|---|---|---|
| `user.roles` | `RequestContext::roles()` | sorted-ascending role IDs joined with `,` | `''` (empty string) |
| `user.id` | `RequestContext::accountId()` | integer ID rendered as string | `''` (anonymous → empty) |
| `url.query.<param>` | `$request->getQueryParams()[<param>] ?? ''` | URL-decoded value, single decode | `''` |
| `language.content` | `RequestContext::activeLangcode()` | langcode string (BCP-47 shape) | `''` |
| `language.interface` | `RequestContext::interfaceLangcode()` | langcode string | `''` |
| any unregistered name | (no source) | `''` + log warning + caller bypasses cache | `''` |

**Determinism:** Same `RequestContext` state → same return string across PHP workers. Critical for cache-key parity (FR-037).

**Sort-stability:** `user.roles` MUST sort ascending; the same set of roles in different `RequestContext::roles()` orders MUST produce the same string. Implementation: `sort($roles); return implode(',', $roles)`.

## ContextNames (string constants)

```php
namespace Waaseyaa\Cache;

final class ContextNames
{
    public const USER_ROLES         = 'user.roles';
    public const USER_ID            = 'user.id';
    public const LANGUAGE_CONTENT   = 'language.content';
    public const LANGUAGE_INTERFACE = 'language.interface';
    public const URL_QUERY_PREFIX   = 'url.query.';   // concatenate the param name
}
```

**Stability commitment:** All constants are stable. The literal string values are also stable (used in cache-key emission). Future additions are additive.

**Listing pipeline usage** (FR-024 / FR-048):
- `language.content` added to `cacheContexts()` for any listing of a translatable entity type (regardless of declared langcode filter).
- `user.roles` added when `$accessOps !== ['view']` AND the bound policy consults role-based decisions.
- `user.id` added when the bound policy consults the specific user (rare; opt-in).
- `url.query.page` added when `$pageSize !== null`.
- `url.query.<param>` added for each `exposedParam` declared in the listing's filters.

## Cache-bypass behaviour for unknown contexts

Per R-11 / FR-035: If `ListingResult::cacheContexts()` includes a name NOT in the registry, the resolver:
1. Logs `LogLevel::WARNING` with the unknown context name + listing id
2. Resolves anyway (doesn't throw)
3. Skips the cache write — the result is not stored, the next resolve incurs another miss + warning

**Rationale:** Throwing would take down the listing on an extension-package config bug; degrading to no-cache surfaces the warning + keeps the feature working. Throws are reserved for definition-time validation failures.

## Test surface

`ContextResolverTest` (concrete; no abstract contract needed — single behaviour):
- `resolveUserRolesReturnsSortedJoined`
- `resolveUserRolesAnonymousReturnsEmpty`
- `resolveUserIdAnonymousReturnsEmpty`
- `resolveUrlQueryParamReturnsDecoded`
- `resolveUrlQueryParamMissingReturnsEmpty`
- `resolveLanguageContentReturnsActiveLangcode`
- `resolveUnknownContextLogsWarningReturnsEmpty`
- `resolveDeterministicAcrossInvocations` (same RequestContext state → same output)

`ContextRegistryTest`:
- `registerAddsNewName`
- `registerRejectsInvalidFormat`
- `hasReturnsTrueForCanonical` (seeded names)
- `hasReturnsFalseForUnknown`
