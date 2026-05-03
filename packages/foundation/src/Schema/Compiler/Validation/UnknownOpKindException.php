<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Validation;

/**
 * Compiler encountered a {@see \Waaseyaa\Foundation\Schema\Diff\SchemaDiffOp}
 * whose kind has no translator in the platform compiler's dispatch table.
 *
 * Indicates either:
 *
 * - A new {@see \Waaseyaa\Foundation\Schema\Diff\OpKind} case was added
 *   without updating the compiler (CI should catch this with a test).
 * - A custom op type was smuggled into a {@see \Waaseyaa\Foundation\Schema\Diff\CompositeDiff}
 *   bypassing the canonical algebra.
 *
 * In both cases the diff cannot be safely compiled — fail loud rather
 * than silently dropping the op.
 */
final class UnknownOpKindException extends ValidationException
{
    public static function for(string $kindName): self
    {
        return new self(
            ValidationDiagnosticCode::UnknownOpKind->value,
            sprintf(
                'Compiler encountered an unknown op kind "%s" — either a new SchemaDiffOp shipped without compiler coverage, or a custom op type was injected into the algebra.',
                $kindName,
            ),
        );
    }
}
