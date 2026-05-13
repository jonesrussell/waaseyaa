# Mission Review — M-002 Migration Platform v1

**Mission slug:** `migration-platform-v1-01KRCDE9`
**Mission ID:** `01KRCDE9ZXK2JEFPT6THSBVKNY`
**Reviewer:** claude:opus:mission-reviewer (spec-kitty-mission-review skill)
**Review date:** 2026-05-13
**Squash-merge commit on `main`:** `d92f82f4a37108818424d323ae70594277c3d614`
**Mission merge commit (true merge):** `03aabb9e8b8571fef7f16e5e3f2ce18abe485376` (mission branch `kitty/mission-migration-platform-v1-01KRCDE9`)
**Pre-merge `main`:** `09686c43b`
**Post-merge HEAD at review:** `09686c43b`
**Baseline diff range used:** `merge-base(03aabb9e8^1, 03aabb9e8^2)..03aabb9e8^2` (mission-branch tip vs. fork point) — `meta.json` carries no `baseline_merge_commit` field; the mission branch was the canonical diff carrier.
**Diff size:** **136 files changed, 21,934 insertions, 9 deletions**

---

## Verdict

**PASS WITH NOTES.**

All 12 WPs are `approved`. The 62 FRs are covered. Stable-surface charter §5.8 lands. The end-to-end CSV→entity round-trip (WP11) is green. No CRITICAL or HIGH risks blocking the release of the substrate.

Five **MEDIUM** findings warrant post-merge follow-up (none of them re-implementation work; all are deltas in tests, docs, or contract clarification). Documented below with file:line evidence.

---

## 1. Orient — WP state and rejection-cycle signal

All 12 WPs sit in `approved` per `kitty-specs/migration-platform-v1-01KRCDE9/status.json`. The `force_count` field is a useful proxy for cycle pressure:

| WP | force_count | Review cycles on disk | Note |
|---|---|---|---|
| WP01 | 1 | 0 | clean |
| WP02 | 1 | 0 | clean |
| WP03 | 1 | 0 | clean |
| WP04 | 1 | 0 | clean |
| WP05 | **2** | 0 (no review-cycle-N.md) | one re-emission of `for_review` — explained in commits |
| WP06 | **2** | 0 | re-emission, not rejection |
| WP07 | **3** | 0 | per implementer log: signal-handler resume edge case |
| WP08 | 1 | 0 | clean |
| WP09 | 1 | 0 | clean |
| **WP10** | **4** | **2 (`review-cycle-1.md` REJECTED, `review-cycle-2.md`)** | `EntityDestination::stability()` set to `'beta'`; rejected by reviewer; fixed by `ea71e0f23 fix(WP10 cycle-2): align EntityDestination stability with canonical contract`. Re-review accepted. Resolution shipped on main. |
| WP11 | 1 | 0 | clean |
| WP12 | 1 | 0 | clean |

**WP10 cycle-1 produced an unresolved follow-up** flagged by the reviewer (see Drift Finding D3 below): the contract ambiguity between conformance gate C4 (lookup returns `null` after rollback) and FR-042 (id-map row retained on idempotent re-run). The reviewer chose to ship the stability fix and **explicitly defer the D3 vs FR-042 reconciliation to a follow-up issue** that has not been filed.

---

## 2. Mission contract absorption

### 2.1 Non-Goals (spec §1.2) — invasion check

| Non-Goal | Status |
|---|---|
| WordPress source reader | Out — not shipped |
| Drupal 7 / 10+ source readers | Out — not shipped |
| Admin UI | Out — not shipped |
| Incremental / continuous sync | Out — runner exits on iteration end |
| Real-time conflict resolution | Out — operator concern |
| Migrate UI | Out |
| Content-promotion via migration | Out — `EntityDestination` is inbound only |

No Non-Goal invasion detected.

### 2.2 Locked Decisions (research §2 D1–D12)

D1–D12 all anchor to the shipped code:

