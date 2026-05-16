<?php

declare(strict_types=1);

namespace Waaseyaa\Cache;

/**
 * Whitelist of canonical cache-context names.
 *
 * The listing pipeline consults this registry before {@see ContextResolver}
 * resolves a context. Unknown names degrade gracefully: the resolver logs a
 * warning and returns an empty string (R-11 / FR-035), which the caller treats
 * as a signal to bypass cache writes for that resolution.
 *
 * Seeded canonical names (from {@see ContextNames}):
 * - `user.roles`
 * - `user.id`
 * - `language.content`
 * - `language.interface`
 * - `url.query.*` — registered as the literal sentinel `'url.query.*'`. Any
 *   call to {@see self::has()} with a string starting with `url.query.` is
 *   treated as canonical (prefix match) so callers do not need to register
 *   every query param individually.
 *
 * Extension packages register additional names from their service provider's
 * `boot()` via {@see self::register()}. Format is `^[a-z][a-z0-9_.]*$` —
 * lowercase, dots and underscores after the first character. Uppercase,
 * leading digit, hyphens, colons, or other special characters throw
 * {@see \InvalidArgumentException}. Registration is idempotent.
 *
 * @api
 *
 * @see ContextResolver
 * @see ContextNames
 */
final class ContextRegistry
{
    /**
     * Canonical name regex (matches `contracts/context-architecture.md`).
     */
    private const string FORMAT_REGEX = '/^[a-z][a-z0-9_.]*$/';

    /**
     * Literal sentinel registered for the `url.query.<param>` prefix family.
     */
    private const string URL_QUERY_SENTINEL = 'url.query.*';

    /**
     * @var array<non-empty-string, true>
     */
    private array $known = [];

    public function __construct()
    {
        // Seed canonical names from ContextNames. Direct-match entries.
        $this->known[ContextNames::USER_ROLES] = true;
        $this->known[ContextNames::USER_ID] = true;
        $this->known[ContextNames::LANGUAGE_CONTENT] = true;
        $this->known[ContextNames::LANGUAGE_INTERFACE] = true;

        // Sentinel for prefix-match family. {@see self::has()} also accepts
        // any string starting with ContextNames::URL_QUERY_PREFIX.
        $this->known[self::URL_QUERY_SENTINEL] = true;
    }

    /**
     * Register an additional canonical context name.
     *
     * Idempotent: re-registering an existing name is a no-op.
     *
     * @param non-empty-string $name must match `^[a-z][a-z0-9_.]*$`
     *
     * @throws \InvalidArgumentException on invalid format
     */
    public function register(string $name): void
    {
        if (preg_match(self::FORMAT_REGEX, $name) !== 1) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid context name "%s": must match %s.',
                    $name,
                    self::FORMAT_REGEX,
                ),
            );
        }

        $this->known[$name] = true;
    }

    /**
     * True if `$name` is a registered canonical context.
     *
     * Returns true for:
     * - direct matches (seeded names + names added via {@see self::register()});
     * - any string starting with `url.query.` (prefix match against the
     *   `url.query.*` sentinel family).
     */
    public function has(string $name): bool
    {
        if (isset($this->known[$name])) {
            return true;
        }

        // Prefix match for the url.query.<param> family. The sentinel itself
        // (url.query.*) is already in $known and matched above; this branch
        // covers concrete param names like url.query.page or url.query.category.
        if (str_starts_with($name, ContextNames::URL_QUERY_PREFIX)) {
            return true;
        }

        return false;
    }

    /**
     * Sorted list of registered canonical names.
     *
     * The list includes the `url.query.*` sentinel literally (not the concrete
     * `url.query.<param>` derivations, which are infinite by design).
     *
     * @return list<non-empty-string>
     */
    public function all(): array
    {
        /** @var list<non-empty-string> $names */
        $names = array_keys($this->known);
        sort($names, SORT_STRING);

        return $names;
    }
}
