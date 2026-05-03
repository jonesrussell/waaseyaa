<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Validation;

/**
 * A destructive op (`DropColumn`, `DropIndex`, …) appeared in the diff
 * but the {@see PlanPolicy} did not opt-in to destruction.
 *
 * Per spec §11 (safety gates), destructive structural changes require
 * explicit operator consent at the plan level. The diff value types are
 * intentionally policy-free — the gate lives one layer up so the algebra
 * stays pure and reusable across dry-run / apply / verify paths.
 *
 * To allow destruction explicitly, construct the compiler with
 * `PlanPolicy(allowDestructive: true)`.
 */
final class DestructiveOpBlockedException extends ValidationException
{
    public function __construct(
        string $diagnosticCode,
        string $message,
        public readonly string $opKind,
    ) {
        parent::__construct($diagnosticCode, $message);
    }

    public static function forOp(string $opKind, string $table, ?string $detail = null): self
    {
        $message = sprintf(
            'Destructive op "%s" on table "%s" blocked by default policy. Pass PlanPolicy(allowDestructive: true) to compile() to acknowledge data loss.',
            $opKind,
            $table,
        );

        if ($detail !== null) {
            $message .= ' ' . $detail;
        }

        return new self(
            ValidationDiagnosticCode::DestructiveOpBlocked->value,
            $message,
            $opKind,
        );
    }
}
