<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Plugin;

use Waaseyaa\Migration\SourceId;

/**
 * Input to {@see DestinationPluginInterface::write()}.
 *
 * Produced by the migration runner after process plugins have transformed each
 * source field into its destination value. The bundle and language code are
 * optional because they are resolved per FR-024 / decision D8 at write time —
 * not every destination plugin uses them (e.g. raw row writers ignore both).
 *
 * @api
 */
final readonly class DestinationRecord
{
    /**
     * @param string $migrationId Id of the running migration that produced this record. Non-empty.
     * @param SourceId $sourceId The canonical source identity that maps to this destination write.
     * @param array<array-key, mixed> $values Destination field-name -> processed value map. May be empty (records with only a bundle/langcode change are valid). Keys must be non-empty strings — numeric keys raise `\InvalidArgumentException`.
     * @param string|null $bundle Optional destination bundle id (e.g. `article`); resolved by the destination plugin if applicable.
     * @param string|null $langcode Optional ISO 639-1 (or extended) language code.
     *
     * @throws \InvalidArgumentException If $migrationId is empty, $values has non-string keys, or $bundle/$langcode is an empty string (use null to opt out).
     */
    public function __construct(
        public string $migrationId,
        public SourceId $sourceId,
        public array $values,
        public ?string $bundle = null,
        public ?string $langcode = null,
    ) {
        if ($migrationId === '') {
            throw new \InvalidArgumentException('DestinationRecord::$migrationId must be a non-empty string.');
        }
        /** @var array-key $name */
        foreach (array_keys($values) as $name) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('DestinationRecord::$values must be a non-empty string-keyed map.');
            }
        }
        if ($bundle === '') {
            throw new \InvalidArgumentException('DestinationRecord::$bundle must be null or a non-empty string.');
        }
        if ($langcode === '') {
            throw new \InvalidArgumentException('DestinationRecord::$langcode must be null or a non-empty string.');
        }
    }
}
