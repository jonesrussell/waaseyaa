# WP11 Review — Cycle 1

**Mission:** entity-storage-v2-01KRCDDC (M-001)
**WP:** WP11 — First Minoo entity migration validation + upgrade guide
**Scope:** Reduced by user decision to **T062 only** (upgrade guide). T057–T061
deferred to live Minoo rollout cycle in a separate repo; tracked in
`kitty-specs/entity-storage-v2-01KRCDDC/validation/pending-minoo-cycle.md`.
**Reviewer:** Opus 4.7
**Verdict:** **APPROVED** with one fixed-in-review observation.

---

## Summary

The shippable WP11 deliverable for the Waaseyaa repo is the upgrade guide
(`docs/upgrades/waaseyaa-alpha-X-to-Y.md`), its directory index
(`docs/upgrades/README.md`), and the deferred-task punch list
(`kitty-specs/entity-storage-v2-01KRCDDC/validation/pending-minoo-cycle.md`).
The scope reduction is sanctioned by the user and the framework-side
acceptance signal is the test suite + the upgrade-guide quality. Both pass.

The guide is thorough, runnable, and accurate against the actual code shipped in
WP01–WP10. All signature spot-checks match source. Backwards-compatibility,
rollback, partial-save semantics, and the `view_revision` open-by-default story
are all stated correctly.

## Signature spot-checks (verified against source)

| Claim in guide | Source verified | Status |
|---|---|---|
| `PartialSaveException::$errorCode` (`public readonly string`, default `'PARTIAL_SAVE'`) | `packages/entity-storage/src/Exception/PartialSaveException.php:46` | ✅ exact match |
| `$committedBackends` / `$uncommittedBackends` public readonly arrays | `packages/entity-storage/src/Exception/PartialSaveException.php:44–45` | ✅ exact match |
| `SaveContext::withoutNewRevision()` returns new instance, original unchanged | `packages/entity-storage/src/SaveContext.php:42–44` (`return new self(withoutNewRevision: true)`) | ✅ exact match |
| `GateInterface::VIEW_REVISION = 'view_revision'` | `packages/access/src/Gate/GateInterface.php:34` (`public const string VIEW_REVISION = 'view_revision'`) | ✅ exact match |
| `#[PolicyAttribute(operations: [...])]` boot-time validation throws when `viewRevision()` missing | `packages/access/src/Gate/PolicyAttribute.php:75–84` | ✅ confirmed |
| `RevisionAccessRouter` calls `viewRevision(EntityInterface, AccountInterface, RevisionMetadata)` | `packages/access/src/Gate/RevisionAccessRouter.php:57–61, 95–98` | ✅ exact match |
| `make:storage-migration` exit codes `0/1/2/3/4` | `packages/cli/src/Handler/MakeStorageMigrationHandler.php:22–26, 56–101` | ✅ exact match |
| `RevisionMetadata` exists as readonly value object | `packages/entity/src/RevisionMetadata.php:17` (`final readonly class`) | ✅ confirmed |

No signature drift detected.

## Quality checklist

