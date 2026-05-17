# WP12 — Cycle 1 Review

**Verdict:** APPROVED
**Reviewer:** Opus 4.7 (1M context)
**Date:** 2026-05-12
**Commit reviewed:** `0cd487161`
**Mission:** entity-storage-v2-01KRCDDC (M-001) — **FINAL WP**

---

## Acceptance criteria — verification

### 1. `FieldStorageBackendContractTestCase` harness (T063) — PASS

- Located at `packages/entity-storage/testing/Contract/FieldStorageBackendContractTestCase.php` — **NOT** under `src/`. The CLAUDE.md production-boot gotcha is correctly observed.
- `composer.json` mapping is under **`autoload-dev`**:
  ```
  autoload-dev.psr-4: {
    "Waaseyaa\\EntityStorage\\Testing\\Contract\\": "testing/Contract/",
    "Waaseyaa\\EntityStorage\\Tests\\":              "tests/"
  }
  ```
  `autoload.psr-4` remains scoped to `src/` only — consumer production installs will never reach the `TestCase` parent.
- Abstract class declared `abstract class FieldStorageBackendContractTestCase extends TestCase`, decorated with `#[CoversNothing]` and `@api`.
- Seven template methods: `createBackend()`, `prepareFixtureEntity()`, `fixtureField()`, `fixtureValue()`, `alternateValue()`, `supportsQueryField()`, `expectSupportsQuery()` — clean Template Method shape.
- Five inherited `#[Test]` methods cover the documented contract surface: id stability/idempotence, read/write/delete round-trip, idempotent re-write, `supportsQuery()` contract, delete cascade.
- Docblock explicitly documents the placement rule and reproduces the production-boot rationale — future-proofing against accidental moves.

### 2. Two conformance tests (T064) — PASS

- `SqlBlobConformanceTest` and `SqlColumnConformanceTest` extend the harness with `#[CoversNothing]`.
- `./vendor/bin/phpunit packages/entity-storage/tests/Contract/Backend/` — **12/12 green** (6 each = 5 inherited + 1 backend-specific `testIdMatchesReservedConstant`).

### 3. `entity-system.md` extension (T065) — PASS

- 159 net lines added; integrates with existing content, no duplication or contradiction with the storage-driver narrative.

### 4. `field-storage-backends.md` (T066) — PASS

- 280 lines (slightly under the ~400 target — see "Minor observations" below; not blocking). Covers interface contract, registration via `BackendRegistrar`/`IsFrameworkBackendProviderInterface`, idempotency, fail-fast definition-time validation, conformance-harness usage, reserved-id discipline, and reference implementations.

### 5. Upgrade guide canonicalization (T067) — PASS (with note)

- `docs/upgrades/waaseyaa-alpha-X-to-Y.md` is at **514 lines** and includes the §6 partial-save operator runbook and expanded §5 view_revision template, as required by WP12. The WP12 commit does not re-edit this file — the implementer's note explains it was already at 514 lines from WP11, which is what cycle-1 review of WP11 left in place. Verified by reading the live file: the partial-save recovery runbook and view_revision template are present and final.

### 6. public-surface-map updates (T068) — PASS

- `docs/public-surface-map.md`: 33 added lines (one row per symbol; the count matches "34 new entries" once the WP12 harness entry is included — observed at line 168).
- `docs/public-surface-map.php`: 40 `Waaseyaa\EntityStorage\*` keys all set to `'public'`. Confirmed `FieldStorageBackendContractTestCase` is listed at line 211 alongside its FQCN `Waaseyaa\EntityStorage\Testing\Contract\FieldStorageBackendContractTestCase => 'public'`.
- All WP01–WP12 symbols enumerated in the criterion 6 walk-through of the acceptance checklist; spot-checked against my prior review notes for WP01 (`BackendRegistrar`, `IsFrameworkBackendProviderInterface`, `UnsupportedQueryException`), WP04 (lifecycle events + `PartialSaveException`), WP10 (`MakeStorageMigrationHandler`, `BackfillHelper`) — all present.

### 7. Acceptance checklist (T069) — PASS

- Walks spec §14 criteria 1–7. Criterion 4 is **explicitly marked DEFERRED**, linked to `kitty-specs/entity-storage-v2-01KRCDDC/validation/pending-minoo-cycle.md`, with a clear rationale that production validation is an operational gate distinct from the framework merge gate. Not failed, not hand-waved.
- FR-001 through FR-055 each mapped to a covering test (criterion 2 table). Spot-check of FR-005 (`BackendResolverTest::throwsOnUnknown`), FR-019 (`CoordinatorPartialSaveTest`), FR-040-range (WP07–WP09 revisionable suites), FR-048 (`BackfillHelperTest`), and FR-053 (`docs/specs/entity-system.md` §"Field storage backends") all match observed code/tests.
- Summary table reads PASS/PASS/PASS/DEFERRED/PASS/PASS/PASS — exactly the shape required.

