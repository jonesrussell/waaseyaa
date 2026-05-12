# Mission Review Report — entity-storage-v2-01KRCDDC

**Mission:** M-001 — Multi-Backend Storage with Revisions
**Merge commit:** `509e31fb7` (squash to `main`)
**Reviewer:** Opus 4.7 (post-merge adversarial audit)
**Date:** 2026-05-12
**Verdict:** **PASS WITH NOTES**

---

## 1. Summary

All 12 WPs are merged; the multi-backend coordinator, sql-blob/sql-column backends, revision plumbing, lifecycle events, query validator, migration helper, conformance suite, and upgrade guide all landed. Layer rules hold (`bin/check-package-layers` green). The full suite reports 7693 tests / 18682 assertions passing.

Two high-severity findings are documented below. Neither blocks the verdict, but each requires a follow-up WP/issue:

- **H-01** — `DefinitionValidator::validateAll()` is not invoked from any production boot path. FR-021's fail-fast guarantee is aspirational, not enforced.
- **H-02** — `spec.md §6.5` (line 317) and `contracts/partial-save-error.md` (line 33) still declare `public readonly string $code = 'PARTIAL_SAVE'`. The cycle-3 reconciliation reached the class, the upgrade guide, and the public-surface-map, but the two normative source documents were not updated. Callers reading the spec or contract will write `$e->code` and hit `Undefined property` (or read the inherited `\Exception::$code` int).

Everything else (cross-WP edits, byte-identity gate, deferral discipline, autoload-dev placement, layer rules, BC notes) is in good shape.

---

## 2. FR Coverage Matrix

56 FRs in spec §3. Coverage is recorded in the acceptance checklist (criterion 2) and verified by the proxy "full suite green." Below is the adequacy assessment — not the coverage status.

| FR group | Adequacy | Notes |
|---|---|---|
| FR-001 – FR-007 (backend registration) | OK | `BackendRegistrar`, `BackendResolver`, `UnknownBackendException` all present and tested. |
| FR-008 – FR-010 (sql-blob refactor + behavior identity) | OK | Byte-identity gate is structurally correct: `assertSame` on raw bytes, baseline pinned to literals (`'[]'`, escaped Unicode). Cannot drift to match a buggy refactor. |
| FR-011 – FR-016 (sql-column) | OK | Backend, schema builder, query translator, type mapping all shipped; conformance suite green. |
| FR-017 – FR-020 (lifecycle events, coordinator) | OK | `BeforeSaveEvent`, `AfterSaveEvent`, abort/partial-save semantics tested. `SaveContext::withoutNewRevision` correctly gated on `entityType->isRevisionable()` at coordinator line 127. |
| **FR-021** (DefinitionValidator UnsupportedQueryException at boot) | **WEAK — see H-01** | Class exists, unit tests pass, but no production caller invokes `validateAll()`. The fail-fast contract is unenforced. |
| FR-022 – FR-045 (revisions: interface, trait, metadata, table builder, sql-blob/sql-column revision storage, pruner) | OK | All shipped; pruner is disabled by default per ADR 016 (a non-goal). |
| FR-046 (UnsupportedListingException) | OK | |
| FR-047 – FR-048 (migration CLI + backfill) | OK | `MakeStorageMigrationHandler`, `BackfillHelper` present and unit-tested. |
| FR-049 – FR-052 (conformance + integration tests) | OK | `FieldStorageBackendContractTestCase` lives at `testing/Contract/` and is mapped under `autoload-dev` only — production-boot gotcha observed. 5 inherited test methods × 2 backends = 10 green. |
| FR-053 (entity-system.md updated) | OK | "Field storage backends" section appended. |
| FR-054 (field-storage-backends.md new) | OK | ~400 lines. |
| FR-055 (upgrade-guide template) | OK | Template captured inline in WP10/WP11 task spec. |
| FR-056 (first concrete upgrade guide) | OK | `docs/upgrades/waaseyaa-alpha-X-to-Y.md` exists at 20,232 bytes. |

---

## 3. §14 Acceptance Criteria — criterion-by-criterion

