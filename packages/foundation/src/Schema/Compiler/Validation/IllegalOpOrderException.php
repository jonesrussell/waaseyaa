<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Validation;

/**
 * Same-composite ordering violation detected by {@see OrderingValidator}.
 *
 * Examples:
 *
 * - `AddIndex(t, [c1])` appears before `AddColumn(t, c1)` in the same
 *   composite (forward reference to a not-yet-created column).
 * - `AddColumn(t, c2)` appears after `RenameColumn(t, c1, c2)` (the rename
 *   already created `c2`; the second add collides).
 * - `AddColumn(t, c1)` appears twice (duplicate creation).
 *
 * The validator looks only at ops within the supplied
 * {@see \Waaseyaa\Foundation\Schema\Diff\CompositeDiff}. It performs no
 * database introspection — pre-existing schema state is *assumed valid*
 * for v1; verify mode (WP10) is the layer that reconciles compiled plans
 * against live schema.
 */
final class IllegalOpOrderException extends ValidationException
{
    public static function forForwardReference(string $opDescription, string $conflictDescription): self
    {
        return new self(
            ValidationDiagnosticCode::IllegalOpOrder->value,
            sprintf(
                'Illegal op order: %s — %s.',
                $opDescription,
                $conflictDescription,
            ),
        );
    }
}
