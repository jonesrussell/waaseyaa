<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

/**
 * Typed wrapper around the parsed `$_GET` slice that a controller passes
 * to {@see ListingResolver::resolve()}.
 *
 * The resolver reads exposed-filter values for filters whose
 * {@see FilterDefinition::$exposedParam} matches a key here; absent keys
 * fall through to the filter's declared `$value`.
 *
 * Construction is fully validated by the future `ExposedFilterParser`
 * (M-007 WP09 / FR-042..FR-045); WP05 only consumes already-parsed values.
 * The shape committed here is the resolver-visible surface — future
 * versions remain additively compatible.
 *
 * @api
 */
final readonly class ExposedFilterValues
{
    /**
     * @param array<non-empty-string, mixed> $values URL-decoded, type-coerced values keyed by exposed-param name.
     */
    public function __construct(
        private array $values = [],
    ) {}

    /**
     * Return the coerced value for `$param`, or `null` if the key is absent.
     */
    public function get(string $param): mixed
    {
        return $this->values[$param] ?? null;
    }

    /**
     * Whether `$param` is present in the values map.
     */
    public function has(string $param): bool
    {
        return array_key_exists($param, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Deterministic 16-hex-char digest of the values map (FR-037).
     *
     * Canonical JSON sorts object keys lexicographically so two PHP workers
     * with the same value-map produce the same digest.
     */
    public function cacheKeyHash(): string
    {
        $canonical = self::canonicalize($this->values);
        $json = json_encode(
            $canonical,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return substr(hash('sha256', $json), 0, 16);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }
        ksort($value);
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = self::canonicalize($v);
        }

        return $out;
    }
}
