<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migrate;

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;

/**
 * Pure logic for `migrate --verify`: walks the ledger, compares stored
 * checksums against the live source's recomputed canonical hash.
 *
 * **v1 scope (per WP10 risk note):** checksum-vs-source comparison only.
 * Live database introspection ("did the schema actually apply?") is the
 * §12.5 round-trip non-goal; do not sneak it in here.
 *
 * **Status mapping:**
 *
 * | Stored checksum | Source present | Source kind | Computed matches | Status     |
 * |-----------------|----------------|-------------|------------------|------------|
 * | non-null        | yes            | v2          | yes              | `match`    |
 * | non-null        | yes            | v2          | no               | `mismatch` |
 * | non-null        | yes            | legacy      | n/a              | `unknown`  |
 * | non-null        | no             | n/a         | n/a              | `orphan`   |
 * | null            | yes or no      | n/a         | n/a              | `unknown`  |
 *
 * Legacy rows always land in `unknown` because legacy migrations don't
 * produce a canonical form. Pre-WP09 rows (null stored checksum) also
 * land in `unknown` per `docs/adr/008-ledger-checksum-backfill.md`.
 */
final readonly class VerifyRunner
{
    public function __construct(
        private MigrationRepository $repository,
    ) {}

    /**
     * @param array<string, array<string, Migration>> $legacy
     * @param list<MigrationInterfaceV2>              $v2
     */
    public function verify(array $legacy, array $v2): VerifyOutcome
    {
        $sources = $this->indexSources($legacy, $v2);

        $rows = [];
        $counts = ['match' => 0, 'mismatch' => 0, 'unknown' => 0, 'orphan' => 0];

        foreach ($this->repository->allWithChecksums() as $ledgerRow) {
            $row = $this->classify($ledgerRow->migration, $ledgerRow->checksum, $sources);
            $rows[] = $row;
            $counts[$row->status]++;
        }

        return new VerifyOutcome(
            rows: $rows,
            summary: new VerifySummary(
                match: $counts['match'],
                mismatch: $counts['mismatch'],
                unknown: $counts['unknown'],
                orphan: $counts['orphan'],
            ),
        );
    }

    /**
     * @param array<string, array<string, Migration>> $legacy
     * @param list<MigrationInterfaceV2>              $v2
     * @return array<string, array{kind: 'legacy'|'v2', source: Migration|MigrationInterfaceV2}>
     */
    private function indexSources(array $legacy, array $v2): array
    {
        $sources = [];

        foreach ($legacy as $packageMigrations) {
            foreach ($packageMigrations as $name => $migration) {
                $sources[$name] = ['kind' => 'legacy', 'source' => $migration];
            }
        }
        foreach ($v2 as $v) {
            $sources[$v->migrationId()] = ['kind' => 'v2', 'source' => $v];
        }

        return $sources;
    }

    /**
     * @param array<string, array{kind: 'legacy'|'v2', source: Migration|MigrationInterfaceV2}> $sources
     */
    private function classify(string $migrationId, ?string $stored, array $sources): VerifyResultRow
    {
        $source = $sources[$migrationId] ?? null;

        if ($source === null) {
            return new VerifyResultRow($migrationId, 'orphan', $stored, null);
        }

        if ($stored === null || $source['kind'] === 'legacy') {
            return new VerifyResultRow($migrationId, 'unknown', $stored, null);
        }

        $v2 = $source['source'];
        \assert($v2 instanceof MigrationInterfaceV2);
        $computed = $v2->plan()->checksum();

        return new VerifyResultRow(
            $migrationId,
            $stored === $computed ? 'match' : 'mismatch',
            $stored,
            $computed,
        );
    }
}
