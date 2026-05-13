---
work_package_id: WP10
title: Conformance suite + reference CsvSource fixture
dependencies:
- WP01
- WP05
requirement_refs:
- FR-049
- FR-050
- FR-051
- FR-052
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T053
- T054
- T055
- T056
- T057
agent: "claude:opus:waaseyaa-implementer:implementer"
shell_pid: "22196"
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/testing/
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/testing/SourceConformanceTestCase.php
- packages/migration/testing/DestinationConformanceTestCase.php
- packages/migration/tests/Fixtures/CsvSource.php
- packages/migration/tests/Contract/ReferenceSourceConformanceTest.php
- packages/migration/tests/Contract/ReferenceDestinationConformanceTest.php
- packages/migration/tests/Fixtures/data/conformance-small.csv
- packages/migration/tests/Fixtures/data/conformance-large.csv
priority: p1
tags:
- stable-surface
- layer-3
- testing
- conformance
---

# WP10 — Conformance suite + reference CsvSource fixture

## Objective

Ship the two reusable abstract test bases (`SourceConformanceTestCase`, `DestinationConformanceTestCase`) that any source / destination plugin implementation must subclass and pass. Ship the reference `CsvSource` fixture used by both the conformance suite (proves the bases work) and by WP11's end-to-end validation. Ship the two concrete reference conformance tests that prove the framework's own `EntityDestination` and `CsvSource` pass.

`testing/` directory is `autoload-dev` only (CLAUDE.md gotcha: never put dev-only deps under `src/`). The composer.json declaration was prepared by WP01.

## Dependencies

- Internal: WP01 (plugin interfaces, DTOs, registry, `autoload-dev` testing namespace), WP05 (`EntityDestination` as the canonical reference destination).
- External: None.
- Charter anchors: §5.8 (proposed) — `SourceConformanceTestCase` + `DestinationConformanceTestCase` as stable test surface.

## Scope (in / out)

**In scope**
- `Waaseyaa\Migration\Testing\SourceConformanceTestCase` abstract base (FR-049, FR-051).
- `Waaseyaa\Migration\Testing\DestinationConformanceTestCase` abstract base (FR-050, FR-051).
- `Waaseyaa\Migration\Tests\Fixtures\CsvSource` reference implementation of `SourcePluginInterface` (FR-052).
- Reference concrete conformance tests: `ReferenceSourceConformanceTest` (runs the base against `CsvSource`) and `ReferenceDestinationConformanceTest` (runs the base against `EntityDestination`).
- Two CSV fixture files: a small (≤ 100 rows) for fast-path tests, a large (> 50 MB equivalent in row count, but compact — generated programmatically at test time to avoid committing huge files; document the generator) for memory-bound tests.

**Out of scope**
- Process-plugin conformance: not in scope (process plugins are tiny and well-tested by their unit tests; no separate conformance base).
- WordPress reader conformance: separate mission.
- Performance benchmarks: not part of conformance.

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Per-lane worktree. Run `spec-kitty agent action implement WP10 --agent opus`.

## Implementation guidance

### Subtask T053 — `CsvSource` reference fixture

**Purpose**: A real, working `SourcePluginInterface` implementation that the conformance suite drives. NOT a first-party composer package (per spec FR-052) — it lives under `tests/Fixtures/` and is autoload-dev only.

**FRs covered**: FR-052.

**Files**:
- `packages/migration/tests/Fixtures/CsvSource.php` (new, ~180 lines).
- `packages/migration/tests/Fixtures/data/conformance-small.csv` (new, ~120 rows).

**Steps**:
1. `final class CsvSource implements SourcePluginInterface` (`@api`-equivalent — it's autoload-dev so charter doesn't apply, but mark with `@api` for shipmonk).
2. Constructor: `__construct(public string $path, /** @var list<string> */ public array $keyFields, public string $sourceType = 'csv')`.
3. `records(): iterable` — generator yielding `SourceRecord` per CSV row:
   - Open the file with `fopen()`; read the header row with `fgetcsv()`.
   - Loop with `fgetcsv()`:
     - Build an associative array (`array_combine($header, $row)`).
     - `yield new SourceRecord($this->sourceType, $assoc)`.
   - Close the file in `finally`.
