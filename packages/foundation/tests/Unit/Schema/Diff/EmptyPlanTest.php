<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;

/**
 * Locks the canonical empty-plan identity per §15 Q3 (2026-05-02).
 *
 * `CompositeDiff([])` is the empty plan — there is no separate `Empty`
 * type. Its checksum is part of the public identity contract: callers
 * may use it as a "no work" sentinel in the migrations ledger.
 */
#[CoversClass(CompositeDiff::class)]
final class EmptyPlanTest extends TestCase
{
    /**
     * Canonical empty-plan SHA-256.
     *
     * Computed once and locked here. If you change {@see CompositeDiff::toCanonical()}
     * or {@see \Waaseyaa\Foundation\Schema\Diff\CanonicalJson::encode()} and this hash
     * diverges, you are changing every recorded `checksum` and `diff_hash` in
     * production migrations ledgers — STOP and write a migration ADR first.
     *
     * @see docs/specs/schema-evolution-v2.md §15 Q2 (2026-05-02)
     */
    private const EMPTY_PLAN_CHECKSUM = 'b2f0effc1a37cecc88986e93381ca24c017e5b7a288ea14a9462ae9b4c466f0c';

    #[Test]
    public function emptyConstructorMatchesNamedFactory(): void
    {
        self::assertTrue((new CompositeDiff([]))->equals(CompositeDiff::empty()));
    }

    #[Test]
    public function emptyPlanCanonicalIsExactlyOpsBracket(): void
    {
        self::assertSame(['ops' => []], CompositeDiff::empty()->toCanonical());
        self::assertSame('{"ops":[]}', CompositeDiff::empty()->toCanonicalJson());
    }

    #[Test]
    public function emptyPlanChecksumIsLocked(): void
    {
        self::assertSame(
            self::EMPTY_PLAN_CHECKSUM,
            CompositeDiff::empty()->checksum(),
            'The canonical empty-plan checksum must not change. See class docblock.',
        );
    }

    #[Test]
    public function defaultConstructorProducesEmptyPlan(): void
    {
        self::assertSame(self::EMPTY_PLAN_CHECKSUM, (new CompositeDiff())->checksum());
    }
}
