<?php

declare(strict_types=1);

namespace Waaseyaa\Migration;

use Waaseyaa\Migration\Canonical\CanonicalForm;

/**
 * Canonical identity of a row in an external source system.
 *
 * Composed of the source-format identifier ($sourceType, e.g. `wordpress_post`)
 * and the natural-key map ($keys) that disambiguates the row inside that source.
 *
 * The hash produced by {@see hash()} is the lookup key in `migration_id_map`
 * (`source_id_hash`, spec §8.1). It MUST be deterministic across re-runs
 * (FR-027); the hashing algorithm is locked to {@see CanonicalForm} +
 * sha256 and any change requires a charter amendment plus a data migration
 * of existing id-map rows.
 *
 * ### Type stability
 *
 * `SourceId` does NOT coerce key value types. `['id' => 42]` and
 * `['id' => '42']` produce different hashes because the canonical form
 * preserves integer-vs-string distinctions. Source plugins are responsible
 * for emitting type-stable key values (typically integer ids stay integers,
 * UUIDs / GUIDs stay strings).
 *
 * ### Unicode
 *
 * The canonical form preserves Unicode verbatim — no NFC normalization in
 * v1. If a source system emits the same logical key with different Unicode
 * compositions, the two values will hash to different lookup keys.
 *
 * @api
 *
 * @spec FR-026 — stable value object
 * @spec FR-027 — deterministic hashing
 */
final readonly class SourceId
{
    /**
     * @param string $sourceType Source-format identifier (e.g. `wordpress_post`). Must be a non-empty lowercase snake-case token matching `/^[a-z][a-z0-9_]*$/`.
     * @param array<array-key, mixed> $keys Natural-key map for the source row. Must be non-empty; keys must be non-empty strings and values must be scalar or null (no nested arrays — keying must stay flat for hash stability). Validated at runtime against arbitrary input (e.g. data deserialised from JSON).
     *
     * @throws \InvalidArgumentException If $sourceType is empty/malformed, $keys is empty, or any key/value is non-string/non-scalar-or-null.
     */
    public function __construct(
        public string $sourceType,
        public array $keys,
    ) {
        if ($sourceType === '') {
            throw new \InvalidArgumentException('SourceId::$sourceType must be a non-empty string.');
        }
        if (\preg_match('/^[a-z][a-z0-9_]*$/', $sourceType) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'SourceId::$sourceType must match /^[a-z][a-z0-9_]*$/, got %s.',
                \var_export($sourceType, true),
            ));
        }
        if ($keys === []) {
            throw new \InvalidArgumentException('SourceId::$keys must not be empty.');
        }
        foreach ($keys as $name => $value) {
            if (!\is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SourceId::$keys must be a non-empty string-keyed map.');
            }
            if ($value !== null && !\is_scalar($value)) {
                throw new \InvalidArgumentException(\sprintf(
                    'SourceId::$keys[%s] must be scalar or null, got %s.',
                    $name,
                    \get_debug_type($value),
                ));
            }
        }
    }

    /**
     * Deterministic sha256 hash used as the id-map lookup key
     * (`migration_id_map.source_id_hash`).
     *
     * The hash input is the canonical form of `['source_type' => $this->sourceType, 'keys' => $this->keys]`
     * — see {@see CanonicalForm::encode()} for the exact encoding contract.
     *
     * @return non-empty-string 64-character lowercase hex digest.
     *
     * @spec FR-027 — hash determinism
     */
    public function hash(): string
    {
        return \hash('sha256', CanonicalForm::encode([
            'source_type' => $this->sourceType,
            'keys' => $this->keys,
        ]));
    }

    /**
     * Convenience equality predicate — true iff both SourceIds produce the
     * same {@see hash()} value. Equivalent to comparing the canonical hashes
     * directly; provided so callers do not have to round-trip through string
     * comparison every time.
     */
    public function equals(self $other): bool
    {
        return $this->hash() === $other->hash();
    }
}