- **D1** (interfaces + `final readonly` value objects) — `SourcePluginInterface`, `ProcessPluginInterface`, `DestinationPluginInterface`, `final readonly` `SourceRecord` / `DestinationRecord` / `WriteResult` / `ProcessContext` / `SourceId` / `MigrationDefinition`. PASS.
- **D2** (provider capability registration) — `HasMigrationPluginsInterface`, `HasMigrationsInterface` exist; `PluginRegistry` scans them at boot. PASS.
- **D3** (reserved id namespace) — `ReservedPluginIds` shipped; six framework processors all use reserved ids; collision throws `MigrationPluginCollisionException` with `isReserved` flag. PASS.
- **D4** (id-map schema frozen v1) — `migration_id_map` migration shipped at `packages/migration/migrations/2026_05_13_000001_create_migration_id_map.php`. PASS.
- **D5** (`SourceId::hash() = sha256(canonical)`) — `CanonicalForm` + `SourceId` shipped. PASS.
- **D6** (separate `source_record_hash`) — `MigrationIdMap::upsert()` accepts both fields. PASS.
- **D7** (`EntityDestination` via coordinator, never direct) — `EntityDestination::write()` calls `EntityRepository::save(...)` at `packages/migration/src/Plugin/Destination/EntityDestination.php:416`. PASS at the surface, but see **Risk Finding R-MED-2** for the lifecycle-event double-dispatch.
- **D8** (bundle at write time) — `EntityDestinationFactory` resolves bundle from `MigrationDefinition`. PASS.
- **D9** (per-record commit default, ≤100 opt-in batch) — `MigrationIdMap::transactional()` wraps per-record save + upsert. PASS.
- **D10** (best-effort reverse-creation rollback) — `RollbackWalker::rollback()` collects errors in `RollbackReport::$errors` without halting. PASS.
- **D11** (filesystem lock, no auto stale recovery) — `MigrationLock` shipped with PID + signal handler + shutdown function fallback. PASS.
- **D12** (`import:*` namespace) — All six commands ship under `Waaseyaa\CLI\Command\Import`. PASS.

No locked-decision violations.

---

## 3. Git timeline / coverage map

Top-level dirs touched in the 136-file diff:

- `packages/migration/**` — primary deliverable (sources, runners, exceptions, schemas, migrations, tests, contracts).
- `packages/cli/src/Command/Import/**` + tests — six `import:*` commands.
- `packages/entity-storage/src/SaveContext.php` + test — `isImport` additive flag.
- `docs/specs/migration-platform.md` — canonical subsystem spec.
- `docs/specs/migration-platform-v1.md` — preserved mission-doctrine spec.
- `docs/specs/stability-charter.md` — §5.8 amendment.
- `docs/extension-authoring/migration-source-readers.md` — FR-057.
- `docs/extension-authoring/migration-process-plugins.md` — FR-058.
- `docs/upgrades/waaseyaa-alpha-177-to-178.md` — FR-059.
- `docs/cookbook/migration-first-cut.md` — FR-060.
- `docs/public-surface-map.php` — partial registration (see D-MED-1).
- `composer.json`, `CLAUDE.md` — workspace and orchestration-table updates.

**Spec-tracks-with-no-diff check:** none. Every spec FR maps to at least one shipped file.

---

## 4. WP review history audit

Two review-cycle files on disk, both under `tasks/WP10-conformance-suite/`:

- **`review-cycle-1.md` (REJECTED, 2026-05-13).** Cause: `EntityDestination::STABILITY = 'beta'` violated the canonical destination contract (interface PHPDoc `@return 'stable'|'experimental'`; contract doc line 26; FR-009 deprecation branching logic). Reviewer required a one-line fix.
  - **Resolution shipped:** commit `ea71e0f23` flips `STABILITY` to `'stable'` and removes the `allowedStabilityValues()` override. Verified in code today (`EntityDestination.php`).
- **`review-cycle-2.md` (ACCEPTED).** Stability constant correct; suite green.

**Reviewer-deferred follow-up that did NOT ship:**

> "Clarify destination-plugin contract: D3 `lookup()` returns null after rollback vs FR-042 idempotency (id-map row retained, prior `WriteResult` returned)."

`grep -rn "FR-042" packages/migration` produces one hit in `ReferenceDestinationConformanceTest.php:104-109` documenting the ambiguity with a `// Follow-up:` comment. Today gate C4 in `DestinationConformanceTestCase` (per contract doc) still asserts `lookup() === null` after `rollback()`, while FR-042's literal text — "Re-running an unchanged record MUST NOT create a duplicate destination entity. The id-map row's `last_imported_at` SHOULD update" — implies row retention. The two are not technically contradictory (rollback is delete; an unchanged re-run is a separate scenario), but the ambiguity merits an explicit normative resolution. **See Drift Finding D-MED-2.**

---

## 5. FR coverage matrix

Spec §3 declares 62 FRs (FR-001..FR-062, no FRs >62; FR-045..FR-048 are error-model not concurrency). Each FR's source-of-truth: spec section → WP → shipped file(s) → test(s). Classification per the skill's definitions:

- **ADEQUATE** — test directly constrains the FR; deleting the impl breaks the test.
- **PARTIAL** — test is present but covers a narrow slice of the FR.
- **MISSING** — no test hit found.
- **FALSE_POSITIVE** — test passes regardless of impl correctness.

