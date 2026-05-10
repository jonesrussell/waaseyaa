# Tasks: PHP 8.4 Mechanical Modernization

**Mission**: php84-mechanical-modernization-01KR82KT
**Branch contract**: planning base `main` → merge target `main` (matches current branch)

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | array_find swap in `SchemaValidatorTest.php` (8 sites: 81, 103, 124, 144, 166, 186, 208, 230, 250, 275) | WP01 | [P] |
| T002 | array_find swap in `PayloadValidatorTest.php:618` | WP01 | [P] |
| T003 | array_find swap in `ValidationGateValidatorTest.php:80` | WP01 | [P] |
| T004 | array_find swap in `IngestRunCommandTest.php:256` | WP01 | [P] |
| T005 | Verify first-match semantics, then array_find swap in `SemanticRefreshTriggerPlanner.php:415` | WP02 | |
| T006 | Read-only sweep of `packages/routing/src/` and `packages/access/src/` for `foreach { if return }` candidates | WP02 | |
| T007 | Add `#[\Deprecated]` attribute on `FailedJobRepository`, keep docblock | WP03 | [P] |
| T008 | Audit `MigrateDefaultsHandler.php:236`, `FixturePackRefreshHandler.php:41`, `PerformanceCompareCommand` for json_validate eligibility; close-with-rationale where decoded value is consumed | WP03 | [P] |
| T009 | Final verification: run full PHPUnit, PHPStan level 5, PHP-CS-Fixer, layer + composer policy checks | WP03 | |

---

## WP01 — array_find: ingestion test suites

**Goal**: Replace `array_values(array_filter(...))[0]` first-match patterns with `array_find()` across four ingestion test files (10 total sites). Test-only blast radius.

**Priority**: P1 (foundation; lowest risk; unblocks reviewer trust in mission shape)
**Independent test**: Per-file PHPUnit run still green; full suite green.
**Estimated prompt size**: ~280 lines
**Dependencies**: none.

### Included subtasks

- [ ] T001 array_find swap in `packages/cli/tests/Unit/Ingestion/SchemaValidatorTest.php` (lines 81, 103, 124, 144, 166, 186, 208, 230, 250, 275)
- [ ] T002 array_find swap in `packages/foundation/tests/Unit/Ingestion/PayloadValidatorTest.php:618`
- [ ] T003 array_find swap in `packages/cli/tests/Unit/Ingestion/ValidationGateValidatorTest.php:80`
- [ ] T004 array_find swap in `packages/cli/tests/Unit/Command/IngestRunCommandTest.php:256`

### Implementation sketch

For each site:
1. Read the existing block — capture predicate body.
2. Replace `array_values(array_filter($source, fn ($x) => P($x)))[0] ?? null` (or any of its no-default variants) with `array_find($source, static fn ($x) => P($x))`.
3. If the original code raised `Undefined offset` on no-match (no `?? null`), wrap or branch to preserve test failure behavior — most sites assert non-null immediately, which `null` from `array_find` will still trip.
4. Re-run only the affected test file: `./vendor/bin/phpunit packages/<pkg>/tests/Unit/...`.

### Risks

- Tests that destructure the result expecting `array<int, T>` shape will fail — `array_find` returns the element directly. None expected based on inspection but verify per site.

### Prompt file

See `tasks/WP01-array-find-ingestion-tests.md`.

---

## WP02 — array_find: production planner + routing/access sweep

**Goal**: Apply `array_find` to one production callsite in the ingestion planner; conduct a read-only sweep of `packages/routing/` and `packages/access/` for additional candidates.

**Priority**: P2 (production code; verify-then-edit)
**Independent test**: Existing planner unit tests green; sweep findings documented in WP closing notes.
**Estimated prompt size**: ~250 lines
**Dependencies**: WP01 (sequencing only — to validate the array_find pattern is reviewer-accepted before touching production).

### Included subtasks

- [ ] T005 Verify first-match semantics, then array_find swap in `packages/cli/src/Ingestion/SemanticRefreshTriggerPlanner.php:415`
- [ ] T006 Read-only sweep of `packages/routing/src/` and `packages/access/src/` for `foreach { if return }` candidates; document findings in WP closing notes (no edits in those packages here — file follow-up issue if surfaces material count)

