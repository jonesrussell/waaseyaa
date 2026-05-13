<?php

declare(strict_types=1);

namespace Waaseyaa\Migration;

/**
 * Canonical identity of a row in an external source system.
 *
 * Composed of the source-format identifier ($sourceType, e.g. `wordpress_post`)
 * and the natural-key map ($keys) that disambiguates the row inside that source.
 *
 * **WP01 stub.** Only the shape is stable surface; the deterministic hashing
 * algorithm lands in WP04. {@see hash()} throws {@see \LogicException} until then
 * so accidental early callers fail loudly rather than silently producing the wrong
 * id-map keys.
 *
 * @api
 */
final readonly class SourceId
{
    /**
     * @param string $sourceType Source-format identifier (e.g. `wordpress_post`). Must be a non-empty lowercase snake-case token.
     * @param array<array-key, mixed> $keys Natural-key map for the source row. Must be non-empty; keys must be non-empty strings and values must be scalar. Validated at runtime against arbitrary input (e.g. data deserialised from JSON).
     *
     * @throws \InvalidArgumentException If $sourceType is empty/malformed, $keys is empty, or any key/value is non-string/non-scalar.
     */
    public function __construct(
        public string $sourceType,
        public array $keys,
    ) {
        if ($sourceType === '') {
            throw new \InvalidArgumentException('SourceId::$sourceType must be a non-empty string.');
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $sourceType) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'SourceId::$sourceType must match /^[a-z][a-z0-9_]*$/, got %s.',
                var_export($sourceType, true),
            ));
        }
        if ($keys === []) {
            throw new \InvalidArgumentException('SourceId::$keys must not be empty.');
        }
        /**
         * @var array-key $name
         * @var mixed $value
         */
        foreach ($keys as $name => $value) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SourceId::$keys must be a non-empty string-keyed map.');
            }
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException(\sprintf(
                    'SourceId::$keys[%s] must be scalar, got %s.',
                    $name,
                    get_debug_type($value),
                ));
            }
        }
    }

    /**
     * Deterministic hash used as the id-map lookup key.
     *
     * **Not yet implemented — landing in WP04.** Calling this method throws so
     * that callers wired before WP04 fail loudly during testing rather than
     * silently producing collisions or invalid id-map rows.
     *
     * @throws \LogicException Always — implemented in WP04.
     */
    public function hash(): string
    {
        throw new \LogicException('SourceId::hash() not yet implemented — landing in WP04.');
    }
}