| FR | Topic | WP | Shipped at | Test | Class |
|---|---|---|---|---|---|
| FR-001 | `SourcePluginInterface` stable | WP01 | `packages/migration/src/Plugin/SourcePluginInterface.php` | unit + conformance C1..C8 | ADEQUATE |
| FR-002 | `records()`/`sourceIdFor()`/`count()` | WP01 | same | `SourceConformanceTestCase` | ADEQUATE |
| FR-003 | `ProcessPluginInterface` stable | WP01 | `Plugin/ProcessPluginInterface.php` | unit | ADEQUATE |
| FR-004 | `transform(mixed, ProcessContext)` | WP01 | same + `ProcessContext.php` | unit | ADEQUATE |
| FR-005 | `DestinationPluginInterface` stable | WP01 | `Plugin/DestinationPluginInterface.php` | unit + conformance | ADEQUATE |
| FR-006 | `write/rollback/lookup` | WP01 | same | `DestinationConformanceTestCase` C1..C8 | ADEQUATE |
| FR-007 | `HasMigrationPluginsInterface` capability | WP01 | `Discovery/HasMigrationPluginsInterface.php` | discovery test (WP02) | ADEQUATE |
| FR-008 | Plugin collision -> typed exception | WP01 | `Exception/MigrationPluginCollisionException.php` + `PluginRegistry` | unit | ADEQUATE |
| FR-009 | `id()` + `stability()` w/ deprecation | WP01 | `PluginRegistry` first-use deprecation | unit | ADEQUATE |
| FR-010 | Array-order process chain | WP01/WP03 | `Runner/ProcessChainExecutor.php` | unit + WP11 chain test | ADEQUATE |
| FR-011 | `MigrationDefinition` stable VO | WP02 | `MigrationDefinition.php` | unit | ADEQUATE |
| FR-012 | Manifest shape | WP02 | same | unit | ADEQUATE |
| FR-013 | Discovery via provider + filesystem | WP02 | `Discovery/FilesystemManifestLoader.php` + `HasMigrationsInterface` | integration | ADEQUATE |
| FR-014 | Missing-dep typed exception | WP02 | `Exception/MigrationDependencyMissingException.php` | unit | ADEQUATE |
| FR-015 | Cycle detection at registration | WP02 | `Discovery/CycleDetector.php` + `DependencyGraph.php` | unit | ADEQUATE |
| FR-016 | Field-keyed process map | WP02 | `MigrationDefinition::processForField()` | unit | ADEQUATE |
| FR-017 | Globally unique migration ids | WP02 | `MigrationRegistry::register()` | unit | ADEQUATE |
| FR-018 | `EntityDestination` writes thru coord | WP05 | `Plugin/Destination/EntityDestination.php:416` | integration (non-rev + rev) | ADEQUATE |
| FR-019 | Lifecycle events fire | WP05 | `EntityDestination.php:412/440` | `save_dispatches_before_and_after_with_import_save_context` | ADEQUATE — but see R-MED-2 |
| FR-020 | Access-policy denial -> `DestinationWriteException` | WP05 | `EntityDestination::write` denial branch | conformance C5 | ADEQUATE |
| FR-021 | Coordinator-level events with `SaveContext` | WP05 | `EntityDestination.php:412/440` | conformance C7 | ADEQUATE — see R-MED-2 |
| FR-022 | `SaveContext::$isImport` true during import | WP05 | `SaveContext::isImport` + destination construction | C7 | ADEQUATE |
| FR-023 | Initial revision on revisionable types | WP05 | `EntityDestination` revision branch | revisionable integration test | ADEQUATE |
| FR-024 | Bundle resolved at write time | WP05 | `EntityDestinationFactory` | unit | ADEQUATE |
| FR-025 | `migration_id_map` table on stable surface | WP04 | `Schema/MigrationIdMapSchema.php` + migration file | smoke | ADEQUATE |
| FR-026 | `SourceId` stable VO | WP04 | `SourceId.php` | unit | ADEQUATE |
| FR-027 | Deterministic hashing | WP04 | `CanonicalForm.php` + `SourceId::hash()` | unit (deterministic vector) | ADEQUATE |
| FR-028 | `lookupDestination()` | WP04 | `MigrationIdMap::lookupDestination` | C3 + unit | ADEQUATE |
| FR-029 | Transactional id-map+entity write | WP04+WP05 | `MigrationIdMap::transactional` wraps EntityRepository::save | C6 simulated-failure | ADEQUATE |
| FR-030 | Idempotency: re-write -> no duplicates | WP04 | `MigrationIdMap::upsert` ON CONFLICT | C2 | ADEQUATE |
| FR-031 | Unchanged hash -> skip path | WP04 | `EntityDestination::write` hash short-circuit | `skip_path_unchanged_hash_returns_prior_write_result_without_save_or_upsert` | ADEQUATE |
| FR-032 | `import:run <id>` | WP06 | `Command/Import/ImportRunCommand.php` | unit | ADEQUATE |
| FR-033 | `import:run-all` | WP06 | `ImportRunAllCommand.php` | unit | ADEQUATE |
| FR-034 | `import:status` | WP06 | `ImportStatusCommand.php` | unit | ADEQUATE |
| FR-035 | `import:rollback` | WP08 | `ImportRollbackCommand.php` + `RollbackWalker` | integration | ADEQUATE |
| FR-036 | `import:reset` | WP08 | `ImportResetCommand.php` | integration | ADEQUATE |
| FR-037 | Resume cursor | WP07 | `MigrationRunState.php` + `ImportResumeCommand.php` | resume integration | ADEQUATE |
| FR-038 | `migration_run_state` table | WP07 | migration file `2026_05_13_000002_*` | smoke | ADEQUATE |
| FR-039 | `--dry-run` | WP06 | `RunOptions::$dryRun` + runner branch | unit | ADEQUATE |
| FR-040 | `--limit` | WP06 | `RunOptions::$limit` + runner branch | unit | ADEQUATE |
| FR-041 | `rollback(WriteResult)` reverses write | WP08 | `EntityDestination::rollback` | C4 + WP08 integration | ADEQUATE |
| FR-042 | Idempotent re-run keeps row | WP04 | `MigrationIdMap` + `EntityDestination` skip | `ReferenceDestinationConformanceTest` retention assertion | **PARTIAL** — see D-MED-2 |
| FR-043 | Reverse-creation walk | WP08 | `RollbackWalker::rollback` + `MigrationIdMap::walkReverseCreationWithKeys` | integration | ADEQUATE |
| FR-044 | Per-record reporting | WP08 | `RollbackReport` + `RollbackError` | unit + integration | ADEQUATE — see R-MED-1 |
| FR-045 | Typed exception base | WP01 | exception hierarchy | smoke | ADEQUATE |
| FR-046 | Continue on per-record error | WP06 | runner catch + `RunReport::$errors` | unit | ADEQUATE |
| FR-047 | `--halt-on-error` | WP06 | `RunOptions::$haltOnError` + `MigrationAbortedException` raise at `MigrationRunner.php:270` | unit | ADEQUATE |
| FR-048 | Run-level fatal -> abort | WP06 | runner outer catch | unit | ADEQUATE |
| FR-049 | `SourceConformanceTestCase` ships | WP10 | `testing/SourceConformanceTestCase.php` | self-test via `ReferenceSourceConformanceTest` | ADEQUATE |
| FR-050 | `DestinationConformanceTestCase` ships | WP10 | `testing/DestinationConformanceTestCase.php` | self-test via reference | ADEQUATE |
| FR-051 | Atomicity/idempotency/rollback gates | WP10 | C1..C8 + D1..D7 | both reference tests | ADEQUATE |
| FR-052 | Reference `CsvSource` lives in `tests/Fixtures/` (autoload-dev) | WP10 | `tests/Fixtures/CsvSource.php` | `ReferenceSourceConformanceTest` | ADEQUATE |
| FR-053 | E2E CSV -> entity | WP11 | `tests/Integration/Migration/EndToEndCsvToEntityTest.php` | `#[Test]` methods | ADEQUATE |
| FR-054 | Resume proves 1000 records, 0 dupes | WP11 | same | resume test | ADEQUATE |
| FR-055 | Rollback proves removal | WP11 | same | rollback test | ADEQUATE |
| FR-056 | `docs/specs/migration-platform.md` exists | WP12 | file present | n/a | ADEQUATE |
| FR-057 | Author guide for source readers | WP12 | `docs/extension-authoring/migration-source-readers.md` | n/a | ADEQUATE |
| FR-058 | Author guide for process plugins | WP12 | `docs/extension-authoring/migration-process-plugins.md` | n/a | ADEQUATE |
| FR-059 | Upgrade guide entry | WP12 | `docs/upgrades/waaseyaa-alpha-177-to-178.md` | n/a | ADEQUATE |
| FR-060 | Cookbook entry | WP12 | `docs/cookbook/migration-first-cut.md` | n/a | ADEQUATE |
| FR-061 | Filesystem lock | WP09 | `Runner/MigrationLock.php` (flock LOCK_EX|LOCK_NB) | unit + integration | ADEQUATE |
| FR-062 | Signal-caught release | WP09 | `MigrationLock::installSignalHandlers` (pcntl + shutdown) | integration | ADEQUATE — see R-MED-3 |