| # | Criterion | Status | Notes |
|---|---|---|---|
| 1 | All 12 WPs merged | **PASS** | Confirmed in `wps.yaml` and `git log`; mission squash-merged at `509e31fb7`. |
| 2 | All §3 FRs covered by tests | **PASS** (with H-01 caveat) | 56 FRs covered per acceptance checklist; FR-021 coverage is unit-level only, not end-to-end through boot — see H-01. |
| 3 | Conformance suite green for sql-blob and sql-column | **PASS** | 5 inherited tests × 2 backends, full suite 7693 green. |
| 4 | WP11 Minoo migration 7 days in prod no incident | **DEFERRED** (acknowledged) | `validation/pending-minoo-cycle.md` documents the operator-side T057–T060 exit criteria. The deferral is explicit, not silent. Mission spec §14 criterion 4 is NOT re-defined — it remains an open obligation owed by the Minoo cycle. |
| 5 | Charter §3.2 criterion 8 ("revisions in production") satisfiable | **PASS** (satisfiable, not satisfied) | The framework now supports revisionable entity types; satisfaction requires the same Minoo cycle as criterion 4. |
| 6 | Charter §5.3 stable-surface entries reflected in `public-surface-map.{md,php}` with `stable` + `present` | **PASS** | `PartialSaveException`, `SqlBlobBackend`, `SqlColumnBackend`, `SqlColumnSchemaBuilder`, `SqlColumnQueryTranslator`, `RevisionableEntityStorageInterface` all present. |
| 7 | First concrete upgrade guide exists | **PASS** | `docs/upgrades/waaseyaa-alpha-X-to-Y.md` (~20 KB). |

Five PASS, one DEFERRED (with documented exit criteria), one satisfiable-pending-Minoo. No silent re-definitions.

---

## 4. Drift Findings

### H-02 — Spec/contract still show `$code` despite cycle-3 reconciliation [HIGH, doc drift]

- `kitty-specs/entity-storage-v2-01KRCDDC/spec.md:317` declares `public readonly string $code = 'PARTIAL_SAVE',` inside the §6.5 normative payload.
- `kitty-specs/entity-storage-v2-01KRCDDC/contracts/partial-save-error.md:33` declares the same.
- The class (`packages/entity-storage/src/Exception/PartialSaveException.php:46`) uses `public readonly string $errorCode`.
- `docs/upgrades/waaseyaa-alpha-X-to-Y.md` and `docs/public-surface-map.{md,php}` use `$errorCode`.

The WP04 cycle-3 commit (`4960418de`) claims to have updated "all three places (spec, contract, class docblock)"; in fact only the class docblock was updated. The two source documents inside `kitty-specs/` were missed. This is a drift between the agreed contract and the documents people will consult as the contract. Consumers reading the spec/contract will write `$e->code` and either get `Undefined property` or silently read the inherited `\Exception::$code` int.

**Severity:** High (consumers will write incorrect code from the canonical normative source).
**Disposition:** Open a focused doc-fix issue. No code change required.

### D-01 — None of the locked decisions in `spec.md` §5 / `research.md` §2 appear violated.

Verified spot-check: backend ids (`sql-blob`, `sql-column`), tier-1/2/3 resolution order in `BackendResolver`, "no query-time fallback" stance (FR-021), `_data` retention as rollback safety net — all hold.

### D-02 — Non-goals (§1.2) not invaded.

No moderation workflows, no per-field translation, no admin revision-compare UI, no vector backend, no cross-backend joins, no auto-pruning enabled by default. `RevisionPruner` ships disabled per ADR 016 as required.

---

## 5. Risk Findings

### H-01 — DefinitionValidator has no production caller [HIGH, dead-on-the-vine]

Grep of `packages/**/src/` (excluding tests, testing/) for `DefinitionValidator` and `validateAll`:

```
packages/entity-storage/src/Query/DefinitionValidator.php   (class itself)
packages/entity-storage/src/Exception/UnsupportedQueryException.php  (docblock @see only)
```

There is no kernel boot, service provider, or `BackendRegistrar` site that calls `$validator->validateAll()`. The class is unreachable from any HTTP/CLI boot. FR-021 specifies that boot MUST fail when a registered entity type declares a query the resolved backend cannot support — this is currently impossible to trigger outside of unit tests.

The WP06 cycle-1 review accepted the WP with this gap explicitly noted as "deferred to a follow-up WP." That deferral was never converted into a follow-up artifact.

**Severity:** High (a documented fail-fast contract that does not fail-fast in production is worse than no contract — it gives false confidence).
**Disposition:** Open a follow-up WP/issue to wire `DefinitionValidator::validateAll()` into `AbstractKernel::boot()` (after `BackendRegistrar::build()` runs, before `discoverAndRegisterProviders()` returns).

### R-01 — Cross-WP edits — all verified clean

| WP | Touched file owned by | Diff scope | Verdict |
|---|---|---|---|
| WP07 | `BackendResolver` + `EntityStorageCoordinator` (WP02) | ~5 lines each location: reflection guard → direct `getPrimaryStorageBackend()` call | OK — natural completion of WP07's contract; no signature change. `BackendResolver.php:76-78` and `EntityStorageCoordinator.php:195-197` confirmed clean. |
| WP08 | `EntityStorageCoordinator` (WP02) | T044: ~12 lines honoring `SaveContext::withoutNewRevision` | OK — gated on `$resolvedContext->withoutNewRevision && $entityType->isRevisionable()` at line 127. Non-revisionable path untouched. |
| WP12 | `SqlColumnBackend::delete()` (WP05) | ~10 lines: was no-op, now issues `DELETE` guarded on `id() === null` | OK — narrow, idempotent against coordinator-level row delete, documented in commit and docblock. WP05 tests still pass. |