| Acceptance criterion | Status |
|---|---|
| §1 Stable-surface deltas — comprehensive, accurate, organised by WP | ✅ |
| §1.4 `$errorCode` vs `$code` rationale — clear, references spec §6.5 + contracts file | ✅ |
| §3 sql-blob → sql-column recipe — uses real CLI, includes `--dry-run`, `--force`, exit codes, row-count safety | ✅ |
| §4 Revision opt-in — covers entityKeys['revision'], interface+trait, schema migration, `SaveContext::withoutNewRevision()` example | ✅ |
| §5 `view_revision` policy — full runnable example, correct signatures, **no implicit deny** explicitly stated (§5.2: "open-by-default, no implicit deny") | ✅ |
| §5.3 boot-time `LogicException` for missing `viewRevision()` documented | ✅ |
| §6 Partial-save recovery — uses `$errorCode`, AfterSave-not-fired invariant, idempotent-retry guidance, structured log line on `entity.lifecycle` | ✅ |
| §7 Backwards-compatibility — entity types remain on sql-blob; policies without `operations[]` keep working; `_data` not removed | ✅ |
| §8 Rollback plan — `migrate:rollback`, auto-rollback on `backfill_mismatch`, revisionable reversal steps | ✅ |
| §9 Lessons placeholder labelled as pending, links to `validation/pending-minoo-cycle.md` | ✅ |
| `docs/upgrades/README.md` index — naming convention noted, coverage table present | ✅ |
| `pending-minoo-cycle.md` per-task exit criteria (T057–T061) — concrete checklists, file paths, log channels, dates fields | ✅ |
| Code examples are runnable PHP 8.5+ with `declare(strict_types=1)` namespaces correct (no `psr/log`, no `Illuminate\*`) | ✅ |
| Filename literal `waaseyaa-alpha-X-to-Y.md` uses placeholder, release-cut substitution explained | ✅ |

## Fixed-in-review observation

The lane cleanup commit **88624f135** ("chore: remove planning artifacts from
lane branch") incorrectly classified
`kitty-specs/entity-storage-v2-01KRCDDC/validation/pending-minoo-cycle.md` as a
planning artifact and deleted it from the lane. This file is **not** a planning
artifact — it is referenced by §9 of the shipped upgrade guide as the canonical
home for the deferred T057–T061 exit criteria, and is therefore part of WP11's
durable deliverable.

**Action taken:** restored the file on the mission branch from commit
`778878399` (its original full content, 140 lines, unchanged). The §9 link in
the upgrade guide now resolves.

**Recommendation for future lane hygiene:** scope the
"remove planning artifacts" pattern to `spec-kitty-next-claude-*` and
`spec-kitty-review-*` files at the repo root only; never sweep
`kitty-specs/*/validation/` because anything there is referenced as evidence
material by reviews and consumer-facing docs.

## Deferred-task punch list quality

`pending-minoo-cycle.md` is a real punch list, not a hand-wave. Each of
T057–T061 lists:

- **What** the task is (with a specific command or scope).
- **Exit criteria** as bulleted checkboxes with concrete predicates (e.g.
  "Migration file emitted without error (exit code 0)",
  "`teaching_revision` table is emitted", "No `outcome=partial_save` log
  entries on `entity.lifecycle`").
- **Who closes it** (operator on the live Minoo rollout cycle).
- **Where to log** (`kitty-specs/entity-storage-v2-01KRCDDC/validation/teaching-migration-log.md`).

The 7-day monitoring window has explicit start/end date fields that the
operator fills in.

## Gate results

| Gate | Result |
|---|---|
| `composer cs-check` | ✅ no violations |
| `composer phpstan` | ✅ `[OK] No errors` |
| `bin/check-package-layers` | ✅ OK |
| `bin/check-composer-policy` | ✅ OK |
| `./vendor/bin/phpunit` (full suite) | ✅ **Tests: 7693, Assertions: 18682** — 1 warning, 2 deprecations, 2 skipped (pre-existing; unchanged by this docs-only WP) |

Docs-only WP did not break tests, as expected.

## Verdict

**APPROVED** — Cycle 1.

The upgrade guide is the right deliverable for "framework-side WP11" and is of
shippable quality. The deferred operational work (T057–T061) sits outside the
Waaseyaa repo by design, is sanctioned by the user, and is tracked with
concrete per-task exit criteria in `validation/pending-minoo-cycle.md` so a
future operator on the Minoo cycle can close each task against measurable
evidence.

Move to approved:

```
spec-kitty agent tasks move-task WP11 \
  --to approved \
  --mission entity-storage-v2-01KRCDDC \
  --note "Cycle 1 approved: upgrade guide ships; operational T057–T061 tracked in pending-minoo-cycle.md (restored after errant lane cleanup)."
```
