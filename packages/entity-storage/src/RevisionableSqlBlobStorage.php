<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\EntityStorage\Event\AfterSaveEvent;
use Waaseyaa\EntityStorage\Event\BeforeSaveEvent;
use Waaseyaa\EntityStorage\Schema\RevisionTableBuilder;

/**
 * Revision storage for entity types using the `sql-blob` primary backend.
 *
 * Reads and writes the `<entity>__revision` table where all non-key data is
 * stored as a `_data` JSON blob — byte-identical to the primary table's blob
 * encoding (T015 discipline: same `JSON_THROW_ON_ERROR`-only flags, no extras).
 *
 * ## Code-sharing approach
 *
 * Both this class and {@see RevisionableSqlColumnStorage} are `final` and
 * delegate to {@see RevisionRowHydrator} via **composition**. A shared base
 * class was rejected because the two classes have different write paths
 * (blob vs. per-column), and PHP traits would obscure the dependency graph.
 * Composition keeps each class self-contained and independently testable.
 *
 * @api
 */
final class RevisionableSqlBlobStorage implements RevisionableEntityStorageInterface
{
    private readonly RevisionRowHydrator $hydrator;
    private readonly EntityTypeInterface $entityType;

    public function __construct(
        private readonly DBALDatabase $database,
        EntityTypeInterface $entityType,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->hydrator = new RevisionRowHydrator($database, $entityType);
        $this->entityType = $entityType;
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

        // Need the primary table's current vid to determine isCurrentRevision.
        $idColumn = $this->hydrator->idColumn();
        $fkEntityId = $row[$idColumn] ?? null;
        $currentVid = $fkEntityId !== null
            ? $this->hydrator->fetchCurrentVid($fkEntityId)
            : null;

        return $this->hydrator->hydrateRevisionRow(
            row: $row,
            currentVid: $currentVid ?? -1,
            isBlob: true,
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
                isBlob: true,
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

    /**
     * Append a per-langcode translation revision row to
     * `<entity>__translation__revision` (M-004 / WP02, FR-001, FR-003, FR-008).
     *
     * The translatable payload is encoded as a single `_data` JSON blob,
     * byte-identical to the primary table's blob shape (T015 discipline:
     * `JSON_THROW_ON_ERROR` only — no `JSON_UNESCAPED_*` or pretty-print).
     *
     * Surrogate `vid` is allocated by the database (`INTEGER PRIMARY KEY`
     * on SQLite, `SERIAL` on Postgres). The newly-allocated vid is returned
     * so callers can update the `<entity>__translation` pointer in the same
     * transaction (per contracts/composite-pk.md §7.1).
     *
     * **Scope:** This method writes the per-revision row only. It does NOT
     * mutate the `<entity>__translation` pointer or the primary `<entity>`
     * row's `vid` — those updates belong to the save semantics WP (WP03).
     *
     * @param int|string             $entityId           Entity id (FK target).
     * @param string                 $langcode           Language code of the translation.
     * @param array<string, mixed>   $translatableValues Translatable field values
     *                                                   for this `(entity, langcode)` revision.
     * @param ?int                   $revisionAuthor     Optional author UID (soft FK).
     * @param ?string                $revisionLog        Optional revision log message.
     * @param ?\DateTimeImmutable    $createdAt          Optional explicit timestamp;
     *                                                   defaults to `now()` in UTC.
     *
     * @return int The newly-allocated surrogate `vid` for the inserted row.
     *
     * @throws \InvalidArgumentException When the entity type is not two-axis
     *                                   (revisionable + translatable) or its
     *                                   primary backend is not `sql-blob`.
     *
     * @api
     */
    public function writeTwoAxisTranslationRevision(
        int|string $entityId,
        string $langcode,
        array $translatableValues,
        ?int $revisionAuthor = null,
        ?string $revisionLog = null,
        ?\DateTimeImmutable $createdAt = null,
    ): int {
        $this->assertTwoAxisBlob();

        $table = $this->translationRevisionTableName();
        $createdAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // T015 discipline: JSON_THROW_ON_ERROR only — symmetric with the
        // primary table's blob encoding.
        $blob = json_encode($translatableValues, \JSON_THROW_ON_ERROR);

        // Pre-allocate the surrogate `vid` via `max(vid) + 1`. Doctrine DBAL
        // 4.x's `Connection::lastInsertId()` raises `NoIdentityValue` on
        // SQLite when the INSERT supplies an explicit value for the
        // AUTOINCREMENT column (or routes through `Connection::insert(table,
        // params)` which is what `DBALInsert::execute()` uses). Pre-allocation
        // sidesteps that entirely AND gives a deterministic monotonic value
        // for the per-(entity, langcode) sequence (contracts/composite-pk.md
        // §5.1, §5.2). Using raw SQL via `$db->query()` avoids the
        // `DBALInsert::execute()` `lastInsertId()` call path.
        $vid = $this->nextVid($table);

        $quotedTable = $this->database->quoteIdentifier($table);
        $sql = 'INSERT INTO ' . $quotedTable
            . ' (vid, entity_id, langcode, revision_created_at, revision_author, revision_log, _data)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?)';

        // `$db->query()` routes DDL/DML through `executeStatement`, which
        // does NOT consult `lastInsertId()` — safe for our pre-allocated id.
        iterator_to_array($this->database->query($sql, [
            $vid,
            (string) $entityId,
            $langcode,
            $createdAt->format(\DateTimeInterface::ATOM),
            $revisionAuthor,
            $revisionLog,
            $blob,
        ]));

        return $vid;
    }

    /**
     * Allocate the next surrogate `vid` for the per-revision blob table.
     *
     * Uses `COALESCE(MAX(vid), 0) + 1` so the sequence remains monotonic across
     * langcodes and entities (FR-018 ordering). Race-condition safety relies
     * on the caller wrapping the entire save in a transaction (WP03 will).
     */
    private function nextVid(string $table): int
    {
        $sql = 'SELECT COALESCE(MAX(vid), 0) AS max_vid FROM ' . $this->database->quoteIdentifier($table);
        foreach ($this->database->query($sql, []) as $r) {
            $row = (array) $r;
            return (int) ($row['max_vid'] ?? 0) + 1;
        }
        return 1;
    }

    /**
     * Load a per-langcode translation revision row, applying the FR-005
     * single-step fallback for non-translatable fields.
     *
     * Reads `<entity>__translation__revision` for `(entity_id, langcode, vid)`,
     * decodes the `_data` blob, then joins to `<entity>__revision` at the
     * current primary `vid` (one extra row lookup, NFR-A) to layer in
     * non-translatable values stored once on the default-langcode revision
     * (FR-004 storage rule, FR-005 read-path rule).
     *
     * Returns an associative array shaped like a primary-row read: keys are
     * field names, values are decoded scalars. Returns `null` when the
     * per-langcode revision row does not exist.
     *
     * @return array<string, mixed>|null
     *
     * @throws \InvalidArgumentException When the entity type is not two-axis
     *                                   (revisionable + translatable) or its
     *                                   primary backend is not `sql-blob`.
     *
     * @api
     */
    public function loadTwoAxisTranslationRevision(
        int|string $entityId,
        string $langcode,
        int $vid,
    ): ?array {
        $this->assertTwoAxisBlob();

        $table = $this->translationRevisionTableName();

        $result = $this->database
            ->select($table)
            ->fields($table)
            ->condition('entity_id', (string) $entityId)
            ->condition('langcode', $langcode)
            ->condition('vid', $vid)
            ->execute();

        $row = null;
        foreach ($result as $r) {
            $row = (array) $r;
            break;
        }

        if ($row === null) {
            return null;
        }

        // Decode the per-langcode translatable blob.
        $translatable = [];
        if (isset($row['_data']) && $row['_data'] !== '') {
            $decoded = json_decode(
                (string) $row['_data'],
                associative: true,
                depth: 512,
                flags: \JSON_THROW_ON_ERROR,
            );
            if (is_array($decoded)) {
                $translatable = $decoded;
            }
        }

        // FR-005 fallback: layer non-translatable values from
        // <entity>__revision at the current primary vid (one extra lookup).
        $nonTranslatable = $this->readNonTranslatableFromCurrentEntityRevision($entityId);

        // Translatable wins on key collision (defensive: collisions should
        // never happen because the partition is exclusive per FR-004, but
        // collisions in legacy migrated data would surface a bug rather than
        // silently overwriting per-langcode content with default values).
        $merged = $nonTranslatable;
        foreach ($translatable as $k => $v) {
            $merged[$k] = $v;
        }

        // Re-inject identity columns for caller convenience.
        $merged['entity_id'] = $row['entity_id'] ?? $entityId;
        $merged['langcode']  = $row['langcode']  ?? $langcode;
        $merged['vid']       = isset($row['vid']) ? (int) $row['vid'] : $vid;

        return $merged;
    }

    /**
     * Read the current entity-revision's non-translatable `_data` blob
     * (FR-005 single-step fallback support).
     *
     * Locates the current `vid` via the primary `<entity>` table, then
     * reads the `_data` JSON from `<entity>__revision` at that vid. The
     * returned array carries only non-translatable field values — the
     * partition rule (FR-004) is enforced upstream at write time.
     *
     * Returns an empty array when the primary row, the current vid, or
     * the revision row are missing — silent recovery is appropriate
     * because the read is "single-step fallback", not the primary path.
     *
     * @return array<string, mixed>
     */
    private function readNonTranslatableFromCurrentEntityRevision(int|string $entityId): array
    {
        $currentVid = $this->hydrator->fetchCurrentVid($entityId);
        if ($currentVid === null) {
            return [];
        }

        $revisionTable = $this->hydrator->revisionTableName();

        $result = $this->database
            ->select($revisionTable)
            ->fields($revisionTable)
            ->condition('vid', $currentVid)
            ->execute();

        foreach ($result as $r) {
            $row = (array) $r;
            if (!isset($row['_data']) || $row['_data'] === '') {
                return [];
            }
            try {
                $decoded = json_decode(
                    (string) $row['_data'],
                    associative: true,
                    depth: 512,
                    flags: \JSON_THROW_ON_ERROR,
                );
            } catch (\JsonException) {
                // Corrupt blob — fall back to empty rather than crashing the
                // translation read.
                return [];
            }
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * The per-revision blob table name `<entity>__translation__revision`.
     */
    private function translationRevisionTableName(): string
    {
        return $this->entityType->id() . RevisionTableBuilder::TRANSLATION_REVISION_SUFFIX;
    }

    /**
     * Guard for the WP02 two-axis blob entry points.
     *
     * @throws \InvalidArgumentException
     */
    private function assertTwoAxisBlob(): void
    {
        if (!$this->entityType->isRevisionable() || !$this->entityType->isTranslatable()) {
            throw new \InvalidArgumentException(sprintf(
                'RevisionableSqlBlobStorage two-axis methods require revisionable + translatable '
                . 'entity type; "%s" has revisionable=%s translatable=%s.',
                $this->entityType->id(),
                $this->entityType->isRevisionable() ? 'true' : 'false',
                $this->entityType->isTranslatable() ? 'true' : 'false',
            ));
        }

        $backend = $this->entityType->getPrimaryStorageBackend();
        if ($backend !== null && $backend !== '' && $backend !== \Waaseyaa\EntityStorage\Backend\ReservedBackendIds::SQL_BLOB) {
            throw new \InvalidArgumentException(sprintf(
                'RevisionableSqlBlobStorage two-axis methods require sql-blob primary backend; '
                . '"%s" declares "%s".',
                $this->entityType->id(),
                $backend,
            ));
        }
    }
}
