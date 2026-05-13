<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\EntityStorage\SaveContext;

/**
 * Unit tests for {@see SaveContext}.
 *
 * Covers default-state, immutable transitions (withoutNewRevision, withLangcode,
 * asImport) and round-tripping of existing flags when new flags are set.
 *
 * The `isImport` flag is added by M-002 WP05 (FR-022) and is signal-only —
 * the coordinator does NOT alter behaviour based on it; subscribers read it
 * from the event-attached SaveContext.
 */
#[CoversClass(SaveContext::class)]
final class SaveContextTest extends TestCase
{
    #[Test]
    public function default_yields_canonical_flags(): void
    {
        $ctx = SaveContext::default();

        self::assertFalse($ctx->withoutNewRevision);
        self::assertNull($ctx->langcode);
        self::assertFalse($ctx->isImport);
    }

    #[Test]
    public function withoutNewRevision_flips_only_revision_flag(): void
    {
        $ctx = SaveContext::default()->withoutNewRevision();

        self::assertTrue($ctx->withoutNewRevision);
        self::assertNull($ctx->langcode);
        self::assertFalse($ctx->isImport);
    }

    #[Test]
    public function withLangcode_pins_translation(): void
    {
        $ctx = SaveContext::default()->withLangcode('fr');

        self::assertFalse($ctx->withoutNewRevision);
        self::assertSame('fr', $ctx->langcode);
        self::assertFalse($ctx->isImport);
    }

    #[Test]
    public function withLangcode_rejects_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SaveContext::default()->withLangcode('');
    }

    #[Test]
    public function asImport_sets_only_import_flag(): void
    {
        $ctx = SaveContext::default()->asImport();

        self::assertFalse($ctx->withoutNewRevision);
        self::assertNull($ctx->langcode);
        self::assertTrue($ctx->isImport);
    }

    #[Test]
    public function asImport_preserves_other_flags(): void
    {
        $ctx = SaveContext::default()
            ->withoutNewRevision()
            ->withLangcode('en')
            ->asImport();

        self::assertTrue($ctx->withoutNewRevision);
        self::assertSame('en', $ctx->langcode);
        self::assertTrue($ctx->isImport);
    }

    #[Test]
    public function withoutNewRevision_preserves_isImport(): void
    {
        $ctx = SaveContext::default()->asImport()->withoutNewRevision();

        self::assertTrue($ctx->withoutNewRevision);
        self::assertTrue($ctx->isImport);
    }

    #[Test]
    public function withLangcode_preserves_isImport(): void
    {
        $ctx = SaveContext::default()->asImport()->withLangcode('mzm');

        self::assertSame('mzm', $ctx->langcode);
        self::assertTrue($ctx->isImport);
    }

    #[Test]
    public function transitions_return_new_instances(): void
    {
        $a = SaveContext::default();
        $b = $a->asImport();

        self::assertNotSame($a, $b);
        self::assertFalse($a->isImport);
        self::assertTrue($b->isImport);
    }
}
