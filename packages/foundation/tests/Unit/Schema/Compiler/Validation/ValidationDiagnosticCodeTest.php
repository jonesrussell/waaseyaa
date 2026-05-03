<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Validation\ValidationDiagnosticCode;

/**
 * Locks the public diagnostic-code strings emitted by the validation
 * layer. Once shipped these strings must not change; downstream tools
 * (operator runbooks, dashboards, CI greps) match on them.
 */
#[CoversClass(ValidationDiagnosticCode::class)]
final class ValidationDiagnosticCodeTest extends TestCase
{
    #[Test]
    public function unknownOpKindCodeIsLocked(): void
    {
        self::assertSame('UNKNOWN_OP_KIND', ValidationDiagnosticCode::UnknownOpKind->value);
    }

    #[Test]
    public function destructiveOpBlockedCodeIsLocked(): void
    {
        self::assertSame('DESTRUCTIVE_OP_BLOCKED', ValidationDiagnosticCode::DestructiveOpBlocked->value);
    }

    #[Test]
    public function illegalOpOrderCodeIsLocked(): void
    {
        self::assertSame('ILLEGAL_OP_ORDER', ValidationDiagnosticCode::IllegalOpOrder->value);
    }

    #[Test]
    public function exposesAllDeclaredCases(): void
    {
        $values = array_map(
            static fn(ValidationDiagnosticCode $c): string => $c->value,
            ValidationDiagnosticCode::cases(),
        );

        self::assertSame(
            [
                'UNKNOWN_OP_KIND',
                'DESTRUCTIVE_OP_BLOCKED',
                'ILLEGAL_OP_ORDER',
            ],
            $values,
        );
    }
}
