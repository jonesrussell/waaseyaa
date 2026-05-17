# Specification Quality Checklist: Listing Pipeline v1

**Purpose:** Validate specification completeness and quality before proceeding to `/spec-kitty.plan`.
**Created:** 2026-05-15
**Mission ID:** M-007 (display) / `01KRMN0B4FWX9PK80RPSYDX1QM` (Spec Kitty)
**Spec:** [`../spec.md`](../spec.md)
**Doctrine spec:** [`../../../docs/specs/listing-pipeline-v1.md`](../../../docs/specs/listing-pipeline-v1.md)
**Validation iteration:** 1 of max 3

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
  - PASS with caveat (same shape as M-006): the spec is a framework-internal mission, so PHP class/method names appear (`ListingDefinition`, `TaggedCacheInterface`, `UnsupportedListingException`). Per project convention (M-001..M-006 follow the same pattern), the names are stable-surface contracts that downstream consumers depend on, not implementation choices.
- [x] Focused on user value and business needs
  - §0 ties the work directly to the Drupal-comparison-matrix gap ("listings are 60–80% of pages on community CMSs; this is the largest mission-completeness gap"). Each FR maps to a stable-surface deliverable or operational outcome listed in §6 / §10.
- [x] Written for the spec's stated audience (framework maintainers + `@jonesrussell`)
  - PASS. Business stakeholders would consume the ADR-015 narrative + the eventual cookbook recipe; this spec layer is rigor for the maintainer audience.
- [x] All mandatory sections completed
  - §0 Origin, §1 Goals/non-goals, §2 Scope, §3 FRs, §4 NFRs, §5 Constraints, §6 Stable surface, §7 Behavior specs (normative), §8 Test surface, §9 WP decomposition, §10 Acceptance criteria, §11 Open questions, §12 References. Present.

## Requirement Completeness

- [x] No `[NEEDS CLARIFICATION]` markers remain
  - Eight items live in §11 Open questions, each with a `Recommend:` resolution that the plan phase will ratify. No `[NEEDS CLARIFICATION]` markers in the spec body.
- [x] Requirements are testable and unambiguous
  - Each FR uses MUST / SHOULD / MAY per RFC 2119. Each NFR has a measurable threshold (NFR-001 <1ms p95, NFR-003 <0.5ms p95, NFR-004 zero-new-warnings). Each constraint is a hard "MUST NOT" boundary.
- [x] Requirement types are separated (Functional / Non-Functional / Constraints)
  - §3 (FR-001..FR-063), §4 (NFR-001..NFR-005), §5 (C-001..C-006) live in distinct sections.
- [x] IDs are unique across FR-###, NFR-###, and C-### entries
  - FR-001..FR-063 (no gaps, no duplicates verified by reading sequence). NFR-001..NFR-005. C-001..C-006. Cross-namespace prefixes prevent collision.
- [x] Internal consistency between FR-031 (totalRows MUST be exact) and NFR-002 (approximateTotal opt-in escape hatch)
  - Tightened on 2026-05-15 self-review: FR-031 now references NFR-002 explicitly so the contract reads as "exact unless approximateTotal is declared".
- [x] Cache miss / context-unknown semantics specified (FR-035, FR-058)
  - Tightened on 2026-05-15 self-review: FR-035 now states explicitly that unknown contexts bypass the cache for that resolution (resolve happens, store does not) rather than silently degrading. FR-058 covers cache-backend errors (caught + logged + continue without caching).
- [x] Non-functional requirements include measurable thresholds
  - NFR-001 (<1 ms p95 per row access overhead, 50 ms / 50-row target), NFR-003 (<0.5 ms p95 cache hit), NFR-004 (zero new PHPStan/PHPUnit warnings), NFR-005 (reference fixture resolves on both backends).
- [x] Acceptance criteria are measurable
  - §10 enumerates 7 numbered gates, each verifiable (FR traceability, NFR sentinels, reference fixture green, M-004 stamp updated, charter amendments filed, gates green, BLOCKED line removed from M-004).
- [x] Acceptance criteria are technology-agnostic for outcome-level checks
  - §10 mixes outcome-level ("M-004 stamps updated", "test suite green") with surface-level ("Charter §3.2 criterion 10 filed"). Consistent with M-006's mixed style.
- [x] All acceptance scenarios are pinned to tests
  - §8 enumerates 19 contract tests + 3 backend conformance suites + 3 integration tests. §10 #1 requires FR→test traceability matrix (to be authored as part of WP12 documentation).

## Mission Sizing

- [x] WP count is reasonable for the mission
  - 12 WPs (vs M-006: 14, M-002: 12). Comparable. Parallelizable lanes documented in §9.1.
- [x] Each WP has a clear owned-file boundary candidate
  - Yes — WP01 `packages/listing/src/{Definition,Filter,Sort,Result,Pagination,Operator}.php`; WP03 `packages/cache/src/TaggedCacheInterface.php` + `MemoryBackend.php` additions; etc. Final owned_files declarations will be set by `/spec-kitty.plan`.
- [x] Cross-WP dependencies traced
  - §9 dependency table is explicit. WP12 is the closer; WP10 needs WP07; WP05 needs WP01 + WP02; etc.

## Cross-Mission Linkage

- [x] M-006 C-002 obligation acknowledged
  - §3.12 FR-046..FR-049 carry the langcode-aware filter/tag deliverables explicitly. mission.json `external_dependencies` lists M-006 as hard-prerequisite.
- [x] M-004's PARTIALLY UNBLOCKED stamp will downgrade fully when this mission ships
  - mission.json `downstream_unblocks` calls this out; both M-004 stamp files (kitty-specs + doctrine) are updated in the same commit to reference M-007 by slug.
- [x] Charter amendments scoped
  - §3.2 criterion 10 (new) + §5.X (listing surface, new) + §5.Y (cache tag-aware ops + context registry, new). All three land in WP12 of this mission.
- [x] Beta-gate addition agreed with ADR 015
  - ADR 015 §Consequences: *"Charter §3.2 beta entry criteria should add: 'ListingDefinition contract is stable and at least one consumer app uses it for production listings.'"* This spec adopts that wording for criterion 10.

## Filing Readiness

- [x] mission.json author at `docs/specs/missions/M-007-listing-pipeline-v1/mission.json` is complete
- [x] manifest README updated at `docs/specs/missions/README.md`
- [x] M-004 BLOCKED stamps (kitty-specs + doctrine) updated to reference M-007 by slug
- [x] meta.json author at this mission directory has full source_description + mission_number
- [x] Spec Kitty bootstrap commits (3a2d1a47d + f97e506bd) merged into the filing commit's set of files
- [ ] Charter §3.2 amendment authored — **deferred to WP12** of the implementation mission, per M-006 / M-002 precedent
- [ ] Charter §5.X + §5.Y sections authored — **deferred to WP12** of the implementation mission

## Outstanding work for `/spec-kitty.plan`

The plan phase will resolve the seven §11 open questions and produce:
- Per-WP `tasks/WP##.md` files with owned_files, contract suite case-IDs, and review evidence templates.
- `plan.md` with cross-WP dependency graph, lane-A / lane-B assignments, and parallelism strategy.
- Charter amendment drafts ready for WP12 review.

This checklist treats the spec as filing-ready. The seven §11 open questions are explicitly NOT blocking filing — they are the right level of decision for the plan phase.
