<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Plugin;

/**
 * A single row produced by a source plugin.
 *
 * `$sourceType` is the source-format identifier (e.g. `wordpress_post`,
 * `csv:authors`) — NOT the destination entity type. `$fields` is the raw,
 * untransformed field map; process plugins consume it via {@see field()} during
 * the per-destination-field transformation chain.
 *
 * @api
 */
final readonly class SourceRecord
{
    /**
     * @param string $sourceType Source-format identifier. Must match `/^[a-z][a-z0-9_]*$/`.
     * @param array<array-key, mixed> $fields Raw source-row fields. May be empty (a source plugin that yields a row with no fields is valid, if unusual). All keys must be non-empty strings; an `\InvalidArgumentException` is raised at runtime if a numeric key sneaks in (PHP coerces stringy integer keys to int at array-write time).
     *
     * @throws \InvalidArgumentException If $sourceType is empty or malformed, or $fields contains non-string / empty-string keys.
     */
    public function __construct(
        public string $sourceType,
        public array $fields,
    ) {
        if ($sourceType === '') {
            throw new \InvalidArgumentException('SourceRecord::$sourceType must be a non-empty string.');
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $sourceType) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'SourceRecord::$sourceType must match /^[a-z][a-z0-9_]*$/, got %s.',
                var_export($sourceType, true),
            ));
        }
        /** @var array-key $name */
        foreach (array_keys($fields) as $name) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SourceRecord::$fields must be a non-empty string-keyed map.');
            }
        }
    }

    /**
     * Convenience accessor returning $fields[$name] or $default when absent.
     */
    public function field(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->fields) ? $this->fields[$name] : $default;
    }
}
