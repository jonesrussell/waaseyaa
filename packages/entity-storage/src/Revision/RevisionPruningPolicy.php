<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Revision;

/**
 * @api
 *
 * Two-axis revision pruning policy (M-004 / WP03, FR-037..FR-039).
 *
 * Extension of the M-006 single-axis {@see \Waaseyaa\EntityStorage\RevisionPruningPolicy}
 * scaffold with per-language keep-counts so operators MAY apply different
 * retention rules per langcode on a translatable + revisionable entity type
 * (FR-037). Lives at `Waaseyaa\EntityStorage\Revision\` so the M-006 surface
 * stays untouched (R-A regression gate, spec §12.3).
 *
 * ## Semantics
 *
 * - **Default is no-op (FR-039).** A policy constructed without arguments
 *   {@see RevisionPruningPolicy::default()} keeps every revision of every
 *   langcode forever. Operators MUST opt in explicitly via the named
 *   constructors below.
 *
 * - **Per-language keep-counts (FR-037).** `keepLastN` may be either a single
 *   integer (applies to all langcodes) or a `langcode => int` map (per-langcode
 *   keep-count). When a langcode is absent from the map AND no `default`
 *   sentinel is set, that langcode's revisions are NOT pruned (most permissive).
 *
 * - **Current revision is immortal (FR-038).** Whatever rule fires, the
 *   per-`(entity, langcode)` current revision MUST NEVER be a deletion
 *   candidate. {@see candidateExcluded()} is the canonical guard — pruning
 *   executors MUST consult it before issuing any DELETE.
 *
 * - **Cookbook-only (spec §9 Q2).** The policy is descriptive metadata: it
 *   describes intent for an external executor (cron, CLI tool). The framework
 *   does NOT auto-prune on save (FR-039).
 *
 * ## Why a separate class
 *
 * The M-006 single-axis {@see \Waaseyaa\EntityStorage\RevisionPruningPolicy}
 * exposes a flat `?int $keepLastN`; widening that field to `int|array<string,int>`
 * would break the M-006 public surface. The two-axis policy is a NEW class on
 * the stable surface (per WP08 surface-map sweep), additive to the M-006 one.
 */
final class RevisionPruningPolicy
{
    /**
     * Sentinel key for the "default" (catch-all) per-language keep-count.
     *
     * When the per-langcode map omits a langcode key and this sentinel is
     * present, the policy applies the sentinel's value to that langcode.
     * When neither the langcode key nor the sentinel is present, the langcode
     * is exempt from pruning (most permissive).
     */
    public const DEFAULT_LANGCODE_KEY = '*';

    /**
     * @param int|array<int|string, mixed>|null $keepLastN  Either a single int
     *     (applies to every langcode) or a `langcode => int` map. `null` means
     *     no keep-count constraint — every revision is retained for every
     *     langcode (no-op default per FR-039). Runtime validators reject any
     *     deviation from the documented `array<non-empty-string, int>` shape.
     * @param \DateTimeImmutable|null $keepNewerThan       Keep revisions newer
     *     than this date across all langcodes. `null` = no cutoff.
     * @param string[] $keepByAuthorRole                   Keep all revisions
     *     authored by users with any of these role strings. Role semantics are
     *     application-defined.
     */
    public function __construct(
        public readonly int|array|null $keepLastN = null,
        public readonly ?\DateTimeImmutable $keepNewerThan = null,
        public readonly array $keepByAuthorRole = [],
    ) {
        if (\is_array($keepLastN)) {
            foreach ($keepLastN as $langcode => $count) {
                if (!\is_string($langcode) || $langcode === '') {
                    throw new \InvalidArgumentException(
                        'RevisionPruningPolicy: per-langcode keep-count map keys MUST be non-empty strings.',
                    );
                }
                if (!\is_int($count) || $count < 0) {
                    throw new \InvalidArgumentException(
                        'RevisionPruningPolicy: per-langcode keep-count values MUST be non-negative integers.',
                    );
                }
            }
        } elseif (\is_int($keepLastN) && $keepLastN < 0) {
            throw new \InvalidArgumentException(
                'RevisionPruningPolicy: keepLastN MUST be a non-negative integer when provided as a scalar.',
            );
        }
    }

    /**
     * Build the default no-op policy (FR-039).
     *
     * The framework MUST treat this policy as "retain every revision of every
     * langcode forever." External executors consuming this policy MUST emit
     * zero deletes when this instance is returned.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Build a uniform-keep-count policy.
     *
     * Every langcode keeps its last `$n` revisions; older revisions are
     * candidates (subject to {@see candidateExcluded()} — the current revision
     * is never deletable per FR-038).
     */
    public static function keepLastUniform(int $n): self
    {
        if ($n < 0) {
            throw new \InvalidArgumentException(
                'RevisionPruningPolicy::keepLastUniform requires a non-negative integer.',
            );
        }

        return new self(keepLastN: $n);
    }

    /**
     * Build a per-language keep-count policy.
     *
     * @param array<int|string, mixed> $perLangcode Map of langcode -> integer
     *     keep-count. Runtime validators reject any deviation from the documented
     *     `array<non-empty-string, int>` shape.
     *
     * Langcodes absent from the map are exempt from pruning (most permissive)
     * unless the caller supplies a wildcard via {@see self::DEFAULT_LANGCODE_KEY}
     * key — that wildcard then applies to every langcode not explicitly listed.
     */
    public static function keepLastPerLangcode(array $perLangcode): self
    {
        return new self(keepLastN: $perLangcode);
    }

    /**
     * Resolve the keep-count for a specific langcode.
     *
     * - Returns `null` when the policy imposes no keep-count for that langcode
     *   (no map entry, no wildcard) — executors MUST treat `null` as "keep all
     *   revisions of this langcode."
     * - Returns the scalar value when `keepLastN` was supplied as a flat int.
     * - Returns the per-langcode value when present in the map, otherwise the
     *   wildcard {@see self::DEFAULT_LANGCODE_KEY} value.
     *
     * @api
     */
    public function keepLastNFor(string $langcode): ?int
    {
        if ($this->keepLastN === null) {
            return null;
        }
        if (\is_int($this->keepLastN)) {
            return $this->keepLastN;
        }
        if (isset($this->keepLastN[$langcode])) {
            return $this->keepLastN[$langcode];
        }
        if (isset($this->keepLastN[self::DEFAULT_LANGCODE_KEY])) {
            return $this->keepLastN[self::DEFAULT_LANGCODE_KEY];
        }

        return null;
    }

    /**
     * Whether the policy is a no-op (no constraints at all).
     *
     * Mirrors FR-039 — a default-constructed policy emits zero deletes.
     *
     * @api
     */
    public function isNoOp(): bool
    {
        return $this->keepLastN === null
            && $this->keepNewerThan === null
            && $this->keepByAuthorRole === [];
    }

    /**
     * FR-038 invariant guard: the current revision of any langcode MUST NEVER
     * be a deletion candidate.
     *
     * Executors call this before issuing each DELETE; returning `true` means
     * the candidate MUST be skipped regardless of other policy rules.
     *
     * @param int $candidateRevisionId        Revision being considered for deletion.
     * @param int $currentRevisionForLangcode The per-`(entity, langcode)` current revision id.
     *
     * @api
     */
    public function candidateExcluded(int $candidateRevisionId, int $currentRevisionForLangcode): bool
    {
        return $candidateRevisionId === $currentRevisionForLangcode;
    }
}