**Coverage summary:** 61 ADEQUATE, 1 PARTIAL (FR-042 — contract ambiguity, not a test gap per se), 0 MISSING, 0 FALSE_POSITIVE.

---

## 6. Drift and gap analysis

### Drift findings

#### D-MED-1 — public-surface-map.php under-registers §5.8 stable symbols (MEDIUM)

**Evidence:** `grep -n 'Migration' docs/public-surface-map.php`

Charter §5.8 enumerates ~30 stable-surface symbols. `docs/public-surface-map.php` lists only the four interface FQCNs:

```
271:    // Migration platform plugin contracts (mission migration-platform-v1-01KRCDE9 WP01).
272:    'Waaseyaa\Migration\Plugin\SourcePluginInterface' => 'public',
273:    'Waaseyaa\Migration\Plugin\ProcessPluginInterface' => 'public',
274:    'Waaseyaa\Migration\Plugin\DestinationPluginInterface' => 'public',
275:    'Waaseyaa\Migration\Discovery\HasMigrationPluginsInterface' => 'public',
276:    // Migration platform discovery / dependency graph (mission migration-platform-v1-01KRCDE9 WP02).
277:    'Waaseyaa\Migration\Discovery\HasMigrationsInterface' => 'public',
```

**Missing** from `public-surface-map.php` but listed in charter §5.8 as stable:

