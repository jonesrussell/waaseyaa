<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\DateTime\EntityClockInterface;
use Waaseyaa\Entity\DateTime\TimestampFieldConvention;
use Waaseyaa\Entity\DateTime\UtcEntityClock;
use Waaseyaa\Entity\EntityBase;
use Waaseyaa\Entity\EntityConstants;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\Event\DefaultEntityEventFactory;
use Waaseyaa\Entity\Event\EntityEventFactoryInterface;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\Field\FieldDefinitionInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * SQL-based entity storage implementation.
 *
 * Stores entities in SQL tables using the waaseyaa/database-legacy package.
 * Supports CRUD operations and dispatches entity lifecycle events.
 *
 * For v0.1.0:
 * - Flat table schema (all fields in one table)
 * - No revision support
 * - No translation support
 */
final class SqlEntityStorage implements EntityStorageInterface
{
    private readonly string $tableName;
    private readonly string $idKey;
    private readonly ?string $bundleKey;

    /** @var array<string, string> */
    private readonly array $entityKeys;

    /** @var array<string, bool> Column existence cache (column name => exists in table). */
    private array $columnCache = [];

    /** @var array<string, bool> Bundle subtable existence cache (subtable name => exists). */
    private array $bundleSubtableCache = [];

    private readonly LoggerInterface $logger;

    private readonly EntityEventFactoryInterface $eventFactory;

    private readonly SqlEntityQueryResultCache $queryResultCache;

    private readonly EntityClockInterface $clock;

    private readonly ?FieldDefinitionRegistryInterface $fieldRegistry;

    public function __construct(
        private readonly EntityTypeInterface $entityType,
        private readonly DatabaseInterface $database,
        private readonly EventDispatcherInterface $eventDispatcher,
        ?FieldDefinitionRegistryInterface $fieldRegistry = null,
        ?LoggerInterface $logger = null,
        ?EntityEventFactoryInterface $eventFactory = null,
        ?SqlEntityQueryResultCache $queryResultCache = null,
        ?EntityClockInterface $clock = null,
    ) {
        $this->tableName = $this->entityType->id();
        $keys = $this->entityType->getKeys();
        $this->idKey = $keys['id'] ?? 'id';
        $this->bundleKey = $keys['bundle'] ?? null;
        $this->entityKeys = $keys;
        $this->logger = $logger ?? new NullLogger();
        $this->eventFactory = $eventFactory ?? new DefaultEntityEventFactory();
        $this->queryResultCache = $queryResultCache ?? new SqlEntityQueryResultCache();
        $this->clock = $clock ?? new UtcEntityClock();
        $this->fieldRegistry = $fieldRegistry;
    }

    public function create(array $values = []): EntityInterface
    {
        foreach ($this->entityType->getFieldDefinitions() as $name => $def) {
            if (array_key_exists($name, $values)) {
                continue;
            }
            if ($def instanceof FieldDefinitionInterface) {
                $defaultValue = $def->getDefaultValue();
                if ($defaultValue !== null) {
                    $values[$name] = $defaultValue;
                }

                continue;
            }
            if (is_array($def) && array_key_exists('default', $def)) {
                $values[$name] = $def['default'];
            }
        }

        $class = $this->entityType->getClass();
        $entity = $this->instantiateEntity($class, $values);

        if (method_exists($entity, 'enforceIsNew')) {
            $entity->enforceIsNew();
        }

        return $entity;
    }

    public function load(int|string $id): ?EntityInterface
    {
        $result = $this->database->select($this->tableName)
            ->fields($this->tableName)
            ->condition($this->idKey, $id)
            ->execute();

        $row = null;
        foreach ($result as $r) {
            $row = (array) $r;
            break;
        }

        if ($row === null) {
            return null;
        }

        $this->mergeBundleSubtableRow($row);

        return $this->mapRowToEntity($row);
    }

    public function loadByKey(string $key, mixed $value): ?EntityInterface
    {
        $ids = $this->getQuery()
            ->condition($key, $value)
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return null;
        }

