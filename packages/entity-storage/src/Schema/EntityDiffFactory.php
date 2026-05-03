<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Schema;

use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Field\FieldDefinitionInterface;
use Waaseyaa\Field\FieldStorage;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\AlterColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;
use Waaseyaa\Foundation\Schema\Diff\SchemaDiffOp;

/**
 * Computes an {@see EntityLevelDiff} by comparing the registered field
 * set of an entity type against a {@see SchemaSnapshot} of the live DB.
 *
 * **Algorithm (per spec §3.4 / WP07 T040):**
 *
 * 1. **Add:** for every registered field whose column does NOT appear
 *    in the snapshot for the relevant table, emit an `AddColumn`.
 * 2. **Alter:** for every registered field whose column DOES appear
 *    but the spec differs (type / nullability / default / length),
 *    emit an `AlterColumn`. The compiler will refuse this on SQLite
 *    per §15 Q5 — that gate is owned by the compiler, not the factory.
 * 3. **Drop:** for every snapshot column NOT in the registered set,
 *    emit a `DropColumn`. The compiler's destructive-op gate decides
 *    whether to allow it; the factory just produces the diff.
 *
 * **Bundle handling (per WP07 T042 / `bundle-scoped-storage.md`):**
 *
 * - Only entity types whose `getBundleEntityType()` is non-null have
 *   bundles.
 * - Each bundle's diff lands in its own {@see BundleLevelDiff}, scoped
 *   to the `{base}__{bundle}` subtable (via
 *   {@see SqlSchemaHandler::resolveSubtableName()}).
 * - **Empty subtables produce no BundleLevelDiff** — the factory does
 *   NOT pre-create empty subtables.
 *
 * **`FieldStorage::Data` discipline (per WP07 risk note / #1257 K2):**
 *
 * Fields stored in the `_data` JSON blob are NOT materialised as
 * columns. The factory MUST skip them. Regression here re-opens the
 * K2 invariant.
 *
 * **Rename detection:** the factory NEVER infers renames. A removed
 * field + a new field with a similar name produces drop+add, not
 * `RenameColumn`. Per spec §3.3, rename is always operator-authored.
 *
 * **Layer:** entity-storage (Layer 1) → foundation (Layer 0). The
 * factory is the only place that translates between the entity-storage
 * field shape and the foundation diff value types.
 */
final readonly class EntityDiffFactory
{
    /** @var \Closure(FieldDefinitionInterface): ColumnSpec */
    private \Closure $deriver;

    /**
     * @param \Closure(FieldDefinitionInterface): ColumnSpec|null $deriver
     *        Field → ColumnSpec mapper. Defaults to
     *        {@see SqlSchemaHandler::deriveDiffColumnSpec()} (the single
     *        source of truth). Tests may override to exercise edge cases.
     *
     *        Resolved in the constructor body because PHP 8.4 disallows
     *        first-class callable syntax (`Class::method(...)`) in
     *        default parameter values — it is not a constant expression.
     */
    public function __construct(?\Closure $deriver = null)
    {
        $this->deriver = $deriver ?? SqlSchemaHandler::deriveDiffColumnSpec(...);
    }

    /**
     * Build the entity-level diff for one entity type.
     *
     * @param EntityTypeInterface                              $type
     * @param string                                           $baseTable     usually `$type->id()`
     * @param list<FieldDefinitionInterface>                   $coreFields    fields that live on the base table
     * @param array<string, list<FieldDefinitionInterface>>    $bundleFields  per-bundle field lists (bundleId => fields)
     * @param SchemaSnapshot                                   $current       in-memory snapshot of live DB state
     */
    public function forEntityType(
        EntityTypeInterface $type,
        string $baseTable,
        array $coreFields,
        array $bundleFields,
        SchemaSnapshot $current,
    ): EntityLevelDiff {
        $coreOps = $this->diffTable($baseTable, $coreFields, $current);

        $bundleDiffs = [];
        foreach ($bundleFields as $bundleId => $fields) {
            $subtable = SqlSchemaHandler::resolveSubtableName($baseTable, $bundleId, $type->id());
            $bundleOps = $this->diffTable($subtable, $fields, $current);

            // Empty subtable → no BundleLevelDiff (WP07 T042: factory
            // does NOT pre-create empty subtables).
            if ($bundleOps === []) {
                continue;
            }

            $bundleDiffs[] = new BundleLevelDiff(
                entityTypeId: $type->id(),
                bundleId: $bundleId,
                baseTable: $baseTable,
                composite: new CompositeDiff($bundleOps),
            );
        }

        return new EntityLevelDiff(
            entityTypeId: $type->id(),
            composite: new CompositeDiff($coreOps),
            bundleDiffs: $bundleDiffs,
        );
    }

    /**
     * @param list<FieldDefinitionInterface> $fields
     * @return list<SchemaDiffOp>
     */
    private function diffTable(string $table, array $fields, SchemaSnapshot $current): array
    {
        $registeredColumns = $this->materialisedColumns($fields);
        $snapshotColumns = $current->columnsFor($table);

        $ops = [];

        // Phase 1: Add / Alter for registered fields.
        foreach ($registeredColumns as $columnName => $spec) {
            if (! isset($snapshotColumns[$columnName])) {
                $ops[] = new AddColumn($table, $columnName, $spec);
                continue;
            }
            if (! $this->specsEqual($snapshotColumns[$columnName], $spec)) {
                $ops[] = new AlterColumn($table, $columnName, $spec);
            }
        }

        // Phase 2: Drop for snapshot columns no longer in the registry.
        $droppedColumns = array_keys(array_diff_key($snapshotColumns, $registeredColumns));
        foreach ($droppedColumns as $columnName) {
            $ops[] = new DropColumn($table, $columnName);
        }

        return $ops;
    }

    /**
     * Map the registered field set to (column name → ColumnSpec),
     * skipping `FieldStorage::Data` per the K2 invariant.
     *
     * @param list<FieldDefinitionInterface> $fields
     * @return array<string, ColumnSpec>
     */
    private function materialisedColumns(array $fields): array
    {
        $columns = [];
        foreach ($fields as $field) {
            if ($field->getStored() === FieldStorage::Data) {
                continue;
            }
            $columns[$field->getName()] = ($this->deriver)($field);
        }
        return $columns;
    }

    /**
     * Structural equality of two {@see ColumnSpec}s. Reuses the
     * canonical-array form so the comparison stays lockstep with
     * {@see ColumnSpec::toCanonical()} — if that shape ever evolves,
     * equality automatically picks up the new field.
     */
    private function specsEqual(ColumnSpec $a, ColumnSpec $b): bool
    {
        return $a->toCanonical() === $b->toCanonical();
    }
}
