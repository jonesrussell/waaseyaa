---
affected_files: []
cycle_number: 2
mission_slug: entity-storage-v2-01KRCDDC
reproduction_command:
reviewed_at: '2026-05-12T02:02:46Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP04
---

# WP04 Review — Cycle 1

**Verdict:** REJECTED (narrow, single change request)
**Reviewer:** Opus 4.7
**Date:** 2026-05-11
**Commit reviewed:** `581a68421`
**Mission:** entity-storage-v2-01KRCDDC

---

## Summary

WP04 delivers a clean, well-tested implementation of T018–T024. The
`CoordinatorLifecycleDispatcher` correctly enforces every normative dispatch
point in `contracts/lifecycle-events.md`, the partial-save partitioning is
correct, and the critical gate — **`AfterSaveEvent`/`AfterDeleteEvent` MUST NOT
fire on partial failure** — is implemented correctly AND actively asserted by
`after_save_does_not_fire_on_partial_failure` using a counter flag (not just an
exception-thrown assertion). All 397 package tests pass; phpstan, cs-check,
package-layers, composer-policy are green.

The single reason for rejection is a contract conformance issue on the
`PartialSaveException` property name. The fix is small and may be either a
rename or a contract amendment in the same commit — see CR-01 below.

---

## Acceptance criteria checklist

| # | Criterion | Status |
|---|---|---|
| 1 | 5 event files under `Event/`, `final`, `@api`, signatures match contract; `BeforeSaveEvent`/`AfterSaveEvent` expose `saveContext()` + `isNewRevision()` | PASS |
| 2 | `AbortOperationException` extends `\RuntimeException`, public readonly `$reason`/`$subscriberFqcn` | PASS |
| 3 | `PartialSaveException` per contract — `$entity`, `$causedBy`, `$committedBackends`, `$uncommittedBackends`, **`$code = 'PARTIAL_SAVE'`** | **FAIL — CR-01** (property renamed to `$errorCode`; contract is normative on the literal name `$code`) |
| 4 | `SaveContext` value object — private ctor, static `default()`, immutable `withoutNewRevision()`; single property | PASS |
| 5 | `CoordinatorLifecycleDispatcher` — dispatch order, abort halts and propagates, AfterSave only after success, symmetric for delete, null dispatcher = no-op | PASS |
| 6 | Partial-save semantics — committed/uncommitted partitioning correct; AfterSave/AfterDelete MUST NOT fire (test counter asserts); structured log on `entity.lifecycle` with `outcome=partial_save` | PASS |
| 7 | WP02 dispatcher slot wired — `@phpstan-ignore property.onlyWritten` removed; dispatcher forwarded to helper at construction; WP02 constructor signature unchanged (last-position nullable `?EventDispatcherInterface`) | PASS |
| 8 | Fan-out order preserved (primary first, alternates in registration order); committed list reflects this order | PASS |
| 9 | `UnknownBackendException` re-thrown directly (resolve-time, pre-write) — covered indirectly by WP02 tests; new helper has explicit `catch (UnknownBackendException $e) { throw $e; }` before the partial-save catch | PASS (see Observation-1) |
| 10 | Tests — order, abort-halts, AfterSave-not-on-partial, committed/uncommitted partition, structured log line | PASS |
| 11 | Namespace `Waaseyaa\EntityStorage` | PASS |
| 12 | Layer rule L1, no upward imports | PASS (`bin/check-package-layers` green) |
| 13 | `@api` on every new public symbol | PASS |
| 14 | No `psr/log`, no `Illuminate\*`, no service locators, `declare(strict_types=1)`, `final class` | PASS |
| 15 | No §1.2 / §2.2 non-goals introduced | PASS |

---

## Change requests

### CR-01 (BLOCKING) — `PartialSaveException::$code` contract conformance

**Where:** `packages/entity-storage/src/Exception/PartialSaveException.php`

**What the contract says:**

- `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` §6.5 normative payload writes the property declaration verbatim:
  ```php
  public readonly string $code = 'PARTIAL_SAVE',
  ```
- `kitty-specs/entity-storage-v2-01KRCDDC/tasks/WP04-…/spec.md` T020 repeats: "Public readonly `$entity`, `$causedBy`, `$committedBackends`, `$uncommittedBackends`, `$code = 'PARTIAL_SAVE'`."
- `contracts/partial-save-error.md` — the exception-class block is normative on the property set.