- `MigrationDefinition`, `SourceId`, `SourceRecord`, `DestinationRecord`, `WriteResult`, `ProcessContext` (6 value objects)
- `EntityDestination`, `EntityDestinationFactory` (2 concrete destinations)
- `PassThroughProcessor`, `HtmlSanitizeProcessor`, `LookupProcessor`, `ConcatProcessor`, `TypeCoerceProcessor`, `DefaultValueProcessor`, `ReservedPluginIds` (7 processors + reserved-id manifest)
- All 8 exception types (`MigrationCycleException`, `MigrationPluginCollisionException`, `MigrationDependencyMissingException`, `SourceReadException`, `ProcessException`, `DestinationWriteException`, `MigrationAbortedException`, `MigrationConcurrencyException`)
- `MigrationIdMapSchema` (the surface-of-truth descriptor)
- `Channels::MIGRATION_DEPRECATION` constant
- `SourceConformanceTestCase`, `DestinationConformanceTestCase` (autoload-dev but charter-listed as stable)

`docs/public-surface-map.md` has zero migration entries (only a stray match on `Migration` base class for foundation/Migration).

**Impact:** The `tests/Integration/SurfaceMap/PublicSurfaceVerificationTest.php` test reflectively verifies that every `public-surface-map.php` entry exists, but it does NOT enforce the reverse direction (every charter-listed symbol must be mapped). Consumers consulting `public-surface-map.php` as their machine-readable contract see only the interfaces, not the value objects or exceptions they MUST type against.

**Recommended action:** Patch `docs/public-surface-map.{php,md}` to enumerate all §5.8 symbols. Mechanical change; no behaviour shift.

**Severity:** MEDIUM (charter §5.8 stable surface is the published-contract; consumers reading the mapping file get a misleadingly narrow picture).

#### D-MED-2 — D3-vs-FR-042 contract ambiguity remains (MEDIUM)

**Evidence:**

- `kitty-specs/migration-platform-v1-01KRCDE9/tasks/WP10-conformance-suite/review-cycle-1.md` flags the conflict and recommends a follow-up issue.
- `packages/migration/tests/Contract/ReferenceDestinationConformanceTest.php:104-109` documents the ambiguity inline with a `// Follow-up:` comment.
- `contracts/destination-plugin.md` conformance gate C4: "After `rollback($result)`, `lookup($sourceId)` returns `null`."
- `spec.md` FR-042: "Re-running an unchanged record MUST NOT create a duplicate destination entity. The id-map row's `last_imported_at` SHOULD update."

Both statements happen to be operationally consistent (rollback is a delete; an unchanged re-run is a separate code path), but the contract doc does not say so explicitly. A third-party destination author reading C4 would expect their `lookup()` to return `null` after `rollback()`, which forces them to clear the id-map row inside `rollback()` — but the framework's own `EntityDestination` calls `MigrationIdMap::deleteByDestination()` in its rollback path, so it works by convention rather than by contract.

**Recommended action:** File the deferred follow-up issue. Tighten `contracts/destination-plugin.md` to state "`lookup()` MUST return `null` for any `SourceId` whose id-map row has been deleted; rollback MUST delete the id-map row." OR relax C4 to permit retention and update WP08's contract accordingly. Either resolves the ambiguity; both are docs-only.

**Severity:** MEDIUM (only third-party destination authors are at risk; no current code breaks).

#### D-LOW-1 — Stale "WP07 will wire run-state" comment in `ImportStatusCommand` (LOW)