        return $this->load(reset($ids));
    }

    /**
     * @param array<int|string> $ids
     * @return array<int|string, EntityInterface>
     */
    public function loadMultiple(array $ids = []): array
    {
        $query = $this->database->select($this->tableName)
            ->fields($this->tableName);

        if (!empty($ids)) {
            $query->condition($this->idKey, $ids, 'IN');
        }

        $result = $query->execute();

        $rows = [];
        foreach ($result as $r) {
            $rows[] = (array) $r;
        }

        if ($rows === []) {
            return [];
        }

        $this->mergeBundleSubtableRowsBatch($rows);

        $entities = [];
        foreach ($rows as $row) {
            $entity = $this->mapRowToEntity($row);
            $entityId = $entity->id();
            if ($entityId !== null) {
                $entities[$entityId] = $entity;
            }
        }

        return $entities;
    }

    public function save(EntityInterface $entity): int
    {
        $isNew = $entity->isNew();

        // Auto-populate timestamp fields.
        $this->populateTimestamps($entity, $isNew);

        // Dispatch PRE_SAVE event (before snapshotting so listeners can mutate the entity).
        $this->eventDispatcher->dispatch(
            $this->eventFactory->create($entity),
            EntityEvents::PRE_SAVE->value,
        );

        // Snapshot entity values AFTER PRE_SAVE so listener mutations are persisted.
        $values = $entity->toArray();

        // Partition values into base-row + bundle-subtable shapes. Bundle-scoped
        // fields (per the registry) are pulled out first so splitForStorage's
        // _data fallback doesn't absorb them. Mismatched-bundle fields throw.
        [$baseValues, $bundleValues, $currentBundle] = $this->partitionBundleValues($values, $entity);
        $dbValues = $this->splitForStorage($baseValues);

        $writesSubtable = false;
        if ($bundleValues !== [] && $currentBundle !== null) {
            $writesSubtable = $this->bundleSubtableExists($currentBundle);
            if (!$writesSubtable) {
                $this->logger->notice(\sprintf(
                    '[MISSING_BUNDLE_SUBTABLE] Bundle-scoped fields are registered for entity type "%s" bundle "%s", but subtable "%s" does not exist at save time. Bundle-field values will not be persisted for this write. Run the schema migration or sync that materializes the subtable before saving this bundle.',
                    $this->entityType->id(),
                    $currentBundle,
                    $this->bundleSubtableName($currentBundle),
                ));
            }
        }

        $txn = $writesSubtable ? $this->database->transaction() : null;
        try {
            if ($isNew) {
                $result = $this->insertBaseRow($entity, $dbValues);
            } else {
                $result = $this->updateBaseRow($entity, $dbValues);
            }

            if ($writesSubtable) {
                $entityId = $entity->id();
                if ($entityId !== null) {
                    /** @var string $currentBundle — narrowed by $writesSubtable. */
                    $this->upsertBundleRow($currentBundle, $entityId, $bundleValues);
                }
            }

            $txn?->commit();
        } catch (\Throwable $e) {
            $txn?->rollBack();
            throw $e;
        }

        $this->queryResultCache->invalidate($this->tableName);

        // Dispatch POST_SAVE event.
        $this->eventDispatcher->dispatch(
            $this->eventFactory->create($entity),
            EntityEvents::POST_SAVE->value,
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $dbValues
     */
    private function insertBaseRow(EntityInterface $entity, array $dbValues): int
    {
        $insertValues = [];
        foreach ($dbValues as $key => $value) {
            if ($key === $this->idKey && $value === null) {
                continue;
            }
            $insertValues[$key] = $value;
        }

        if (!isset($this->entityKeys['uuid']) && (!isset($insertValues[$this->idKey]) || $insertValues[$this->idKey] === '')) {
            throw new \InvalidArgumentException(\sprintf(
                'Config entity "%s" requires a non-empty string ID in the "%s" field.',
                $this->entityType->id(),
                $this->idKey,
            ));
        }

        $id = $this->database->insert($this->tableName)
            ->fields(\array_keys($insertValues))
            ->values($insertValues)
            ->execute();

        if (!isset($insertValues[$this->idKey]) && \method_exists($entity, 'set')) {
            $entity->set($this->idKey, (int) $id);
        }

        if (\method_exists($entity, 'enforceIsNew')) {
            $entity->enforceIsNew(false);
        }

        return EntityConstants::SAVED_NEW;
    }

    /**
     * @param array<string, mixed> $dbValues
     */
    private function updateBaseRow(EntityInterface $entity, array $dbValues): int
    {
        $updateFields = [];
        foreach ($dbValues as $key => $value) {
            if ($key === $this->idKey) {
                continue;
            }
            $updateFields[$key] = $value;
        }

        $this->database->update($this->tableName)
            ->fields($updateFields)
            ->condition($this->idKey, $entity->id())
            ->execute();

        return EntityConstants::SAVED_UPDATED;
    }

    /**
     * @param EntityInterface[] $entities
     */
    public function delete(array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        // Dispatch PRE_DELETE events.
        foreach ($entities as $entity) {
            $this->eventDispatcher->dispatch(
                $this->eventFactory->create($entity),
                EntityEvents::PRE_DELETE->value,
            );
        }

        // Collect IDs for deletion.
        $ids = [];
        foreach ($entities as $entity) {
            $id = $entity->id();
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        if (!empty($ids)) {
            $this->database->delete($this->tableName)
                ->condition($this->idKey, $ids, 'IN')
                ->execute();
            $this->queryResultCache->invalidate($this->tableName);
        }

        // Dispatch POST_DELETE events.
        foreach ($entities as $entity) {
            $this->eventDispatcher->dispatch(
                $this->eventFactory->create($entity),
                EntityEvents::POST_DELETE->value,
            );
        }
    }

    public function getQuery(): EntityQueryInterface
    {
        return new SqlEntityQuery(
            $this->entityType,
            $this->database,
            $this->queryResultCache,
            $this->fieldRegistry,
        );
    }

    public function getEntityTypeId(): string
    {
        return $this->entityType->id();
    }

    /**
     * Maps a database row to an entity object.
     *
     * @param array<string, mixed> $row
     */
    private function mapRowToEntity(array $row): EntityInterface
    {
        $class = $this->entityType->getClass();

        // Cast the ID to int if it is numeric.
        if (isset($row[$this->idKey]) && is_numeric($row[$this->idKey])) {
            $row[$this->idKey] = (int) $row[$this->idKey];
        }

        // Merge extra data from the _data JSON column back into values.
        if (isset($row['_data'])) {
            try {
                $extra = json_decode((string) $row['_data'], associative: true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning(sprintf('Corrupt _data JSON for %s entity %s: %s', $this->tableName, $row[$this->idKey] ?? '?', $e->getMessage()));
                $extra = [];
            }
            unset($row['_data']);
            $row = array_merge($row, $extra);
        }

        // Decode json field values from JSON strings back to arrays.
        $jsonFields = $this->getJsonFieldNames();
        foreach ($jsonFields as $fieldName => $_) {
            if (isset($row[$fieldName]) && is_string($row[$fieldName])) {
                try {
                    $row[$fieldName] = json_decode($row[$fieldName], associative: true, depth: 512, flags: \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $this->logger->warning(sprintf('Corrupt JSON in field "%s" for %s entity %s: %s', $fieldName, $this->tableName, $row[$this->idKey] ?? '?', $e->getMessage()));
                    $row[$fieldName] = null;
                }
            }
        }

        /** @var EntityInterface $entity */
        $entity = $this->instantiateEntity($class, $row);

        // Loaded entities are not new.
        if (method_exists($entity, 'enforceIsNew')) {
            $entity->enforceIsNew(false);
        }

        return $entity;
    }

    /**
     * Instantiate an entity, adapting to its constructor signature.
     *
     * Entity subclasses like User and Node define their own constructors
     * that only accept $values and hardcode entityTypeId/entityKeys.
     * This method detects the constructor shape and passes only what
     * the class accepts.
     *
     * @param class-string $class
     * @param array<string, mixed> $values
     */
    private function instantiateEntity(string $class, array $values): EntityInterface
    {
        return (new Hydration\EntityInstantiator($this->entityType))->instantiate($class, $values);
    }

    /**
     * Split entity values into schema columns + JSON _data blob.
     *
     * Values whose keys match actual table columns are stored directly.
     * All other values are JSON-encoded into the _data column.
     * Fields with type 'json' are JSON-encoded before storage.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function splitForStorage(array $values): array
    {
        $schema = $this->database->schema();
        $jsonFields = $this->getJsonFieldNames();
        $dataStoredFields = $this->getDataStoredCoreFieldNames();
        $dbValues = [];
        $extraData = [];

        foreach ($values as $key => $value) {
            if ($key === '_data') {
                continue;
            }
            // Honor explicit FieldStorage::Data: route to _data even if a
            // legacy column happens to exist. Keeps the registry's storage
            // hint authoritative over residual schema state.
            if (isset($dataStoredFields[$key])) {
                $extraData[$key] = $value;
                continue;
            }
            if ($this->columnExists($key, $schema)) {
                // Encode json field values that are arrays/objects to JSON strings.
                if (isset($jsonFields[$key]) && !is_string($value) && $value !== null) {
                    $value = json_encode($value, \JSON_THROW_ON_ERROR);
                }
                $dbValues[$key] = $value;
            } else {
                $extraData[$key] = $value;
            }
        }

        $dbValues['_data'] = json_encode($extraData, \JSON_THROW_ON_ERROR);

        return $dbValues;
    }

    /**
     * Returns the set of core field names whose registered storage hint is
     * FieldStorage::Data. Used by splitForStorage() to keep `_data`-routed
     * fields out of base columns even when legacy columns exist.
     *
     * @return array<string, true>
     */
    private function getDataStoredCoreFieldNames(): array
    {
        if ($this->fieldRegistry === null) {
            return [];
        }

        $names = [];
        foreach ($this->fieldRegistry->coreFieldsFor($this->entityType->id()) as $name => $definition) {
            if ($definition->getStored() === \Waaseyaa\Field\FieldStorage::Data) {
                $names[$name] = true;
            }
        }

        return $names;
    }

    /**
     * Auto-fills timestamp fields from definitions and optional casts (#1183).
     */
    private function populateTimestamps(EntityInterface $entity, bool $isNew): void
    {
        $fieldDefs = $this->entityType->getFieldDefinitions();
        $now = $this->clock->now();

        foreach ($fieldDefs as $fieldName => $def) {
            $meta = $this->fieldDefinitionAsMetadataArray($def);
            if (($meta['type'] ?? null) !== 'timestamp') {
                continue;
            }

            $role = TimestampFieldConvention::inferAutoPopulate($fieldName, $meta);
            if ($role === null) {
                continue;
            }

            if ($role === 'create') {
                if (!$isNew || !TimestampFieldConvention::isRawTimestampUnset($entity, $fieldName)) {
                    continue;
                }
            }

            $castSpec = $entity instanceof EntityBase ? $entity->getCastSpecForField($fieldName) : null;
            if (self::isDatetimeImmutableCastSpec($castSpec)) {
                $entity->set($fieldName, $now);

                continue;
            }

            $format = TimestampFieldConvention::resolveStorageFormat($fieldName, $meta);
            $scalar = $format === 'unix'
                ? $now->getTimestamp()
                : $now->format(\DateTimeInterface::ATOM);
            $entity->set($fieldName, $scalar);
        }
    }

    /**
     * @param string|array<string, mixed>|null $spec
     */
    private static function isDatetimeImmutableCastSpec(string|array|null $spec): bool
    {
        if ($spec === null) {
            return false;
        }

        $token = is_string($spec) ? $spec : (string) ($spec['type'] ?? '');

        return $token === 'datetime_immutable';
    }

    /** @var array<string, true>|null Cached json field names for this entity type. */
    private ?array $jsonFieldCache = null;

    /**
     * Returns field names whose type is 'json', keyed by name.
     *
     * @return array<string, true>
     */
    private function getJsonFieldNames(): array
    {
        if ($this->jsonFieldCache !== null) {
            return $this->jsonFieldCache;
        }

        $this->jsonFieldCache = [];
        foreach ($this->entityType->getFieldDefinitions() as $name => $def) {
            $meta = $this->fieldDefinitionAsMetadataArray($def);
            if (($meta['type'] ?? null) === 'json') {
                $this->jsonFieldCache[$name] = true;
            }
        }

        return $this->jsonFieldCache;
    }

    /**
     * Entity types may declare fields as legacy metadata arrays or as
     * {@see FieldDefinitionInterface} objects (registry / package entity types).
     *
     * @return array<string, mixed>
     */
    private function fieldDefinitionAsMetadataArray(mixed $def): array
    {
        if (is_array($def)) {
            return $def;
        }
        if ($def instanceof FieldDefinitionInterface) {
            $settings = $def->getSettings();

            return array_merge($settings, [
                'type' => $def->getType(),
            ]);
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported field definition for entity type %s: %s',
            $this->entityType->id(),
            is_object($def) ? $def::class : get_debug_type($def),
        ));
    }

    /**
     * Check if a column exists in the entity table (with caching).
     */
    private function columnExists(string $column, \Waaseyaa\Database\SchemaInterface $schema): bool
    {
        if (!isset($this->columnCache[$column])) {
            $this->columnCache[$column] = $schema->fieldExists($this->tableName, $column);
        }
        return $this->columnCache[$column];
    }

    /**
     * Partition entity values into base-row and bundle-subtable shapes.
     *
     * When no FieldDefinitionRegistry is wired, or when the entity type has
     * no bundleKey, or when no bundle fields are registered for the type, all
     * values flow to the base row unchanged and the bundle is reported as null.
     *
     * Otherwise, fields whose name is registered as a bundle field for the
     * entity's current bundle are pulled out into $bundleValues; fields
     * registered as bundle fields for some OTHER bundle are rejected as a
     * programming error (writing them would corrupt the schema).
     *
     * @param array<string, mixed> $values
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: ?string}
     */
    private function partitionBundleValues(array $values, EntityInterface $entity): array
    {
        if ($this->fieldRegistry === null || $this->bundleKey === null) {
            return [$values, [], null];
        }

        $entityTypeId = $this->entityType->id();
        $registeredBundles = $this->fieldRegistry->bundleNamesFor($entityTypeId);
        if ($registeredBundles === []) {
            return [$values, [], null];
        }

        $currentBundle = $entity->bundle();
        if ($currentBundle === '' || $currentBundle === $entityTypeId) {
            return [$values, [], null];
        }

        $bundleFieldNames = [];
        foreach ($this->fieldRegistry->bundleFieldsFor($entityTypeId, $currentBundle) as $name => $_def) {
            $bundleFieldNames[$name] = true;
        }

        $otherBundleFields = [];
        foreach ($registeredBundles as $bundle) {
            if ($bundle === $currentBundle) {
                continue;
            }
            foreach ($this->fieldRegistry->bundleFieldsFor($entityTypeId, $bundle) as $name => $_def) {
                $otherBundleFields[$name] = $bundle;
            }
        }

        $baseValues = [];
        $bundleValues = [];
        foreach ($values as $key => $value) {
            if (isset($bundleFieldNames[$key])) {
                $bundleValues[$key] = $value;
                continue;
            }
            if (isset($otherBundleFields[$key])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Field "%s" belongs to bundle "%s" but entity of type "%s" has bundle "%s".',
                    $key,
                    $otherBundleFields[$key],
                    $entityTypeId,
                    $currentBundle,
                ));
            }
            $baseValues[$key] = $value;
        }

        return [$baseValues, $bundleValues, $currentBundle];
    }

    private function bundleSubtableName(string $bundle): string
    {
        return $this->tableName . '__' . $bundle;
    }

    private function bundleSubtableExists(string $bundle): bool
    {
        $subtable = $this->bundleSubtableName($bundle);
        if (!isset($this->bundleSubtableCache[$subtable])) {
            $this->bundleSubtableCache[$subtable] = $this->database->schema()->tableExists($subtable);
        }
        return $this->bundleSubtableCache[$subtable];
    }

    /**
     * UPSERT a bundle subtable row by primary key.
     *
     * Portable across SQLite/MySQL/Postgres: probes for an existing row, then
     * issues UPDATE or INSERT. The subtable's PK column matches the base
     * table's idKey and carries an ON DELETE CASCADE FK per commit 3.
     *
     * @param array<string, mixed> $bundleValues
     */
    private function upsertBundleRow(string $bundle, int|string $id, array $bundleValues): void
    {
        $subtable = $this->bundleSubtableName($bundle);

        $existingResult = $this->database->select($subtable)
            ->fields($subtable, [$this->idKey])
            ->condition($this->idKey, $id)
            ->execute();

        $exists = false;
        foreach ($existingResult as $_) {
            $exists = true;
            break;
        }

        if ($exists) {
            if ($bundleValues === []) {
                return;
            }
            $this->database->update($subtable)
                ->fields($bundleValues)
                ->condition($this->idKey, $id)
                ->execute();
            return;
        }

        $insertRow = $bundleValues;
        $insertRow[$this->idKey] = $id;
        $this->database->insert($subtable)
            ->fields(\array_keys($insertRow))
            ->values($insertRow)
            ->execute();
    }

    /**
     * Merge the matching bundle subtable row into a single base-table row.
     *
     * No-op when the registry/bundleKey is unavailable, when the row lacks a
     * bundle/id, or when the subtable does not exist. Existing keys on the
     * base row win — bundle columns cannot shadow base columns.
     *
     * @param array<string, mixed> $row
     * @param-out array<string, mixed> $row
     */
    private function mergeBundleSubtableRow(array &$row): void
    {
        if ($this->fieldRegistry === null || $this->bundleKey === null) {
            return;
        }

        $bundle = $row[$this->bundleKey] ?? null;
        if (!\is_string($bundle) || $bundle === '') {
            return;
        }

        $id = $row[$this->idKey] ?? null;
        if ($id === null) {
            return;
        }

        if (!$this->bundleSubtableExists($bundle)) {
            return;
        }

        $subtable = $this->bundleSubtableName($bundle);
        $result = $this->database->select($subtable)
            ->fields($subtable)
            ->condition($this->idKey, $id)
            ->execute();

        foreach ($result as $subRow) {
            $subRowArr = (array) $subRow;
            unset($subRowArr[$this->idKey]);
            foreach ($subRowArr as $k => $v) {
                if (!\is_string($k)) {
                    continue;
                }
                if (!\array_key_exists($k, $row)) {
                    $row[$k] = $v;
                }
            }
            break;
        }
    }

    /**
     * Batch variant of mergeBundleSubtableRow: groups rows by bundle and
     * performs one IN query per bundle rather than one lookup per row.
     *
     * @param list<array<string, mixed>> $rows
     * @param-out list<array<string, mixed>> $rows
     */
    private function mergeBundleSubtableRowsBatch(array &$rows): void
    {
        if ($this->fieldRegistry === null || $this->bundleKey === null || $rows === []) {
            return;
        }

        $idsByBundle = [];
        $indexByBundleAndId = [];
        foreach ($rows as $i => $row) {
            $bundle = $row[$this->bundleKey] ?? null;
            $id = $row[$this->idKey] ?? null;
            if (!\is_string($bundle) || $bundle === '' || $id === null) {
                continue;
            }
            $idsByBundle[$bundle][] = $id;
            $indexByBundleAndId[$bundle][(string) $id] = $i;
        }

        foreach ($idsByBundle as $bundle => $ids) {
            if (!$this->bundleSubtableExists($bundle)) {
                continue;
            }
            $subtable = $this->bundleSubtableName($bundle);
            $result = $this->database->select($subtable)
                ->fields($subtable)
                ->condition($this->idKey, $ids, 'IN')
                ->execute();

            foreach ($result as $subRow) {
                $subRowArr = (array) $subRow;
                $subId = $subRowArr[$this->idKey] ?? null;
                if ($subId === null) {
                    continue;
                }
                $rowIndex = $indexByBundleAndId[$bundle][(string) $subId] ?? null;
                if ($rowIndex === null) {
                    continue;
                }
                unset($subRowArr[$this->idKey]);
                foreach ($subRowArr as $k => $v) {
                    if (!\is_string($k)) {
                        continue;
                    }
                    if (!\array_key_exists($k, $rows[$rowIndex])) {
                        $rows[$rowIndex][$k] = $v;
                    }
                }
            }
        }
    }
}
