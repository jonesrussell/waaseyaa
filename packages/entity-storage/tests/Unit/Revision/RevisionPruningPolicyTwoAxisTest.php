<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit\Revision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\EntityStorage\Revision\RevisionPruningPolicy;

/**
 * Per-language revision pruning policy (M-004 / WP03, T020).
 *
 * Verifies FR-037..FR-039:
 *
 * - FR-037 — keep-counts extensible per langcode (uniform / per-langcode / wildcard).
 * - FR-038 — `candidateExcluded()` returns true for the current revision id.
 * - FR-039 — `default()` is a no-op (`isNoOp() === true`); no constraints apply.
 */
#[CoversClass(RevisionPruningPolicy::class)]
final class RevisionPruningPolicyTwoAxisTest extends TestCase
{
    #[Test]
    public function default_policy_is_no_op_per_fr_039(): void
    {
        $policy = RevisionPruningPolicy::default();

        self::assertTrue($policy->isNoOp());
        self::assertNull($policy->keepLastN);
        self::assertNull($policy->keepNewerThan);
        self::assertSame([], $policy->keepByAuthorRole);
    }

    #[Test]
    public function default_policy_returns_null_keep_count_for_every_langcode(): void
    {
        $policy = RevisionPruningPolicy::default();

        self::assertNull($policy->keepLastNFor('en'));
        self::assertNull($policy->keepLastNFor('oj'));
        self::assertNull($policy->keepLastNFor('xx-unregistered'));
    }

    #[Test]
    public function uniform_keep_last_applies_same_count_to_every_langcode(): void
    {
        $policy = RevisionPruningPolicy::keepLastUniform(5);

        self::assertFalse($policy->isNoOp());
        self::assertSame(5, $policy->keepLastNFor('en'));
        self::assertSame(5, $policy->keepLastNFor('oj'));
        self::assertSame(5, $policy->keepLastNFor('fr'));
    }

    #[Test]
    public function per_langcode_keep_last_returns_explicit_values(): void
    {
        $policy = RevisionPruningPolicy::keepLastPerLangcode([
            'en' => 10,
            'oj' => 50,
        ]);

        self::assertSame(10, $policy->keepLastNFor('en'));
        self::assertSame(50, $policy->keepLastNFor('oj'));
    }

    #[Test]
    public function per_langcode_keep_last_omitted_langcode_is_unbounded(): void
    {
        // Most-permissive rule: when neither the langcode nor the wildcard is
        // present in the map, the langcode is exempt from pruning.
        $policy = RevisionPruningPolicy::keepLastPerLangcode(['en' => 10]);

        self::assertSame(10, $policy->keepLastNFor('en'));
        self::assertNull($policy->keepLastNFor('oj'));
    }

    #[Test]
    public function per_langcode_keep_last_with_wildcard_applies_to_unlisted_langcodes(): void
    {
        $policy = RevisionPruningPolicy::keepLastPerLangcode([
            'en'                                      => 10,
            RevisionPruningPolicy::DEFAULT_LANGCODE_KEY => 3,
        ]);

        self::assertSame(10, $policy->keepLastNFor('en'));
        self::assertSame(3, $policy->keepLastNFor('oj'));
        self::assertSame(3, $policy->keepLastNFor('fr'));
    }

    #[Test]
    public function candidate_excluded_protects_current_revision_per_fr_038(): void
    {
        $policy = RevisionPruningPolicy::keepLastUniform(1);

        self::assertTrue(
            $policy->candidateExcluded(candidateRevisionId: 7, currentRevisionForLangcode: 7),
            'current revision MUST never be a deletion candidate (FR-038)',
        );
        self::assertFalse(
            $policy->candidateExcluded(candidateRevisionId: 6, currentRevisionForLangcode: 7),
            'historical revisions MAY be candidates (subject to other rules)',
        );
    }

    #[Test]
    public function rejects_non_string_keys_in_per_langcode_map(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RevisionPruningPolicy(keepLastN: [0 => 5]);
    }

    #[Test]
    public function rejects_empty_string_key_in_per_langcode_map(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RevisionPruningPolicy(keepLastN: ['' => 5]);
    }

    #[Test]
    public function rejects_negative_keep_count_scalar(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RevisionPruningPolicy(keepLastN: -1);
    }

    #[Test]
    public function rejects_negative_keep_count_in_map(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RevisionPruningPolicy(keepLastN: ['en' => -1]);
    }

    #[Test]
    public function keep_newer_than_alone_is_not_no_op(): void
    {
        $policy = new RevisionPruningPolicy(
            keepNewerThan: new \DateTimeImmutable('2026-01-01'),
        );

        self::assertFalse($policy->isNoOp());
    }

    #[Test]
    public function keep_by_author_role_alone_is_not_no_op(): void
    {
        $policy = new RevisionPruningPolicy(
            keepByAuthorRole: ['knowledge_keeper'],
        );

        self::assertFalse($policy->isNoOp());
    }

    #[Test]
    public function existing_m006_policy_class_remains_at_old_namespace(): void
    {
        // R-A regression gate: WP03 introduces the new
        // Waaseyaa\EntityStorage\Revision\RevisionPruningPolicy without
        // disturbing the M-006 Waaseyaa\EntityStorage\RevisionPruningPolicy.
        // Surface drift would break consumers compiled against the M-006 FQCN.
        self::assertTrue(
            class_exists(\Waaseyaa\EntityStorage\RevisionPruningPolicy::class, autoload: true),
            'M-006 single-axis RevisionPruningPolicy MUST remain at the legacy namespace',
        );
        self::assertNotSame(
            \Waaseyaa\EntityStorage\RevisionPruningPolicy::class,
            RevisionPruningPolicy::class,
        );
    }
}