**Evidence:** `packages/cli/src/Command/Import/ImportStatusCommand.php` PHPDoc line ~16:

> "WP06 shipped the placeholder rendering described in spec §9.2 with zero-valued FAILED / SKIPPED columns; WP07 wires `migration_run_state` so those columns reflect real per-record outcomes."

WP07 is merged; the comment language reads as if WP07 work is pending. Real outcomes ARE rendered in code. Pure documentation drift.

**Severity:** LOW.

### Punted-FR check

None. `tasks.md` Subtask Index lists every FR against a `[D]` (done) row; cross-checked against shipped tests in §5 above.

### NFR verification

Spec §3 does not enumerate machine-measurable NFRs (no SLO p95s, no memory ceilings other than the conformance suite's 50 MB source budget). The conformance suite asserts the memory budget directly (`SourceConformanceTestCase` gate C7).

---

## 7. Risk findings

### R-MED-1 — `RollbackReport` clock-skew constructor guard is a known WSL flake (MEDIUM)

**Evidence:**

- `packages/migration/src/Runner/RollbackReport.php:80-82`:
  ```php
  if ($finishedAt < $startedAt) {
      throw new \InvalidArgumentException('RollbackReport::$finishedAt must be >= $startedAt.');
  }
  ```
- `packages/migration/src/Runner/RollbackWalker.php:89` and `:130` build `startedAt = ($this->clock)()` and `finishedAt = ($this->clock)()` from a `\Closure` defaulting to `new \DateTimeImmutable('now', new \DateTimeZone('UTC'))`.

`\DateTimeImmutable('now')` resolves to whole-second precision on most platforms. Under WSL 2 the system clock can occasionally tick **backwards** when virtualised — the implementer flagged this directly as a ~1-in-30 WSL flake. Today the constructor would crash mid-walk on backwards-clock observation.

**Why MEDIUM, not LOW:** The rollback is best-effort and the report is operator-facing; a thrown `InvalidArgumentException` here would surface as an opaque CLI stack trace rather than a clean "rollback finished" summary, and the rollback work itself would still have happened (events fired, entities deleted) — so the user sees a "rollback failed" message for a rollback that succeeded. This is a logged false-positive failure on the operator-facing CLI.

**Recommended action:** Either (a) use higher-precision clock — `new \DateTimeImmutable('@' . microtime(true))` or `hrtime()`-based — or (b) clamp `finishedAt = max($finishedAt, $startedAt)` in the walker before constructing the report. (b) is the cheaper fix.

**Severity:** MEDIUM. Already on the user's known-issues list; documenting here for completeness.

### R-MED-2 — Cross-WP integration risk: `EntityDestination` self-dispatches `BeforeSave`/`AfterSave` while revisionable storage backends ALSO dispatch them (MEDIUM)

**Evidence:**

- `packages/migration/src/Plugin/Destination/EntityDestination.php:412`:
  ```php
  $this->eventDispatcher->dispatch(new BeforeSaveEvent($entity, $saveContext, $isNewRevision));
  ...
  $this->entityRepository->save($entity, validate: false);
  ...
  $this->eventDispatcher->dispatch(new AfterSaveEvent($entity, $saveContext, $isNewRevision));
  ```
- `packages/entity-storage/src/RevisionableSqlBlobStorage.php:160` AND `:176` — also dispatches `BeforeSaveEvent` / `AfterSaveEvent`.
- `packages/entity-storage/src/RevisionableSqlColumnStorage.php:155` — same.

**Cross-WP integration risk:** When `EntityDestination::write()` writes a revisionable entity, **`BeforeSaveEvent` and `AfterSaveEvent` are dispatched twice** — once by `EntityDestination` (carrying `$saveContext->isImport === true`) and once by the revisionable storage backend (with its own internally-constructed `SaveContext`, which would NOT carry `isImport = true` unless threaded through).

The WP05 reviewer accepted this approach because `EntityRepository::save()` does not accept a `SaveContext` parameter — `EntityDestination` self-dispatches as the only way to surface `$isImport = true` to event subscribers. But the M-001 storage backends were not informed of the migration platform's contract, so subscribers receive:
1. One event with `isImport = true` (from `EntityDestination`).
2. One event with the backend's default `SaveContext` (`isImport = false` by default).

A subscriber that branches on `$event->saveContext()->isImport` will execute its import-branch logic on event #1 and its normal-save-branch logic on event #2 in the same write call. **Idempotency-safe operations are fine; non-idempotent side-effects (e.g. "send a webhook on every save") fire twice.**

**Why MEDIUM, not HIGH:** No production subscriber today depends on `isImport` for revisionable entities (the WP05 integration test covers the non-revisionable path and the revisionable path with no extra subscribers). But the contract `FR-019` says "lifecycle events fire" — not "fire twice with conflicting context."

**Recommended action:** Either (a) thread a `?SaveContext` parameter through `EntityRepository::save()` and the storage backends so the backend's own dispatch carries `$isImport = true` (cross-mission change touching M-001's surface), or (b) document this dispatch shape in `migration-platform.md` and `BeforeSaveEvent` PHPDoc so subscribers know to deduplicate.

