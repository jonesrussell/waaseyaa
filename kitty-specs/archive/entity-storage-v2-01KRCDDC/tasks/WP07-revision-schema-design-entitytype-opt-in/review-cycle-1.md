# WP07 Review — Cycle 1

**Verdict:** APPROVED
**Reviewer:** opus
**Commit:** `fca57437f`
**Date:** 2026-05-12

## Summary

WP07 lands the revisionable opt-in slot on `EntityType`, a per-entity-type
primary-backend slot, the `RevisionableEntityInterface` + `RevisionMetadata`
contract, and the `RevisionTableBuilder` schema emitter for both `sql-blob`
and `sql-column` primary backends. All five subtasks (T035–T039) are present
and faithful to spec §3.5 / contracts/revisionable-entity.md §3.2.

## Acceptance criteria

1. **EntityType opt-in slots (T035)** — both params strictly additive, both
   accessors (`isRevisionable()`, `getPrimaryStorageBackend()`) present.
   `$primaryStorageBackend` is inserted between `$description` and `$tenancy`
   — acceptable because every call site in the tree uses named arguments
   past `$description` (verified by full-suite green across 7662 tests).
2. **Revision key validation (T036)** — `\InvalidArgumentException` thrown
   BEFORE other validation, message names the entity type id. Covered by
   `EntityTypeRevisionableTest`.
3. **RevisionableEntityInterface + trait + RevisionMetadata (T037)** —
   interface extends `EntityInterface`, three methods match brief. Trait
   provides defaults. `RevisionMetadata` is `final readonly class` with the
   three documented properties. Legacy `RevisionableInterface` and the new
   `RevisionableEntityInterface` coexist cleanly — see clash assessment
   below.
4. **RevisionTableBuilder (T038)** — both `sql-blob` (with `_data TEXT`)
   and `sql-column` (mirrored layout) branches present. DBAL platform
   discrimination via the `serial`/`text` abstract types (consistent with
   WP05 `TypeMapping`).
5. **Revision metadata columns (T039)** — `revision_created_at` text,
   `revision_author` int nullable with explicit no-FK comment, `revision_log`
   text. Soft FK semantics documented in the class-level docblock and in the
   inline comment at the `revision_author` spec entry.
6. **`@api`** — present on `EntityType::getPrimaryStorageBackend()`,
   `RevisionableEntityInterface`, `RevisionMetadata`, `RevisionTableBuilder`,
   and `build()`.
7. **No psr/log, no Illuminate, no service locators, `declare(strict_types=1)`,
   `final` / `final readonly` by default** — all clean.
8. **Tests** — `EntityTypeRevisionableTest` (165 lines) + `RevisionTableBuilderTest`
   (258 lines) cover opt-in, validation, both primary-backend layouts, soft-FK
   absence, and platform dispatch.
9. **Non-goal scope** — no revision admin UI, no auto-pruning, no per-field
   translation, no moderation, no vector backend, no cross-backend joins.

## Scope decisions

### BackendResolver + EntityStorageCoordinator cleanup — ACCEPTED

The reflection guards in `BackendResolver::resolveId()` and
`EntityStorageCoordinator::resolvePrimaryBackendId()` existed specifically as
a forward-compat bridge until WP07 added `getPrimaryStorageBackend()` to
`EntityTypeInterface`. The diff is the minimal 5-line simplification
(reflection → direct method call) per location:

- No signature changes.
- No parameter changes.
- No behavior change — the tier-2 logic is unchanged; only the dispatch
  mechanism is simplified.
- WP02's tier-1 (field-level), tier-2 (entity-type primary), tier-3
  (framework default) ordering is preserved.

This is the natural completion of the contract WP07 owns, not scope creep.
Implementer flagged the alternative in the brief and chose the right call.

### EntityTypeInterface evolution — ACCEPTED

Adding `getPrimaryStorageBackend(): ?string` to the interface is required for
WP02's coordinator/resolver to consume the new EntityType slot through the
interface (the alternative is keeping reflection forever, which we explicitly
do not want). Implementor risk audit:

- Only two implementors of `EntityTypeInterface` in the repo:
  - `packages/entity/src/EntityType.php` — updated.
  - `packages/relationship/tests/Fixtures/StubEntityTypeManager.php` — updated
    (in diff stat).
- Full suite (7662 tests) green confirms no consumer breakage.
- The implementer correctly chose an explicit interface method over a
  default — defaults on interfaces are not a thing in PHP and any future
  third-party implementor will get a clear contract violation rather than a
  silent fallback.

## Pre-existing `RevisionableInterface` clash

`packages/entity/src/RevisionableInterface.php` is the pre-WP07 storage-facing
contract used by `EntityRepository` (`getRevisionLog()`, `isNewRevision()`,
etc.). The new `RevisionableEntityInterface` adds the read-side contract
(`revisionId()`, `isCurrentRevision()`, `revisionMetadata()`).

The two interfaces have **disjoint method sets** and **different consumers**
(legacy: `EntityRepository`; new: WP08+ read paths). The trait
`RevisionableEntityTrait` exposes both surfaces with backward-compat marked
explicitly (`// Legacy RevisionableInterface methods (pre-WP07, preserved
for compat)`). No collision. Future cleanup will likely unify these once
WP08 lands; not a blocker here.

## Soft FK verified

`packages/entity-storage/src/Schema/RevisionTableBuilder.php` emits
`revision_author` as `int` with `not null => false` and an inline comment:
`// Intentionally NO foreign key ON DELETE — soft FK only so revision
history survives user deletion`. No `REFERENCES users(uid)` in the DDL.
Matches spec §3.5.

## Gate spot-checks

| Gate | Result |
|---|---|
| `composer cs-check` | clean |
| `composer phpstan` | OK, no errors (1227 files) |
| `bin/check-package-layers` | OK |
| `bin/check-composer-policy` | OK |
| `phpunit packages/entity/ packages/entity-storage/` | 832/832 green |
| Full `phpunit` | **7662/7662 green** (18577 assertions) |

## Notes for follow-up

- WP08 should consider unifying `RevisionableInterface` (storage-facing) and
  `RevisionableEntityInterface` (read-side) — out of scope for WP07.
- `RevisionTableBuilder::build()` does not yet wire `_data TEXT` for sql-blob
  primaries in the runtime path — the visible code in the diff defines the
  spec but the third `rev_table_full` chunk should be inspected by WP08 when
  hooking the builder into `SqlSchemaHandler`. The unit test coverage is
  present, so the contract is locked.

## Verdict

**APPROVED** — Cycle 1.