4. `sourceIdFor(SourceRecord $record): SourceId` — extracts the key fields from the record and constructs a `SourceId($this->sourceType, $keysSubset)`.
5. `count(): ?int` — return null by default (CSV row count requires a full pass; not worth the cost for v1). Document.
6. `id(): string` → `'csv_reference'`. `stability(): string` → `'stable'`.

**Validation**:
- [ ] Unit smoke test: open the small fixture, iterate, assert row count + first row's field values.

**Edge cases**:
- Missing CSV file raises `SourceReadException` (from WP04).
- Empty CSV (header only) yields zero records — supported.
- Headers with duplicate names: `array_combine` collapses; document — operators must ensure unique column names.

### Subtask T054 — `SourceConformanceTestCase` abstract base

**Purpose**: A subclass-once-and-pass test base for source plugins. Subclasses implement two protected factory methods; the base runs the FR-049 / FR-051 gates.

**FRs covered**: FR-049, FR-051.

**Files**:
- `packages/migration/testing/SourceConformanceTestCase.php` (new, ~260 lines).

**Steps**:
1. `abstract class SourceConformanceTestCase extends \PHPUnit\Framework\TestCase` (`@api`).
2. Abstract methods subclasses must implement:
   - `abstract protected function buildPluginUnderTest(): SourcePluginInterface;` — the plugin instance backed by a small fixture.
   - `abstract protected function buildLargeFixturePath(): string;` — absolute path to a fixture ≥ 50 MB.
   - `abstract protected function buildSmallFixturePath(): string;` — absolute path to a fixture ≤ 100 records.