**What was implemented:** Renamed to `public readonly string $errorCode = 'PARTIAL_SAVE'`. Commit message and PHPDoc justify the rename as avoiding a collision with `\Exception::$code` (which is `int`). The technical rationale is real and sound — promoting a `string` property named `$code` on a subclass of `\Exception` is a property-type conflict.

**Why this blocks:** The contract is the single source of truth callers will code against. A consumer reading `spec.md` §6.5 or `contracts/partial-save-error.md` will write `$e->code` and get a runtime `Undefined property` error (or worse, read the inherited int `0` from `\Exception::$code`).

**Acceptable resolutions (pick one):**

1. **Update the contract + spec in this WP commit.** Edit:
   - `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` §6.5 — change the `public readonly string $code` line to `public readonly string $errorCode` and add a one-sentence note: "`$errorCode` (renamed from `$code` to avoid a property-type collision with `\Exception::$code`, which is `int`)."
   - `kitty-specs/entity-storage-v2-01KRCDDC/contracts/partial-save-error.md` — same edit.
   - Keep the implementation as-is.

2. **Rename the property back to `$code`** via a different inheritance strategy — e.g. do not extend `\RuntimeException` directly; extend a base shape that does not carry `\Exception::$code` (impractical in PHP without sidestepping `\Throwable`), OR drop constructor promotion and override `$code` with a stringly-typed property using `new #[\Override]` semantics (still a type clash). In practice option 1 is the only clean path.

**Recommended:** Resolution 1. The implementer's technical reasoning is correct; the only gap is that the contract drift was not recorded in the same commit.

---

## Non-blocking observations

**Observation-1 — `UnknownBackendException` regression test.** The helper's `catch (UnknownBackendException $e) { throw $e; }` is correctly placed before the `\Throwable` catch, so an unknown backend at fan-out time will propagate as a config error rather than be wrapped as a partial save. There is no dedicated test inside `LifecycleDispatchTest`/`PartialSaveTest` asserting this re-throw shape. WP02's coordinator tests cover the resolve-time path. Optional: add one test in WP04 asserting `UnknownBackendException` is not wrapped when the failing backend id is the missing one. Not blocking.

**Observation-2 — `SaveContext` cardinality.** Currently a single flag (`withoutNewRevision`). The class is designed to extend; this matches Q6 (value object, not flags array). No action.

**Observation-3 — Structured log payload field naming.** The contract lists fields `event_class, entity_type_id, entity_id, save_context, duration_ms, outcome`. The implementation emits `event, outcome, entity_type_id, entity_id, duration_ms` (and `committed_backends`, `uncommitted_backends`, `cause_class`, `cause_message` for partial-save). The contract field `event_class` is rendered as the human-readable `event` short name (`save`, `delete`) rather than an FQCN; `save_context` is not currently emitted. Both deviations are minor and pragmatic, but should be reconciled — either update the contract to match the implementation, or add `save_context` serialization and rename `event` → `event_class` with the FQCN value. Not blocking; track as a small follow-up in WP05 or a docs cycle.

---

## Gates spot-check

| Gate | Result |
|---|---|
| `composer phpstan` | OK — no errors (1218 files) |
| `composer cs-check` | OK — no diffs |
| `bin/check-package-layers` | OK |
| `bin/check-composer-policy` | OK |
| `./vendor/bin/phpunit packages/entity-storage/tests/Integration/Events/` | 13/13 pass, 46 assertions |
| `./vendor/bin/phpunit packages/entity-storage/tests/` | 397/397 pass, 883 assertions |

---

## Critical-things assessment

- **AfterSave-on-partial-failure gate:** Implementation correctly throws `PartialSaveException` from inside the `try`/`catch (\Throwable)` block and the `AfterSaveEvent` dispatch is placed **after** the `try` block exits normally — so the early throw guarantees no AfterSave fires. Symmetric for delete. The test `after_save_does_not_fire_on_partial_failure` registers a real listener that flips a boolean and asserts `assertFalse($afterSaveFired)` after catching the exception. This is a real gate, not an artefact assertion. PASS.

- **`$code` vs `$errorCode`:** See CR-01. Deviation from a normative contract; the technical justification is valid but unrecorded.

---

## Next actions

1. Implementer chooses Resolution 1 (contract + spec update) — recommended — and commits to the lane branch as a follow-up to `581a68421` resolving CR-01.
2. Re-submit for cycle 2 review.

— end review-cycle-1.md —
