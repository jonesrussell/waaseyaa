<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\Entity\RevisionMetadata;
use Waaseyaa\EntityStorage\Hydration\EntityInstantiator;

/**
 * Shared helper for revision table row → RevisionableEntityInterface hydration.
 *
 * Used by both RevisionableSqlBlobStorage and RevisionableSqlColumnStorage via
 * composition (both are final classes and cannot extend a base). Composition
 * was chosen over a trait because the logic has its own invariants and
 * dependencies that are cleaner to test in isolation.
 *
 * ## Naming
 *
 * The revision table is `<entity_type_id>__revision` (double underscore), matching
 * the schema emitted by {@see \Waaseyaa\EntityStorage\Schema\RevisionTableBuilder}.
 *
 * @internal Not part of the public API; internal implementation detail.
 */
final class RevisionRowHydrator
{
    public function __construct(
        private readonly DBALDatabase $database,
        private readonly EntityTypeInterface $entityType,
    ) {}

    /**
     * Build the revision table name for the entity type.
     */
    public function revisionTableName(): string
    {
        return $this->entityType->id() . '__revision';
    }

    /**
     * Build the primary table name for the entity type.
     */
    public function primaryTableName(): string
    {
        return $this->entityType->id();
    }

    /**
     * Resolve the id column name from entity type keys.
     */
    public function idColumn(): string
    {
        return $this->entityType->getKeys()['id'] ?? 'id';
    }

    /**
     * Resolve the revision key name from entity type keys (for setting on entity).
     */
    public function revisionKey(): string
    {
        return $this->entityType->getKeys()['revision'] ?? 'vid';
    }

    /**
     * Fetch the current vid from the primary table for a given entity id.
     *
     * Returns null when the entity does not exist.
     */
    public function fetchCurrentVid(int|string $entityId): ?int
    {
        $idColumn = $this->idColumn();
        $primaryTable = $this->primaryTableName();

        $result = $this->database
            ->select($primaryTable)
            ->fields($primaryTable, ['vid'])
            ->condition($idColumn, $entityId)
            ->execute();

        foreach ($result as $row) {
            $row = (array) $row;
            $val = $row['vid'] ?? null;
            return $val !== null ? (int) $val : null;
        }

        return null;
    }

    /**
     * Hydrate a raw revision row into a RevisionableEntityInterface instance.
     *
     * @param array<string, mixed> $row         Raw row from the __revision table.
     * @param int|string           $currentVid  Current vid from the primary table.
     * @param bool                 $isBlob       True when the revision table uses a _data blob (sql-blob backend).
     */
    public function hydrateRevisionRow(
        array $row,
        int|string $currentVid,
        bool $isBlob,
    ): RevisionableEntityInterface {
        $idColumn = $this->idColumn();
        $vid = isset($row['vid']) ? (int) $row['vid'] : null;

        // Re-inject the entity id from the FK column into the id key position.
        $fkValue = $row[$idColumn] ?? null;
        if ($fkValue !== null) {
            $row[$idColumn] = is_numeric($fkValue) ? (int) $fkValue : $fkValue;
        }

        // For blob storage: merge _data JSON into the row (byte-identical to primary table read).
        if ($isBlob && isset($row['_data'])) {
            try {
                $extra = json_decode(
                    (string) $row['_data'],
                    associative: true,
                    depth: 512,
                    flags: \JSON_THROW_ON_ERROR,
                );
                if (is_array($extra)) {
                    $row = array_merge($row, $extra);
                }
            } catch (\JsonException) {
                // Corrupt blob — continue with what we have.
            }
            unset($row['_data']);
        }

        // Extract revision metadata before merging into values array.
        $revisionCreatedAt = $row['revision_created_at'] ?? null;
        $revisionAuthor = isset($row['revision_author']) ? (int) $row['revision_author'] : null;
        $revisionLog = isset($row['revision_log']) ? (string) $row['revision_log'] : null;

        // Remove revision-table-only columns from the values the entity sees.
        unset($row['revision_created_at'], $row['revision_author'], $row['revision_log']);

        // Inject the vid into the revision key position.
        if ($vid !== null) {
            $revisionKey = $this->revisionKey();
            $row[$revisionKey] = $vid;
        }

        $entity = new EntityInstantiator($this->entityType)->instantiate(
            $this->entityType->getClass(),
            $row,
        );

        // Mark as not new (loaded from storage).
        if (method_exists($entity, 'enforceIsNew')) {
            $entity->enforceIsNew(false);
        }

        if (!($entity instanceof RevisionableEntityInterface)) {
            throw new \LogicException(sprintf(
                'Entity class "%s" does not implement RevisionableEntityInterface.',
                $entity::class,
            ));
        }

        // Set revision id.
        if (method_exists($entity, 'setRevisionId')) {
            $entity->setRevisionId($vid);
        }

        // Set isCurrentRevision flag: true only when this vid matches the primary pointer.
        $isCurrent = ($vid !== null && $vid === (int) $currentVid);
        if (method_exists($entity, 'setIsCurrentRevision')) {
            $entity->setIsCurrentRevision($isCurrent);
        }

        // Set revision metadata when available.
        if ($revisionCreatedAt !== null && method_exists($entity, 'setRevisionMetadata')) {
            try {
                $dt = new \DateTimeImmutable((string) $revisionCreatedAt);
                $entity->setRevisionMetadata(new RevisionMetadata(
                    revisionCreatedAt: $dt,
                    revisionAuthor: $revisionAuthor,
                    revisionLog: $revisionLog,
                ));
            } catch (\Throwable) {
                // Unparseable timestamp — metadata stays null.
            }
        }

        return $entity;
    }
}
