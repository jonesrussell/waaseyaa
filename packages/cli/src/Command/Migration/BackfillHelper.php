<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migration;

use Doctrine\DBAL\Connection;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * Backfills extracted field values from the `_data` JSON blob into new typed columns.
 *
 * Called from the `up()` step of generated storage migration files. The helper
 * validates row counts pre/post backfill; a mismatch throws a {@see BackfillRowCountMismatchException}
 * which causes the migration transaction to roll back.
 *
 * @api
 */
final class BackfillHelper
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Backfill `$fields` from the `_data` JSON blob column into typed columns.
     *
     * Static convenience entry point used from generated migration files (which
     * have no DI container available). For injectable use, instantiate and call
     * {@see self::execute()} instead.
     *
     * @param list<string> $fields  Field names to extract from `_data` and write into columns.
     *
     * @throws BackfillRowCountMismatchException When the post-backfill row count differs from pre-backfill.
     *
     * @api
     */
    public static function backfill(Connection $conn, string $table, array $fields): void
    {
        $helper = new self(new NullLogger());
        $helper->execute($conn, $table, $fields);
    }

    /**
     * Instance entry point — allows injecting a real logger for progress reporting.
     *
     * @param list<string> $fields
     *
     * @throws BackfillRowCountMismatchException
     *
     * @api
     */
    public function execute(Connection $conn, string $table, array $fields): void
    {
        $logger = $this->logger ?? new NullLogger();

        if ($fields === []) {
            $logger->info('BackfillHelper: no fields to backfill.', ['table' => $table]);
            return;
        }

        // Snapshot pre-backfill row count.
        $preCount = (int) $conn->fetchOne('SELECT COUNT(*) FROM ' . $conn->quoteIdentifier($table));

        $logger->info('BackfillHelper: starting backfill.', [
            'table'       => $table,
            'fields'      => $fields,
            'row_count'   => $preCount,
        ]);

        if ($preCount === 0) {
            $logger->info('BackfillHelper: table is empty; nothing to backfill.', ['table' => $table]);
            return;
        }

        // Fetch all rows that have a _data blob.
        $rows = $conn->fetchAllAssociative(
            'SELECT id, _data FROM ' . $conn->quoteIdentifier($table) . ' WHERE _data IS NOT NULL',
        );

        $processedCount = 0;

        foreach ($rows as $row) {
            $id = $row['id'];
            $rawData = $row['_data'];

            if ($rawData === null || $rawData === '') {
                continue;
            }

            $data = json_decode($rawData, associative: true, flags: JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                $logger->warning('BackfillHelper: _data is not an object; skipping row.', [
                    'table' => $table,
                    'id'    => $id,
                ]);
                continue;
            }

            $updates = [];
            $params = [];
            $types = [];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = $conn->quoteIdentifier($field) . ' = ?';
                    $params[] = $data[$field];
                    $types[] = \Doctrine\DBAL\ParameterType::STRING;
                }
            }

            if ($updates === []) {
                continue;
            }

            $params[] = $id;
            $types[] = \Doctrine\DBAL\ParameterType::INTEGER;

            $conn->executeStatement(
                'UPDATE ' . $conn->quoteIdentifier($table)
                    . ' SET ' . implode(', ', $updates)
                    . ' WHERE id = ?',
                $params,
                $types,
            );

            $processedCount++;
        }

        $logger->info('BackfillHelper: rows processed.', [
            'table'     => $table,
            'processed' => $processedCount,
        ]);

        // Validate post-backfill row count.
        $postCount = (int) $conn->fetchOne('SELECT COUNT(*) FROM ' . $conn->quoteIdentifier($table));

        if ($postCount !== $preCount) {
            throw new BackfillRowCountMismatchException(
                table: $table,
                expected: $preCount,
                actual: $postCount,
            );
        }

        $logger->info('BackfillHelper: backfill complete; row count validated.', [
            'table'     => $table,
            'row_count' => $postCount,
        ]);
    }
}
