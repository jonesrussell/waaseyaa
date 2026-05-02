<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Canonical-JSON encoder for the SchemaDiff algebra.
 *
 * The rules are ratified in `docs/specs/schema-evolution-v2.md` §15 Q2
 * (2026-05-02). They are load-bearing for the SHA-256 checksum, so any
 * deviation here changes every recorded `checksum` and `diff_hash` value
 * in the migrations ledger. Treat this class as part of a stability
 * contract — golden hashes in the test suite lock the rules.
 *
 * **Rules:**
 *
 * 1. **UTF-8 bytes.** Output is a UTF-8 byte string. Multibyte characters
 *    are emitted directly (never `\uXXXX`-escaped) so the byte
 *    representation is stable across platforms and locales.
 * 2. **Sorted object keys.** Every associative array is recursively
 *    sorted by key using `ksort($a, SORT_STRING)` (lexicographic byte
 *    order, not locale-aware). The result is a JSON object whose key
 *    order is the same on every PHP version on every platform.
 * 3. **Lists preserve order.** A list (zero-indexed, contiguous integer
 *    keys) is emitted as a JSON array in input order. Order is part of
 *    identity — `[a, b]` and `[b, a]` hash differently. The encoder uses
 *    `array_is_list()` to make the distinction.
 * 4. **Integers stay integers.** PHP `int` is emitted as a bare integer
 *    literal (no `.0`). PHP `float` is emitted as a JSON number with the
 *    shortest round-trip representation. The encoder MUST NOT use
 *    `JSON_PRESERVE_ZERO_FRACTION`, which would force `1` → `1.0` and
 *    diverge from the canonical form.
 * 5. **`null` is preserved.** `null` is emitted as the JSON literal
 *    `null`. The op's `toCanonical()` is responsible for only emitting
 *    `null` where the field is declared nullable; this encoder does not
 *    omit null-valued keys.
 * 6. **No whitespace.** No spaces, no newlines, no `JSON_PRETTY_PRINT`.
 * 7. **NaN / Inf rejected.** `JSON_THROW_ON_ERROR` makes `json_encode`
 *    throw on `NAN`, `INF`, `-INF`, malformed UTF-8, and any other
 *    encoding failure. Callers must convert before reaching here.
 * 8. **No backslashes / forward-slashes escaped beyond JSON minimum.**
 *    `JSON_UNESCAPED_SLASHES` keeps `/` unescaped. `JSON_UNESCAPED_UNICODE`
 *    keeps non-ASCII verbatim per rule 1.
 *
 * @internal Foundation-layer infrastructure for {@see CompositeDiff}.
 */
final class CanonicalJson
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR;

    /**
     * Encode a value to canonical JSON.
     *
     * @param array<array-key, mixed>|scalar|null $value
     *
     * @throws \JsonException on encoding failure (NaN, Inf, malformed UTF-8).
     */
    public static function encode(mixed $value): string
    {
        return json_encode(self::canonicalize($value), self::JSON_FLAGS);
    }

    /**
     * Recursively sort associative arrays by key while preserving list order.
     *
     * Public so callers can normalize inputs before comparing them by
     * structure (rare; most callers want {@see encode()}).
     */
    public static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        $sorted = $value;
        ksort($sorted, SORT_STRING);

        return array_map(self::canonicalize(...), $sorted);
    }
}
