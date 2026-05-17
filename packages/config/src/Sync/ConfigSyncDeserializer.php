<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Waaseyaa\Config\Exception\ConfigSerializationException;

/**
 * Parse a sync-store YAML file into a {@see ConfigSyncFile} value object.
 *
 * Validation pipeline (per contracts/config-manifest.md §Validation pipeline):
 *  1. YAML parses to an array — else `ConfigSerializationException`.
 *  2. `_meta` block exists and has required keys (`entity_type`, `uuid`,
 *     `langcode`).
 *  3. `_meta.entity_type` matches the filename prefix — else
 *     `ConfigSerializationException::entityTypeMismatch()`.
 *  4. Field-presence / `FieldValueMapper` coercion is deferred to
 *     `ConfigSyncValidator` (later WP) which has access to entity-type
 *     `FieldDefinition` sets.
 */
final class ConfigSyncDeserializer
{
    /**
     * Pinned Symfony Yaml parse flags.
     *
     * - PARSE_EXCEPTION_ON_INVALID_TYPE: bail on objects / unknown tags.
     * - We intentionally do not enable PARSE_DATETIME so timestamps come back
     *   as ISO 8601 strings — `FieldValueMapper` then validates/normalises.
     */
    public const PARSE_FLAGS = Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE;

    /**
     * Parse a YAML string into a {@see ConfigSyncFile}.
     *
     * @param non-empty-string $yaml
     * @param non-empty-string $filename basename `<entity_type>.<entity_id>.yml`
     *
     * @throws ConfigSerializationException
     */
    public function fromYaml(string $yaml, string $filename): ConfigSyncFile
    {
        try {
            $parsed = Yaml::parse($yaml, self::PARSE_FLAGS);
        } catch (ParseException $exception) {
            throw ConfigSerializationException::malformedYaml($filename, $exception->getMessage());
        }

        if (!\is_array($parsed)) {
            throw ConfigSerializationException::malformedYaml(
                $filename,
                'top-level YAML node must be a mapping, got ' . get_debug_type($parsed),
            );
        }

        /** @var array<string, mixed> $parsed */
        return ConfigSyncFile::fromParsedArray($parsed, $filename);
    }
}