No cross-WP edit smuggled a signature change or expanded scope.

### R-02 — PolicyAttribute backward compatibility [LOW]

WP09 added an optional `operations` array (default `[]`) to `PolicyAttribute`. Upgrade guide §7 explicitly documents this as a no-op default. Full suite green is the proxy that existing `#[PolicyAttribute(...)]` call sites are unaffected.

### R-03 — Test harness production-boot safety [PASS]

`FieldStorageBackendContractTestCase` lives at `packages/entity-storage/testing/Contract/`. `composer.json` maps `Waaseyaa\\EntityStorage\\Testing\\Contract\\` under **`autoload-dev`** only; `autoload` is scoped to `src/`. A consumer `composer install --no-dev` will not Reflection-load this class. The CLAUDE.md gotcha is correctly observed.

---

## 6. Silent Failure Candidates

| Candidate | Where | Risk | Mitigation in place? |
|---|---|---|---|
| Empty `IN`/`NOT IN` queries return zero results | DBAL pattern, framework-wide (CLAUDE.md) | Not introduced by this mission | N/A |
| `DefinitionValidator` not invoked at boot | `packages/entity-storage/src/Query/DefinitionValidator.php` | **Yes** — FR-021 fail-fast guarantee silently absent | **No** — see H-01 |
| Consumer reads `$e->code` per spec/contract | spec.md:317, contracts/partial-save-error.md:33 | Returns inherited `\Exception::$code` int (0) silently | **No** — see H-02 |
| `SqlColumnBackend::delete()` double-delete | Coordinator deletes the row too | Idempotent: `DELETE` against an already-deleted row is a no-op | Yes — documented in WP12 docblock |
| Non-revisionable entity touched by `withoutNewRevision=true` | EntityStorageCoordinator:127 | None — gated on `isRevisionable()` | Yes |

---

## 7. Security Notes

| Surface | Concern | Disposition |
|---|---|---|
| `SqlColumnQueryTranslator` | SQL injection via field name composition | Goes through DBAL query builder; field names come from `FieldDefinition`, not user input. No raw concatenation. |
| `PartialSaveException` message | Embeds entity type id + entity id in `sprintf` | Acceptable — same surface as existing exception messages. No user-controlled data interpolated. |
| `MakeStorageMigrationHandler` | Generates migration PHP into the project tree | Generation only; no execution. Consumer must run migrations explicitly. |
| `RevisionPruner` | Ships disabled per ADR 016 | OK — operator must opt in. |
| Backend registration | `BackendRegistrar` accepts user-defined backends via attribute scan | Reflection scan is layer-bounded; consumer cannot register backends from a higher layer. |

No new public attack surface introduced. No secrets in defaults.

---

## 8. Open items (non-blocking)

| ID | Item | Type | Suggested follow-up |
|---|---|---|---|
| H-01 | Wire `DefinitionValidator::validateAll()` into `AbstractKernel::boot()` | Code | New WP or GitHub issue. ~20-line delta. Required to make FR-021 honest. |
| H-02 | Update `spec.md` §6.5 (line 317) and `contracts/partial-save-error.md` (line 33) to declare `$errorCode` not `$code`; add the PHP-constraint footnote that already lives in the class docblock | Docs | Single doc-fix PR. No code change. |
| O-01 | Convert `validation/pending-minoo-cycle.md` exit criteria into a tracked Minoo-repo issue with a 7-day clock | Operational | Tracks §14 criterion 4 / criterion 5. |
| O-02 | After H-01 lands, add an integration test that constructs a kernel with a deliberately-misconfigured entity type and asserts boot raises `UnsupportedQueryException` | Test | Locks the fail-fast guarantee. |
| O-03 | After T060 closes, capture any lessons in `docs/upgrades/waaseyaa-alpha-X-to-Y.md` §9 per the deferred-cycle note | Docs | |

---

## 9. Final Verdict

**PASS WITH NOTES.**

The framework deliverable is sound: the multi-backend architecture is in place, the byte-identity gate is structurally honest, cross-WP edits are narrow and documented, layer rules hold, and the conformance suite genuinely caught a contract violation in WP05 (the `SqlColumnBackend::delete()` no-op). Deferral discipline on §14 criterion 4 is explicit — the obligation is not silently redefined, just shifted to the Minoo cycle with documented exit criteria.

Two follow-ups are owed: (a) wire `DefinitionValidator` into production boot so FR-021's fail-fast guarantee is real, and (b) reconcile spec.md §6.5 and contracts/partial-save-error.md with the `$errorCode` naming that already lives in the class, upgrade guide, and public-surface-map.

Neither blocks the mission's release; both should be tracked before the next storage-touching mission begins.
