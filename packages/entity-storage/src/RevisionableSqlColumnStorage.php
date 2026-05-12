<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\EntityStorage\Event\AfterSaveEvent;
use Waaseyaa\EntityStorage\Event\BeforeSaveEvent;

/**
 * Revision storage for entity types using the `sql-column` primary backend.
 *
 * Reads and writes the `<entity>__revision` table where each field is stored
 * in its own column — mirroring the primary table's column layout.
 *
 * ## Code-sharing approach
 *
 * Shares {@see RevisionRowHydrator} with {@see RevisionableSqlBlobStorage} via
 * composition (see that class's docblock for rationale).
 *
 * @api
 */
final class RevisionableSqlColumnStorage implements RevisionableEntityStorageInterface
{
    private readonly RevisionRowHydrator $hydrator;

    public function __construct(
        private readonly DBALDatabase $database,
        EntityTypeInterface $entityType,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->hydrator = new RevisionRowHydrator($database, $entityType);
    }

    /**
     * {@inheritdoc}
     */
    public function loadRevision(
        EntityTypeInterface $type,
        int|string $revisionId,
    ): ?RevisionableEntityInterface {
        $revisionTable = $this->hydrator->revisionTableName();

        $result = $this->database
            ->select($revisionTable)
            ->fields($revisionTable)
            ->condition('vid', $revisionId)
            ->execute();

        $row = null;
        foreach ($result as $r) {
            $row = (array) $r;
            break;
        }

        if ($row === null) {
            return null;
        }

        $idColumn = $this->hydrator->idColumn();
        $fkEntityId = $row[$idColumn] ?? null;
        $currentVid = $fkEntityId !== null
            ? $this->hydrator->fetchCurrentVid($fkEntityId)
            : null;

        return $this->hydrator->hydrateRevisionRow(
            row: $row,
            currentVid: $currentVid ?? -1,
            isBlob: false,
        );
    }

    /**
     * {@inheritdoc}
     *
     * Generator — yields in descending `revision_created_at` order.
     *
     * @return \Generator<RevisionableEntityInterface>
     */
    public function listRevisions(RevisionableEntityInterface $entity): iterable
    {
        $revisionTable = $this->hydrator->revisionTableName();
        $idColumn = $this->hydrator->idColumn();
        $entityId = $entity->id();

        if ($entityId === null) {
            return;
        }

        $currentVid = $this->hydrator->fetchCurrentVid($entityId);

        $rows = $this->database->query(
            'SELECT * FROM ' . $this->database->quoteIdentifier($revisionTable)
            . ' WHERE ' . $this->database->quoteIdentifier($idColumn) . ' = ?'
            . ' ORDER BY revision_created_at DESC',
            [$entityId],
        );

        foreach ($rows as $row) {
            yield $this->hydrator->hydrateRevisionRow(
                row: (array) $row,
                currentVid: $currentVid ?? -1,
                isBlob: false,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentRevision(
        RevisionableEntityInterface $entity,
        int|string $revisionId,
    ): void {
        $revisionTable = $this->hydrator->revisionTableName();
        $primaryTable = $this->hydrator->primaryTableName();
        $idColumn = $this->hydrator->idColumn();
        $entityId = $entity->id();

        if ($entityId === null) {
            throw new \InvalidArgumentException('Cannot set current revision for an entity without an id.');
        }

        // Verify the target revision exists.
        $result = $this->database
            ->select($revisionTable)
            ->fields($revisionTable, ['vid'])
            ->condition('vid', $revisionId)
            ->execute();

        $found = false;
        foreach ($result as $row) {
            $row = (array) $row;
            if ((int) ($row['vid'] ?? -1) === (int) $revisionId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException(sprintf(
                'Revision "%s" does not exist in table "%s".',
                $revisionId,
                $revisionTable,
            ));
        }

        $saveContext = SaveContext::default();

        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch(new BeforeSaveEvent($entity, $saveContext, false));
        }

        $transaction = $this->database->transaction();
        try {
            $this->database->update($primaryTable)
                ->fields(['vid' => (int) $revisionId])
                ->condition($idColumn, $entityId)
                ->execute();
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch(new AfterSaveEvent($entity, $saveContext, false));
        }
    }
}
