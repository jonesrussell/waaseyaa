<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Fixtures;

use Waaseyaa\Migration\Exception\SourceReadException;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;
use Waaseyaa\Migration\SourceId;

/**
 * Reference {@see SourcePluginInterface} implementation backed by a CSV file.
 *
 * Two reasons this lives under `tests/Fixtures/` rather than `src/`:
 *
 * 1. CsvSource is the **reference plugin** the conformance suite drives
 *    against itself (FR-052). It proves the abstract bases (FR-049, FR-051)
 *    work without inflating the production surface area with a CSV reader.
 * 2. Per spec FR-052 it is NOT a first-party composer package — third-party
 *    plugin authors copy it as a starting point.
 *
 * The plugin treats the CSV file as a streaming, single-pass source:
 *
 * - Each call to {@see records()} re-opens the file and yields one
 *   {@see SourceRecord} per data row. The yielded generator is NOT
 *   rewindable; callers re-invoke {@see records()} to start over.
 * - The first row of the CSV is treated as the header. Field keys come
 *   straight from the header. Headers MUST be unique within the file
 *   (`array_combine` collapses duplicates; the loss is silent).
 * - {@see sourceIdFor()} extracts the configured `$keyFields` from the
 *   record and packages them into a {@see SourceId} with the configured
 *   `$sourceType` discriminator.
 *
 * @api Reference fixture used by the conformance suite. Not a production
 *      source plugin — operators wanting CSV ingestion should fork this
 *      and harden it (delimiter detection, BOM handling, charset
 *      conversion, etc.).
 *
 * @spec FR-052 — reference CSV source plugin (autoload-dev)
 */
final class CsvSource implements SourcePluginInterface
{
    /**
     * @param string $filePath Absolute path to the CSV file. Read on each `records()` call.
     * @param list<string> $keyFields Header column names that compose the SourceId for each row. Must be non-empty.
     * @param string $sourceType Source-format identifier. Must match `/^[a-z][a-z0-9_]*$/` (SourceRecord/SourceId contract).
     * @param string $pluginId Globally unique plugin id returned by {@see id()}. Defaults to `csv_reference`.
     * @param string $delimiter Single-character CSV delimiter. Defaults to `,`.
     * @param string $stability Either `stable` or `experimental`.
     *
     * @throws \InvalidArgumentException When `$keyFields` is empty, `$delimiter` is not a single character,
     *                                   or `$stability` is not `stable`/`experimental`.
     */
    public function __construct(
        public readonly string $filePath,
        public readonly array $keyFields,
        public readonly string $sourceType = 'csv',
        public readonly string $pluginId = 'csv_reference',
        public readonly string $delimiter = ',',
        public readonly string $stability = 'stable',
    ) {
        if ($keyFields === []) {
            throw new \InvalidArgumentException('CsvSource::$keyFields must be non-empty.');
        }
        foreach ($keyFields as $field) {
            if (!\is_string($field) || $field === '') {
                throw new \InvalidArgumentException('CsvSource::$keyFields entries must be non-empty strings.');
            }
        }
        if (\strlen($delimiter) !== 1) {
            throw new \InvalidArgumentException('CsvSource::$delimiter must be a single character.');
        }
        if ($stability !== 'stable' && $stability !== 'experimental') {
            throw new \InvalidArgumentException(\sprintf(
                'CsvSource::$stability must be `stable` or `experimental`, got %s.',
                \var_export($stability, true),
            ));
        }
    }

    public function id(): string
    {
        return $this->pluginId;
    }

    public function stability(): string
    {
        return $this->stability;
    }

    /**
     * Yield rows as {@see SourceRecord} instances. Re-opens the file each call;
     * the underlying generator is single-pass.
     *
     * @return \Generator<int, SourceRecord, mixed, void>
     *
     * @throws SourceReadException When the CSV file cannot be opened or the header row is missing/malformed.
     */
    public function records(): iterable
    {
        // Pre-check readability so we can raise the typed exception without
        // relying on `@` to silence PHP's fopen() warning on missing files
        // (issue #1454).
        if (!\is_file($this->filePath) || !\is_readable($this->filePath)) {
            throw new SourceReadException(
                sourceId: $this->pluginId,
                migrationId: 'unknown',
                reason: \sprintf('Cannot open CSV file %s for reading.', $this->filePath),
            );
        }

        $handle = \fopen($this->filePath, 'rb');
        if ($handle === false) {
            throw new SourceReadException(
                sourceId: $this->pluginId,
                migrationId: 'unknown',
                reason: \sprintf('Cannot open CSV file %s for reading.', $this->filePath),
            );
        }

        try {
            $header = \fgetcsv($handle, 0, $this->delimiter, '"', '\\');
            if ($header === false || $header === null) {
                throw new SourceReadException(
                    sourceId: $this->pluginId,
                    migrationId: 'unknown',
                    reason: \sprintf('CSV file %s is empty or missing a header row.', $this->filePath),
                );
            }

            /** @var list<string> $normalisedHeader */
            $normalisedHeader = [];
            foreach ($header as $column) {
                if (!\is_string($column) || $column === '') {
                    throw new SourceReadException(
                        sourceId: $this->pluginId,
                        migrationId: 'unknown',
                        reason: \sprintf('CSV header in %s contains an empty or non-string column name.', $this->filePath),
                    );
                }
                $normalisedHeader[] = $column;
            }

            $headerCount = \count($normalisedHeader);

            while (($row = \fgetcsv($handle, 0, $this->delimiter, '"', '\\')) !== false) {
                if ($row === null) {
                    continue;
                }
                // fgetcsv returns [null] for blank lines in some PHP builds.
                if (\count($row) === 1 && ($row[0] === null || $row[0] === '')) {
                    continue;
                }

                // Pad short rows / truncate long rows to header width so
                // array_combine never raises (defensive: malformed CSVs
                // surface as SourceReadException below, but we still want a
                // deterministic shape for partial-row recovery).
                if (\count($row) < $headerCount) {
                    $row = \array_pad($row, $headerCount, null);
                } elseif (\count($row) > $headerCount) {
                    $row = \array_slice($row, 0, $headerCount);
                }

                $assoc = \array_combine($normalisedHeader, $row);

                yield new SourceRecord(sourceType: $this->sourceType, fields: $assoc);
            }
        } finally {
            \fclose($handle);
        }
    }

    public function sourceIdFor(SourceRecord $record): SourceId
    {
        $keys = [];
        foreach ($this->keyFields as $field) {
            if (!\array_key_exists($field, $record->fields)) {
                throw new SourceReadException(
                    sourceId: $this->pluginId,
                    migrationId: 'unknown',
                    reason: \sprintf(
                        'CsvSource cannot compute SourceId: record missing required key field "%s".',
                        $field,
                    ),
                );
            }
            $value = $record->fields[$field];
            // SourceId::$keys must be scalar-or-null. CSV cells are strings or null.
            if ($value !== null && !\is_scalar($value)) {
                throw new SourceReadException(
                    sourceId: $this->pluginId,
                    migrationId: 'unknown',
                    reason: \sprintf(
                        'CsvSource cannot compute SourceId: key field "%s" must be scalar or null.',
                        $field,
                    ),
                );
            }
            $keys[$field] = $value;
        }

        return new SourceId(sourceType: $this->sourceType, keys: $keys);
    }

    /**
     * CSV row counts require a full pass; v1 returns null and lets progress
     * reporters fall back to unbounded mode (FR-049 allows null).
     */
    public function count(): ?int
    {
        return null;
    }
}