3. Gates (one `#[Test]` per gate):
   - **C1 — `records()` is a lazy iterable**: assert the result of `records()` is `instanceof \Traversable` and NOT `instanceof \ArrayAccess` (rules out pre-loaded arrays). Iterate once; assert each yielded element is a `SourceRecord`.
   - **C2 — `sourceIdFor()` is deterministic**: invoke twice on the same record; assert identical `SourceId` (compared via `hash()`).
   - **C3 — `SourceId::hash()` is stable across multiple plugin instances**: build two plugin instances pointing at the same fixture; iterate first record on each; assert `sourceIdFor()->hash()` matches.
   - **C4 — `count()` returns non-negative int or null**: assert `count()` is null or an int `>= 0`.
   - **C5 — Streaming memory bound**: import the large fixture; assert peak memory (`memory_get_peak_usage(true)`) growth is ≤ 50 MB above baseline. Use `gc_collect_cycles()` before and after to stabilize. (Some platforms inflate baseline; the contract says "memory bound", document the absolute bound clearly.)
   - **C6 — Idempotent re-iteration**: iterate `records()` twice from the same plugin instance; if the implementation supports rewind (most generators don't), assert identical first record. If not rewindable, document the contract: a fresh plugin instance must yield identical first record (test that instead).
   - **C7 — Error path for missing source**: build a plugin pointing at a non-existent path; assert `records()` raises `SourceReadException` (or the plugin's documented exception).
   - **C8 — `id()` is non-empty + stable**: call `id()` twice; assert non-empty + identical.

**Validation**:
- [ ] Each `#[Test]` is independently runnable.
- [ ] The base class itself does not fail in PHPUnit collection (abstract is honoured — PHPUnit skips collection on abstract classes).

**Edge cases**:
- C5's memory assertion is brittle on CI; document the variance and use a generous threshold (50 MB ceiling, not 5 MB).
- C6's rewind contract is documented as "fresh-instance only" — the conformance base accommodates both behaviors.

### Subtask T055 — `DestinationConformanceTestCase` abstract base

**Purpose**: A subclass-once-and-pass test base for destination plugins.

**FRs covered**: FR-050, FR-051.

**Files**:
- `packages/migration/testing/DestinationConformanceTestCase.php` (new, ~300 lines).

**Steps**:
1. `abstract class DestinationConformanceTestCase extends \PHPUnit\Framework\TestCase` (`@api`).
2. Abstract methods:
   - `abstract protected function buildDestinationUnderTest(): DestinationPluginInterface;`
   - `abstract protected function buildDestinationRecord(SourceId $sourceId): DestinationRecord;` — subclass crafts a valid record for its destination.
   - `abstract protected function buildAccessDeniedAccount(): AccountInterface;` — subclass provides an account that fails the `create` gate.
   - `abstract protected function setUpStorage(): void;` — subclass prepares the storage substrate (e.g. SQLite + schemas).
3. Gates:
   - **D1 — `write()` returns a `WriteResult` with populated uuid**.
   - **D2 — `write()` is idempotent with id-map** (the conformance suite injects a real `MigrationIdMap` against `DBALDatabase::createSqlite()`): write the same record twice; assert one row in the underlying storage; second `write()` returns same uuid.
   - **D3 — `rollback()` reverses `write()`**: write, rollback, then `lookup(sourceId)` returns null.
   - **D4 — `lookup()` returns prior `WriteResult` for written source-id**.
   - **D5 — Access denial raises `DestinationWriteException`**: use `buildAccessDeniedAccount()` and assert.
   - **D6 — `id()` non-empty + stable**.
   - **D7 — `stability()` returns `'stable'` or `'experimental'`**.

**Validation**:
- [ ] Each gate is independently runnable.

**Edge cases**:
- D2 + D3 + D4 require the subclass's storage to be reset between tests; document via `setUp() { $this->setUpStorage(); }` in the abstract base.
- D5 requires the subclass to wire `Gate` correctly; the abstract base documents the contract.

### Subtask T056 — `ReferenceSourceConformanceTest`

**Purpose**: Prove the conformance base works by running it against the framework's own `CsvSource` fixture.

**FRs covered**: FR-049, FR-051, FR-052 (composition).

**Files**:
- `packages/migration/tests/Contract/ReferenceSourceConformanceTest.php` (new, ~120 lines).
- `packages/migration/tests/Fixtures/data/conformance-large.csv` (NEW or generated) — see steps.

**Steps**:
1. `final class ReferenceSourceConformanceTest extends SourceConformanceTestCase` — uses `#[CoversNothing]` (contract test convention from CLAUDE.md).
2. `buildPluginUnderTest()` returns `new CsvSource($smallPath, ['id'])`.
3. `buildSmallFixturePath()` returns the path to `conformance-small.csv` (committed; ~100 rows).
4. `buildLargeFixturePath()` returns a path to a generated-at-test-time file: `setUp()` generates a 200,000-row CSV in `sys_get_temp_dir() . '/waaseyaa_migration_conformance_large_' . uniqid() . '.csv'`. ~50 MB. Cleaned up in `tearDown()`.
5. The class is otherwise empty — all eight gates inherit and run automatically.

**Validation**:
- [ ] `./vendor/bin/phpunit --filter ReferenceSourceConformanceTest` green.

**Edge cases**:
- Committing a 50 MB binary CSV is undesirable. Generate at runtime. Document the generator's deterministic seeding so the fixture content is reproducible (use `mt_srand(42)`).

### Subtask T057 — `ReferenceDestinationConformanceTest`

**Purpose**: Prove the conformance base works against `EntityDestination` (WP05).

**FRs covered**: FR-050, FR-051 (composition).

**Files**:
- `packages/migration/tests/Contract/ReferenceDestinationConformanceTest.php` (new, ~180 lines).

**Steps**:
1. `final class ReferenceDestinationConformanceTest extends DestinationConformanceTestCase` (`#[CoversNothing]`).
2. `setUpStorage()`: in-memory SQLite + WP05's `migration_test_widget` entity type + id-map migration.
3. `buildDestinationUnderTest()` returns a fully-wired `EntityDestination` pointing at `migration_test_widget`.
4. `buildDestinationRecord(SourceId $sourceId)` constructs a `DestinationRecord` with `title`, `body`, `value_int`, `tags` field values.
5. `buildAccessDeniedAccount()` returns a `Gate` test double denying `create` for the test account; assemble via the framework's existing test-Gate helpers.

**Validation**:
- [ ] `./vendor/bin/phpunit --filter ReferenceDestinationConformanceTest` green — all seven D-gates pass.
- [ ] Full suite green.

**Edge cases**:
- The destination test must reset the in-memory DB in `setUp()` to keep gates independent.

## Tests

- **Unit**: T053 smoke test.
- **Contract**: T056, T057 — these are the reference conformance tests.
- **Integration**: not in scope.

## Definition of Done

- [ ] All five subtasks complete.
- [ ] All four FRs cited in code as `@spec FR-xxx`.
- [ ] `composer phpstan` clean.
- [ ] `composer cs-check` clean (run twice).
- [ ] `bin/check-package-layers` clean.
- [ ] `bin/check-composer-policy` clean.
- [ ] `bin/audit-dead-code` clean (test bases are reflection-discovered; the audit auto-marks PHPUnit subclasses, but mark with `@api` anyway for documentation).
- [ ] `./vendor/bin/phpunit` full suite green.
- [ ] `SourceConformanceTestCase` and `DestinationConformanceTestCase` carry `@api`.
- [ ] `Waaseyaa\Migration\Testing\` namespace is registered in `packages/migration/composer.json` under `autoload-dev` only — verify by reading the manifest.
- [ ] `Waaseyaa\Migration\Testing\` classes are NOT visible after a production install (`composer install --no-dev`). Smoke test: `composer install --no-dev && php -r "var_dump(class_exists('Waaseyaa\\Migration\\Testing\\SourceConformanceTestCase'));"` must print `false`.
- [ ] CSV fixture path conventions documented in PHPDoc.

## Risks

- **R1 — Memory assertion brittle on CI** (C5): mitigate with generous absolute threshold (50 MB) and a `gc_collect_cycles()` reset before each iteration. Document the variance.
- **R2 — `autoload-dev` regression**: a future refactor moves `Testing/` under `src/`, breaking consumers. Mitigate with the production smoke test in the DoD bullet above. Also documented in CLAUDE.md gotcha "Never put classes that extend dev-only deps under autoload".
- **R3 — Conformance suite drift from spec**: as new FRs land (or existing FRs sharpen), the conformance bases need updates. Mitigate: the bases reference FRs in PHPDoc; reviewer compares against `spec.md` §10.
- **R4 — Large CSV generation slow in CI**: 200,000 rows × `fputcsv` is ~5 seconds on average hardware. Acceptable; document the cost.

## Reviewer guidance

- Check: both conformance bases are `abstract class` (PHPUnit skips abstract classes in collection).
- Check: every `#[Test]` method has a comment referencing the FR or contract gate (e.g. `// @gate C5 — Streaming memory bound (FR-051)`).
- Check: `CsvSource` uses `yield` (generator), not array buffering.
- Check: `ReferenceSourceConformanceTest` runs against the actual `CsvSource` (not a mock).
- Check: `ReferenceDestinationConformanceTest` runs against the actual `EntityDestination` (not a mock).
- Verify: `composer install --no-dev` smoke test in the DoD passes — autoload-dev wiring is correct.
- Verify: large fixture is generated at test time, not committed as a 50 MB binary.
- Confirm: `Testing/` directory carries a `.gitignore`-style entry only for generated fixtures (not the test bases themselves).

## Activity Log

- 2026-05-13T16:23:43Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=18558 – Started implementation via action command
- 2026-05-13T16:33:31Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=18558 – Ready for review — conformance suite (SourceConformanceTestCase + DestinationConformanceTestCase) + CsvSource fixture + two reference contract tests; full suite green (8249 tests). Two WP05/WP08 deviations documented via configurable hooks (allowedStabilityValues/rollbackClearsLookup) for follow-up.
- 2026-05-13T16:34:14Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=20951 – Started review via action command
- 2026-05-13T16:38:06Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=20951 – Moved to planned
- 2026-05-13T16:38:32Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=22196 – Started implementation via action command
