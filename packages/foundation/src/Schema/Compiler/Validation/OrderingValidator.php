<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Validation;

use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\AddIndex;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;

/**
 * Pre-translation walk that catches same-composite ordering bugs.
 *
 * **What it detects (v1):**
 *
 * - **Forward column reference:** `AddIndex(t, [c1])` appears in the
 *   composite *before* `AddColumn(t, c1)` in the same composite.
 *   (If `c1` never appears in any `AddColumn` of this composite, it is
 *   assumed pre-existing — see "scope" below.)
 * - **Rename collision:** after `RenameColumn(t, from, to)`, a later
 *   `AddColumn(t, to)` would collide with the renamed name.
 * - **Duplicate add:** two `AddColumn(t, c)` ops in the same composite.
 * - **Stale rename source:** `RenameColumn(t, from, to)` appears, then
 *   a later op references `from` (the source column no longer exists
 *   under that name).
 *
 * **Scope (v1):**
 *
 * The validator walks ONLY the ops in the supplied
 * {@see CompositeDiff} — no database introspection. Pre-existing schema
 * state is treated as valid; if `c1` is not added in this composite the
 * validator assumes it already exists in the live schema. Verify mode
 * (WP10) is the layer that reconciles a compiled plan against live
 * schema and catches "the column doesn't actually exist" cases.
 */
final class OrderingValidator
{
    public function validate(CompositeDiff $diff): void
    {
        // Phase 1: collect every column added per table across the
        // entire composite. We need to know the future before we can
        // detect "AddIndex references column added later".
        /** @var array<string, list<string>> $futureAdds */
        $futureAdds = [];
        foreach ($diff->ops as $op) {
            if ($op instanceof AddColumn) {
                $futureAdds[$op->table][] = $op->column;
            }
        }

        // Phase 2: walk in order. Track which columns are "live" per
        // table — meaning created earlier in this composite OR assumed
        // pre-existing (i.e. not added in this composite at all).
        /** @var array<string, array<string, true>> $createdSoFar */
        $createdSoFar = [];

        foreach ($diff->ops as $op) {
            $this->checkOp($op, $createdSoFar, $futureAdds);
            $this->advance($op, $createdSoFar);
        }
    }

    /**
     * @param array<string, array<string, true>> $createdSoFar
     * @param array<string, list<string>>        $futureAdds
     */
    private function checkOp(object $op, array $createdSoFar, array $futureAdds): void
    {
        if ($op instanceof AddColumn) {
            if (isset($createdSoFar[$op->table][$op->column])) {
                throw IllegalOpOrderException::forForwardReference(
                    sprintf('AddColumn "%s"."%s"', $op->table, $op->column),
                    'a prior op in this composite already created that column (duplicate add or rename collision)',
                );
            }
            return;
        }

        if ($op instanceof AddIndex) {
            foreach ($op->columns as $column) {
                $addedLater = in_array($column, $futureAdds[$op->table] ?? [], true)
                    && ! isset($createdSoFar[$op->table][$column]);
                if ($addedLater) {
                    throw IllegalOpOrderException::forForwardReference(
                        sprintf('AddIndex on "%s" referencing column "%s"', $op->table, $column),
                        'that column is added later in the same composite — reorder so AddColumn precedes AddIndex',
                    );
                }
            }
            return;
        }

        if ($op instanceof RenameColumn) {
            $sourceRenamedAway = isset($createdSoFar[$op->table][$op->from . ':renamed-away']);
            if ($sourceRenamedAway) {
                throw IllegalOpOrderException::forForwardReference(
                    sprintf('RenameColumn "%s"."%s" → "%s"', $op->table, $op->from, $op->to),
                    'the source column was already renamed away earlier in this composite',
                );
            }
            return;
        }

        if ($op instanceof DropColumn) {
            $alreadyRenamedAway = isset($createdSoFar[$op->table][$op->column . ':renamed-away']);
            if ($alreadyRenamedAway) {
                throw IllegalOpOrderException::forForwardReference(
                    sprintf('DropColumn "%s"."%s"', $op->table, $op->column),
                    'that column was renamed earlier in this composite — reference its new name',
                );
            }
            return;
        }
    }

    /**
     * @param array<string, array<string, true>> $createdSoFar
     */
    private function advance(object $op, array &$createdSoFar): void
    {
        if ($op instanceof AddColumn) {
            $createdSoFar[$op->table][$op->column] = true;
            return;
        }

        if ($op instanceof RenameColumn) {
            $createdSoFar[$op->table][$op->to] = true;
            $createdSoFar[$op->table][$op->from . ':renamed-away'] = true;
            // Remove the old name from "live" set so a later AddColumn
            // with the same source name is allowed (the rename made the
            // name available again).
            unset($createdSoFar[$op->table][$op->from]);
            return;
        }
    }
}
