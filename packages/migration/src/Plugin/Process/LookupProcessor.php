<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Plugin\Process;

use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

/**
 * Resolve a source value through a sibling migration's id-map.
 *
 * Use this to map cross-migration references — e.g. a WordPress post's
 * `post_author` (a numeric WP user id) becomes the destination uuid of the
 * previously-imported account.
 *
 * The processor calls the `$lookup` closure injected on {@see ProcessContext}
 * (FR-028); the closure wraps {@see \Waaseyaa\Migration\MigrationIdMap::lookupDestination()}.
 * That is the only correct way for a process plugin to read the id-map — it
 * MUST NOT depend on `MigrationIdMap` directly (Layer 3 → Layer 3 is fine,
 * but the runner owns transactional scoping and test-double injection).
 *
 * Miss-handling is governed by {@see $onMiss}:
 *
 *   - `'null'` (default): return `null`. The destination field stays empty;
 *     downstream plugins (e.g. `DefaultValue`) can supply a placeholder.
 *   - `'fail'`: raise {@see \Waaseyaa\Migration\Exception\ProcessException}
 *     with code {@see \Waaseyaa\Migration\Exception\ProcessException::CODE_LOOKUP_MISS}.
 *
 * @api
 *
 * @spec FR-010 — framework-reserved process plugin (`lookup`)
 * @spec FR-028 — id-map lookup callable on ProcessContext
 */
final readonly class LookupProcessor implements ProcessPluginInterface
{
    public const string ON_MISS_NULL = 'null';
    public const string ON_MISS_FAIL = 'fail';

    /**
     * @param string $sourceField Source-record field carrying the key to look up. Non-empty.
     * @param string $migration Sibling migration id whose id-map is consulted. Non-empty.
     * @param string|null $sourceType `SourceId::$sourceType` used to construct the lookup key. Defaults to the running migration's source record sourceType.
     * @param string $keyField Name of the `SourceId::$keys` entry under which the source value is placed. Defaults to `'id'`.
     * @param string $onMiss Behaviour when the id-map has no row. One of {@see self::ON_MISS_NULL}, {@see self::ON_MISS_FAIL}.
     *
     * @throws \InvalidArgumentException If $sourceField or $migration is empty, or $onMiss is unrecognised.
     */
    public function __construct(
        public string $sourceField,
        public string $migration,
        public ?string $sourceType = null,
        public string $keyField = 'id',
        public string $onMiss = self::ON_MISS_NULL,
    ) {
        if ($sourceField === '') {
            throw new \InvalidArgumentException('LookupProcessor::$sourceField must be a non-empty string.');
        }
        if ($migration === '') {
            throw new \InvalidArgumentException('LookupProcessor::$migration must be a non-empty string.');
        }
        if ($keyField === '') {
            throw new \InvalidArgumentException('LookupProcessor::$keyField must be a non-empty string.');
        }
        if ($onMiss !== self::ON_MISS_NULL && $onMiss !== self::ON_MISS_FAIL) {
            throw new \InvalidArgumentException(\sprintf(
                'LookupProcessor::$onMiss must be %s or %s, got %s.',
                var_export(self::ON_MISS_NULL, true),
                var_export(self::ON_MISS_FAIL, true),
                var_export($onMiss, true),
            ));
        }
    }

    public function id(): string
    {
        return ReservedPluginIds::LOOKUP;
    }

    public function stability(): string
    {
        return 'stable';
    }

    /**
     * @throws \Waaseyaa\Migration\Exception\ProcessException When the lookup misses and `$onMiss === 'fail'`.
     */
    public function transform(mixed $value, ProcessContext $context): mixed
    {
        // Prefer the chained value if upstream produced one; otherwise read
        // directly from the source record. Allows
        // `[Lookup(field:'wp_id', migration:'wp_users')]` to act as chain
        // head as well as a follow-on step.
        $keyValue = $value ?? $context->sourceRecord->field($this->sourceField, null);

        if ($keyValue === null || $keyValue === '') {
            return null;
        }

        if (!is_scalar($keyValue)) {
            // SourceId::$keys requires scalar values — bail rather than
            // hashing an object/array unexpectedly.
            throw new \Waaseyaa\Migration\Exception\ProcessException(
                processCode: \Waaseyaa\Migration\Exception\ProcessException::CODE_LOOKUP_MISS,
                sourceField: $this->sourceField,
                migrationId: $context->migrationId,
                message: \sprintf(
                    'LookupProcessor cannot use a non-scalar source value for field %s.',
                    var_export($this->sourceField, true),
                ),
            );
        }

        $sourceType = $this->sourceType ?? $context->sourceRecord->sourceType;

        $sourceId = new SourceId(
            sourceType: $sourceType,
            keys: [$this->keyField => $keyValue],
        );

        $lookup = $context->lookup;
        $writeResult = $lookup($this->migration, $sourceId);

        if ($writeResult instanceof WriteResult) {
            return $writeResult->destinationUuid;
        }

        if ($this->onMiss === self::ON_MISS_FAIL) {
            throw new \Waaseyaa\Migration\Exception\ProcessException(
                processCode: \Waaseyaa\Migration\Exception\ProcessException::CODE_LOOKUP_MISS,
                sourceField: $this->sourceField,
                migrationId: $context->migrationId,
                message: \sprintf(
                    'LookupProcessor: no id-map row in migration %s for source value %s.',
                    var_export($this->migration, true),
                    var_export($keyValue, true),
                ),
            );
        }

        return null;
    }
}