**Severity:** MEDIUM. Already on the user's known-issues list; verified empirically.

### R-MED-3 — `MigrationLock` shutdown handler captures `$this` by reference; signal handler calls `exit()` inside (MEDIUM)

**Evidence:** `packages/migration/src/Runner/MigrationLock.php`:

- Line 282: `\register_shutdown_function(function (): void { $this->release(); });` — captures `$this`, preventing GC until process exit. Acceptable trade-off (lock holders are short-lived CLI invocations).
- Line 305: inside the signal handler, `exit(128 + $signal);` after `release()`. PHP runs `register_shutdown_function` callbacks after `exit()`, but **only the FIRST exit** is honored; subsequent `exit()` calls in chained shutdown functions are no-ops. The release path is idempotent (line 222 — early return on null handle), so this is safe.

**Edge case:** A `pcntl_signal` handler that calls `exit()` triggers an immediate orderly shutdown which means:
1. Signal received.
2. Handler runs: `release()` flushes flock + unlinks file.
3. `exit(128+$signal)` runs.
4. Shutdown function fires: `release()` early-returns.

No issue when SIGTERM arrives at a normal moment. **TOCTOU window** exists between `flock(LOCK_UN)` and `unlink($lockPath)` (`release()` lines 230, ~232) — another process could acquire the lock at the same path right after `LOCK_UN`, and we'd then unlink THEIR lock file. The `@unlink()` is best-effort and a fresh `acquire()` in the racing process would re-create the file before writing PID, so the visible effect is "the racing process's lock file briefly disappears" — but the OS-level flock is honored throughout, so no double-acquire is possible. Operator-visible artefact only.

**Why MEDIUM, not LOW:** The race is real (`@unlink` after `LOCK_UN`) and the windows are small but observable on busy hosts. The fix is to NOT unlink the lock file at all — leave it as a stable path that everyone fopens. Operators who want "is anyone running" check `flock` rather than file existence.

**Recommended action:** Remove the `@unlink($this->lockPath)` from `MigrationLock::release()`. The lock file is a synchronisation primitive, not a queue artifact; deleting it serves no purpose and creates the race.

**Severity:** MEDIUM (limited blast radius — the race is observable but does not corrupt locks).

### R-LOW-1 — Reference `CsvSource` opens file with `@\fopen()` (LOW)

**Evidence:** `packages/migration/tests/Fixtures/CsvSource.php:102`:
```php
$handle = @\fopen($this->filePath, 'rb');
```

The `@` suppresses the warning. If `$this->filePath` is invalid the handle is false and the next call (`fgetcsv($handle)`) will fail with a different, less actionable error.

`CsvSource` is `tests/Fixtures/` — autoload-dev only — so this is not on the production stable surface. Severity is therefore LOW.

**Recommended action:** Drop the `@`; raise a typed exception when `fopen()` fails. Improves DX for source-reader-package authors who copy this as their starter implementation.

**Severity:** LOW.

### Adversarial pass (boundary clauses, silent failures, dead code)

**Boundary "MUST NOT" clauses audited:**

- FR-008 plugin-id collision MUST fail at boot — `PluginRegistry::register()` throws; verified by unit test.
- FR-015 cycles MUST raise typed exception — `CycleDetector` DFS implementation; verified.
- FR-017 migration-id collision -> `MigrationPluginCollisionException` — reuses plugin-collision exception; verified.
- FR-061 second `import:run` MUST be prevented — `flock(LOCK_EX|LOCK_NB)` returns false on second acquire; verified by integration test.

**Silent-failure scan:** `try ... catch ... return ""` / `catch ... }`:

- `RollbackWalker::rollback()` lines 110–122 captures per-record errors into `RollbackReport::$errors` — this is INTENTIONAL best-effort (FR-044). The errors-cap at `ERROR_CAP = 100` silently drops the tail; `$failed > count($errors)` carries the count. **Documented and tested.**
- `MigrationLock::release()` line 232: `@unlink($this->lockPath)` — best-effort by design; failure means a lingering empty lock file. See R-MED-3.
- No other `catch ... {}` patterns in the migration package.

**Dead-code scan:** No new `@api` symbol shipped without at least one production callsite or conformance-suite assertion.