### Implementation sketch

1. Open `SemanticRefreshTriggerPlanner.php:415`. Inspect surrounding loop intent — is the `array_values(array_filter(array_map(...)))` extracting the first match, all matches, or constructing a list?
2. If first-match: swap to `array_find(array_map(...), static fn ($x) => $x !== null)`. If all matches: swap to `array_filter(array_map(...))` (drop the wrapping `array_values` only — out of scope for this WP if shape changes anything downstream). If list shape is required: leave with a one-line comment explaining why.
3. For the sweep: `rg -nP "foreach \(.+?\) \{[\s\S]+?return " packages/routing/src/ packages/access/src/` (manual review — too noisy for automation). Note matches in the WP close-out; do **not** edit those files in this WP.

### Risks

- Sweep may surface non-mechanical sites; resist scope creep — file follow-up, don't edit here.

### Prompt file

See `tasks/WP02-array-find-production-and-sweep.md`.

---

## WP03 — `#[\Deprecated]` + json_validate audit close-out

**Goal**: Promote `FailedJobRepository`'s `@deprecated` docblock to a `#[\Deprecated]` attribute; audit and close FR-007/008/009 with rationale (initial inspection shows the targets use the decoded value, so per Decision 3 they remain `try/catch`).

**Priority**: P2 (low blast radius, high IDE/static-analyzer signal value).
**Independent test**: PHPUnit green; PHPStan does not regress on the new attribute.
**Estimated prompt size**: ~280 lines
**Dependencies**: none (parallel-safe with WP01).

### Included subtasks

- [ ] T007 Add `#[\Deprecated(message: "Use FailedJobRepositoryInterface", since: "0.1")]` attribute to `packages/queue/src/FailedJobRepository.php` (class-level), keep `@deprecated` docblock
- [ ] T008 Audit `MigrateDefaultsHandler.php:236`, `FixturePackRefreshHandler.php:41`, and locate-then-audit `PerformanceCompareCommand` (under `packages/cli/`); for each, document whether the decoded value is consumed downstream — if yes, close FR-007/008/009 with the standard rationale; if no, perform the json_validate swap
- [ ] T009 Run final verification: `./vendor/bin/phpunit`, `composer phpstan`, `composer cs-check`, `bin/check-package-layers`, `composer check-composer-policy`

### Implementation sketch

T007:
- Open `packages/queue/src/FailedJobRepository.php`.
- Above the `class FailedJobRepository` line (under the `*/` of the existing docblock), add: `#[\Deprecated(message: "Use FailedJobRepositoryInterface with InMemoryFailedJobRepository or DatabaseFailedJobRepository instead", since: "0.1")]`.
- Re-run `./vendor/bin/phpunit packages/queue/tests/`. PHPUnit may emit deprecations from any callsite — confirm none promote to errors.

T008:
- For each handler: the audit (2026-05-10) flagged catch-only-suppress; closer inspection showed both *use* the decoded value (`$entries[] = $entry` in MigrateDefaults; `$decoded` shape-checked then used in FixturePackRefresh). Per research.md Decision 3, those stay. **Document this in the WP close-out**.
- Locate `PerformanceCompareCommand`: `find packages -iname 'PerformanceCompare*'`. If it exists and is genuinely catch-only-suppress, swap to `if (!json_validate($s)) { return null; }`. Otherwise close with same rationale.

T009:
- Run all five verification commands listed in `quickstart.md`. Capture output summaries in the WP close-out.

### Risks

- `#[\Deprecated]` may emit `E_USER_DEPRECATED` from existing test fixtures using the class — verify with the package test suite first. The class is internal; only the package's own tests should reference it.

### Prompt file

See `tasks/WP03-deprecated-attribute-and-json-validate-audit.md`.

---

## Execution order & parallelism

- **Lane A**: WP01 → WP02 (sequential — WP02 follows WP01 to validate pattern acceptance).
- **Lane B**: WP03 (parallel with Lane A; final verification step T009 should run from the merged branch state).

MVP scope (smallest reviewable shippable slice): **WP01 alone** demonstrates the modernization pattern; WP02 + WP03 follow.

## Branch contract (final)

- **Planning base**: `main`
- **Merge target**: `main`
- **branch_matches_target**: `true`