### 8. Critical scope check — `SqlColumnBackend::delete()` cross-WP fix — PASS

- The change is genuinely required: the conformance contract explicitly requires `read()` after `delete()` to return null. The prior implementation was a no-op with a comment claiming "the coordinator deletes the row" — which is true at the storage layer, but the **backend-level** contract is what `FieldStorageBackendContractTestCase::testDeleteCascade()` exercises, and a backend that leaves the row in place violates that contract.
- The diff is narrowly scoped: single method, ~10 lines, adds a single `DELETE` statement guarded by `id() === null`. The docblock is updated to document the new behavior and explicitly call out idempotency when the coordinator also issues a row-level DELETE (no double-delete failure).
- The commit message **does** document the cross-WP edit ("Fix 3: SqlColumnBackend::delete() now issues DELETE statement (was no-op); read() after delete() correctly returns null, satisfying the contract"). The fix is auditable.
- WP05's existing tests still pass: full suite `7693, 18682 assertions, 1 PHPUnit warning (no coverage driver), 2 deprecations, 2 skipped`. No `SqlColumnBackend` regressions.

**Assessment:** This is a textbook example of WP12's job working as designed — the conformance suite caught a contract violation in WP05 that the WP05-local tests did not exercise. The fix is minimal, justified, and documented.

### 9. `@api` on new public symbols — PASS

- Harness carries `@api` in its docblock; concrete conformance tests inherit usage status via `ApiPhpDocUsageProvider`.

### 10. Code hygiene — PASS

- `declare(strict_types=1)` in all new files.
- Concrete conformance tests are `final class`; harness is `abstract class` (correct).
- No `psr/log`, no `Illuminate\*`, no service locators.

### 11. Scope discipline — PASS

- No auto-pruning, no admin UI, no mass-migration tooling, no remote/vector backend implementation. Stays inside §1.2/§2.2 boundaries.

---

## Mission-level final pass

- **§14 criterion 2 FR coverage:** Spot-checked FR-005 (BackendResolverTest), FR-019 (CoordinatorPartialSaveTest), FR-021 (DefinitionValidatorTest), FR-047 (MakeStorageMigrationHandlerTest), FR-053 (entity-system.md). All map cleanly.
- **§14 criterion 5 (charter §3.2 criterion 8):** Framework infrastructure — pluggable backends, conformance suite, revisionable storage, lifecycle events, upgrade guide — is complete and satisfiable. The criterion-5 note correctly acknowledges that Minoo adoption is the remaining operational step (which is criterion 4 deferral, not a framework gap).
- **Public-surface consistency across WPs:** Verified `Waaseyaa\EntityStorage\*` namespace (not `Waaseyaa\Entity\Storage`) used uniformly; `$errorCode` (not `$code`) appears in WP04 surface exposures and the upgrade guide.
- **WP04 `$errorCode` story:** Reflected in the upgrade guide (§7 in WP11) and the public-surface-map entries for `PartialSaveException`.

---

## Gate spot-checks

| Gate | Result |
|------|--------|
| `composer dump-autoload` | Clean (autoload-dev change picked up) |
| `composer cs-check` | Clean (`files: []`, no fixes needed) |
| `bin/check-package-layers` | `OK — package layer constraints satisfied` |
| `bin/check-composer-policy` | `OK: Composer policy checks passed` |
| `./vendor/bin/phpunit packages/entity-storage/tests/Contract/Backend/` | 12/12 green |
| Full suite `./vendor/bin/phpunit` | **7693 tests, 18682 assertions, OK** (1 runner warning re no-coverage-driver, 2 deprecations, 2 skipped — pre-existing) |

---

## Minor observations (non-blocking)

1. `docs/specs/field-storage-backends.md` lands at 280 lines vs. the ~400-line target. The content is implementer-complete (contract, registration, idempotency, fail-fast, harness usage, reserved-ids, reference impls) — the lower line count reflects denser prose, not missing topics. No revision required.
2. T067's commit note ("upgrade guide already at 514 lines from WP11; no additional content needed") is accurate but slightly understates that this WP is the canonicalization point — the guide is *final* now. Worth surfacing in the mission-acceptance commit message.

---

## Verdict

**APPROVED — Cycle 1.**

WP12 lands the conformance suite, completes the documentation surface, and produces an acceptance checklist that is honest about criterion 4's deferral. The cross-WP `SqlColumnBackend::delete()` fix is the right kind of cross-WP edit: a bug surfaced by the new conformance contract, narrowly fixed, and clearly documented. Full suite green at 7693 tests.

Mission **entity-storage-v2-01KRCDDC** is ready for acceptance on criteria 1, 2, 3, 5, 6, 7. Criterion 4 properly deferred to the live Minoo cycle.