**Cross-WP shared-file integration:**
- `CLAUDE.md` orchestration table updated.
- `composer.json` workspace updated.
- `SaveContext.php` — single additive parameter, default-false; unit test covers default + true.
- `MigrationIdMap.php` evolves across WP04 (creation) → WP05 (`transactional` helper) → WP08 (`walkReverseCreationWithKeys`). Each cycle adds methods only; no signature changes.
- `EntityDestination.php` co-owned by WP05 (write/lookup) + WP08 (rollback) + WP10 cycle-2 (stability constant).

All cross-WP shared-file mutations are additive; layer rules enforced by `bin/check-package-layers`.

---

## 8. Security review

| Vector | Surface | Finding |
|---|---|---|
| CLI arg validation | `ImportRunCommand::execute` | Validates non-empty `migration_id`, registry membership, `--limit` numeric. PASS. |
| CLI flag validation | `--limit`, `--halt-on-error`, `--run-id`, `--dry-run` | Type-checked in `buildOptions()`; raises `InvalidArgumentException`. PASS. |
| File-path traversal | `CsvSource::records()` opens `$this->filePath` with `fopen` — autoload-dev test fixture only; not consumed by production code. Source-reader-package authors are responsible for their own path handling in production source plugins. **Not a production-surface issue.** | LOW |
| File locking | `MigrationLock` — see R-MED-3 for `@unlink` TOCTOU on lock-file path. OS-level flock contract is intact. | MEDIUM (see R-MED-3) |
| Signal-handler races | `MigrationLock::installSignalHandlers` — handler calls `release(); exit()`. `release()` is idempotent; shutdown function double-call is a no-op. **No race that corrupts the lock.** | PASS |
| Subprocess execution | `grep -rn 'Process\|proc_open\|exec\|shell_exec' packages/migration packages/cli/src/Command/Import` — no hits. **No subprocess execution in the migration platform.** | PASS |
| HTTP timeouts | No new HTTP clients in the migration package. | PASS (N/A) |
| Credential clearing | N/A — migration is non-network. | PASS (N/A) |
| Logging of secrets | `RecordError::$message` could carry source-field values if a process plugin's exception message includes them. The framework cannot scrub user-supplied process-plugin error messages. **Operator awareness, not a framework defect.** | LOW (advisory) |

**No HIGH or CRITICAL security findings.**

---

## 9. Out-of-scope but noted

- **`PublicSurfaceVerificationTest` pre-existing failure** flagged by WP12 reviewer is unrelated to M-002 (see `tests/Integration/SurfaceMap/PublicSurfaceVerificationTest.php`). Confirmed not introduced by this mission's diff. Out of scope for this review.

---

## 10. Final verdict

**PASS WITH NOTES.**

The mission delivers the substrate it set out to deliver. All 12 WPs are merged; all 62 FRs ship code; the conformance suite, the end-to-end validation, and the charter §5.8 amendment all land. No regressions. The diff is large but disciplined — 21k insertions, 9 deletions, additive-only on stable surface.

Five MEDIUM findings (D-MED-1 surface-map under-registration, D-MED-2 D3-vs-FR-042 ambiguity, R-MED-1 RollbackReport clock-skew, R-MED-2 double-dispatch on revisionable saves, R-MED-3 lock-file unlink TOCTOU) warrant follow-up issues but none block consumption of the substrate by the WordPress source-reader mission. Two LOW findings (D-LOW-1 stale WP07 placeholder comment, R-LOW-1 CsvSource error-suppression) are housekeeping.

Acceptance criterion 5 ("Charter §5.8 amendment with tier/status labels on `public-surface-map.{md,php}`") is **partially met** — charter §5.8 is fully populated, but `public-surface-map.php` lists only 5 of ~30 symbols. This is the most consequential finding (D-MED-1).

---

## 11. Recommended post-merge issues (do not file from this review — operator's call)

1. **`public-surface-map.{md,php}`: add missing §5.8 stable symbols** (D-MED-1).
2. **Reconcile destination-plugin contract C4 vs FR-042** (D-MED-2; reviewer-deferred from WP10 cycle-1).
3. **`RollbackReport`: tolerate sub-second clock skew on WSL** (R-MED-1).
4. **Thread `SaveContext` through `EntityRepository::save()` to deduplicate `BeforeSaveEvent`/`AfterSaveEvent` on revisionable imports** (R-MED-2).
5. **`MigrationLock::release()`: remove `@unlink($lockPath)` to eliminate the post-LOCK_UN TOCTOU window** (R-MED-3).
6. *(Housekeeping)* Update stale "WP07 wires…" comment in `ImportStatusCommand.php` (D-LOW-1).
7. *(Housekeeping)* Drop `@` and throw typed exception in `CsvSource::records()` (R-LOW-1).

---

*End of mission review.*
