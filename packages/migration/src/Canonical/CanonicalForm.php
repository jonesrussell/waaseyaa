<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Canonical;

/**
 * Deterministic canonical-form encoder for hashing source identities and
 * destination payloads.
 *
 * The canonical form is a JSON string with three stability rules:
 *
 * 1. Associative arrays (string-keyed) are sorted by key (ksort, SORT_STRING).
 * 2. Numeric-keyed arrays (PHP "list" semantics) preserve their original
 *    insertion order — they encode as JSON arrays, not objects.
 * 3. Encoded with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` and
 *    `JSON_THROW_ON_ERROR` so output is byte-stable across PHP versions.
 *
 * Used by:
 *
 * - {@see \Waaseyaa\Migration\SourceId::hash()} — the canonical form of the
 *   `(sourceType, keys)` pair, hashed with sha256, is the id-map lookup key
 *   (FR-026, FR-027).
 * - {@see \Waaseyaa\Migration\MigrationIdMap} change-detection — callers may
 *   reuse {@see encode()} to compute their own `source_record_hash` so the
 *   hashing algorithm stays in one place (FR-031, spec §8.3).
 *
 * **Lock the algorithm.** Any change to the encoding (key sort order, JSON
 * flags, scalar handling) invalidates every existing id-map row across every
 * downstream site. Per the spec / charter §5.4, future changes require a
 * charter amendment and a data migration of existing id-map rows.
 *
 * @api
 */
final class CanonicalForm
{
    /**
     * Encode an arbitrary nested array of scalars and arrays into the
     * canonical-form JSON string.
     *
     * - String-keyed (associative) arrays are sorted by key.
     * - Integer-keyed arrays preserve original order.
     * - Booleans encode as `true`/`false`; null encodes as `null`; integers
     *   stay numeric (no implicit string cast).
     * - Unicode is preserved verbatim — no NFC normalization in v1.
     *
     * @param array<array-key, mixed> $value Nested array of scalar | null | array values.
     *
     * @throws \InvalidArgumentException If any leaf value is not scalar, null, or array (objects, resources, closures rejected).
     * @throws \JsonException If the canonicalised structure cannot be JSON-encoded (should never happen with sane input — kept on the signature so callers can detect storage / hashing failures).
     *
     * @spec FR-027 — hash determinism
     */
    public static function encode(array $value): string
    {
        $canonical = self::canonicalise($value);

        return \json_encode(
            $canonical,
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Recursively canonicalise a value:
     *
     * - Scalars and null pass through unchanged.
     * - Lists (integer-keyed, sequential from 0) preserve order but recurse
     *   into elements.
     * - Associative arrays are key-sorted (SORT_STRING for stable bytes
     *   regardless of locale) and recurse into values.
     *
     * @param mixed $value
     *
     * @throws \InvalidArgumentException If $value is not scalar, null, or array.
     */
    private static function canonicalise(mixed $value): mixed
    {
        if ($value === null || \is_scalar($value)) {
            return $value;
        }
        if (!\is_array($value)) {
            throw new \InvalidArgumentException(\sprintf(
                'CanonicalForm::encode() only accepts arrays of scalar | null | array values; got %s.',
                \get_debug_type($value),
            ));
        }

        if (\array_is_list($value)) {
            $out = [];
            foreach ($value as $item) {
                $out[] = self::canonicalise($item);
            }

            return $out;
        }

        \ksort($value, \SORT_STRING);
        $out = [];
        foreach ($value as $name => $item) {
            $out[(string) $name] = self::canonicalise($item);
        }

        return $out;
    }
}
