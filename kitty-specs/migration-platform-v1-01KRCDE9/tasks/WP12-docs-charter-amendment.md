---
work_package_id: WP12
title: Documentation + charter §5.8 amendment
dependencies:
- WP04
- WP06
- WP09
- WP11
requirement_refs:
- FR-056
- FR-057
- FR-058
- FR-059
- FR-060
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T062
- T063
- T064
- T065
- T066
- T067
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "31385"
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: docs/specs/migration-platform.md
execution_mode: planning_artifact
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- docs/specs/migration-platform.md
- docs/extension-authoring/migration-source-readers.md
- docs/extension-authoring/migration-process-plugins.md
- docs/cookbook/migration-first-cut.md
- docs/upgrades/waaseyaa-alpha-X-to-Y.md
- docs/specs/stability-charter.md
- CLAUDE.md
- docs/specs/public-surface-map.md
priority: p1
tags:
- docs
- charter
- planning-artifact
---

# WP12 — Documentation + charter §5.8 amendment

## Objective

Close the mission with the documentation required for sustainability: a canonical subsystem spec (`docs/specs/migration-platform.md`), two extension-authoring guides, the first-cut cookbook, the upgrade-guide entry for the alpha that ships the platform, the charter §5.8 amendment listing all stable surface symbols, and the CLAUDE.md / public-surface-map updates that integrate the new package into project memory.

This is a `planning_artifact` WP — no code under `packages/`. All work is markdown edits, with one exception: `public-surface-map.md` is a doctrine artifact updated when stable surface lands (this WP records its entries).

## Dependencies

- Internal: WP04 (id-map schema is documented), WP06 (CLI surface), WP09 (operator recovery path for stale locks). Effectively all of WP01–WP11 must be merged before WP12's docs can accurately reflect what shipped — schedule this as the last WP in the mission.
- External: None.
- Charter anchors: this WP delivers the §5.8 amendment itself.

## Scope (in / out)

**In scope**
- `docs/specs/migration-platform.md` — canonical subsystem spec (FR-056). Replaces the draft `kitty-specs/.../spec.md` as the lived-in document going forward.
- `docs/extension-authoring/migration-source-readers.md` — guide for source-reader package authors (FR-057). Walks through `SourcePluginInterface`, `SourceConformanceTestCase`, packaging conventions.
- `docs/extension-authoring/migration-process-plugins.md` — guide for process-plugin authors (FR-058). Walks through `ProcessPluginInterface`, naming conventions (non-reserved id prefix), chain composition.
- `docs/cookbook/migration-first-cut.md` — "Writing your first migration" recipe (FR-060). Walks through the WP11 reference scenario from an operator's perspective.
- `docs/upgrades/waaseyaa-alpha-X-to-Y.md` — upgrade-guide entry for the alpha that ships the migration platform (FR-059). Replace `X` and `Y` with the actual alpha numbers at implementation time (`git describe` plus the next-tag policy from `feedback_internal_version_sweep_mechanism.md`).
- `docs/specs/stability-charter.md` — add §5.8 listing all stable-surface symbols delivered by this mission (per spec §4).
- `CLAUDE.md` — orchestration table row for `packages/migration/*` and Layer 3 row update.
- `docs/specs/public-surface-map.md` — if the file exists at merge time, add the migration symbols with `tier: stable` and `mission-status: present`. If it doesn't exist, skip this file from owned_files at execution time (the file is created by a separate doctrine track).

**Out of scope**
- Doctrine assets under `kitty-specs/migration-platform-v1-01KRCDE9/` — those stay as the mission's planning history.
- New code under `packages/`.

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Per-lane worktree. Run `spec-kitty agent action implement WP12 --agent opus`.

## Implementation guidance

### Subtask T062 — `docs/specs/migration-platform.md` canonical spec

**Purpose**: The lived-in subsystem spec — what every future agent reads when working on `packages/migration/*`.

**FRs covered**: FR-056.

**Files**:
- `docs/specs/migration-platform.md` (new, ~600–800 lines).

**Steps**:
1. Front matter: `<!-- Spec reviewed YYYY-MM-DD - migration-platform-v1 mission landed -->` (CLAUDE.md feedback_drift_detector_review_stamp).
2. Sections:
   1. **Overview** — what the platform does, where it sits (Layer 3), when to use it. 2–3 paragraphs.
   2. **Stable surface** — table mirroring spec §4 with every public symbol, its FQCN, and a one-line description.
   3. **Plugin contracts** — full interface signatures with example implementations.
   4. **Manifest format** — `MigrationDefinition` shape + the process-map shapes.
   5. **Storage** — `migration_id_map` schema; `migration_run_state` table marked clearly as internal infrastructure.
   6. **CLI** — six commands; exit codes; flag semantics.
   7. **Discovery** — provider capability + filesystem path.
   8. **Boot sequence** — WP01/WP02 registration steps.
   9. **EntityDestination** — write/rollback/lookup paths (the §7 of spec.md, re-grounded in the merged code).
   10. **Conformance** — `SourceConformanceTestCase` + `DestinationConformanceTestCase`; how third parties subclass and pass.
   11. **Concurrency + recovery** — lock semantics, stale-lock recovery (`rm <path>`), pcntl behavior, Windows degradation.
   12. **Error model** — eight exception types + their codes.
   13. **Charter mapping** — §5.8 list; link to charter spec.
   14. **Operations playbook** — link to `docs/specs/operations-playbooks.md` if/when those entries land.
   15. **Related ADRs** — link 010, 011, 012a, 016.
   16. **History** — link `kitty-specs/migration-platform-v1-01KRCDE9/` as the planning archive.
3. Pattern after `docs/specs/entity-system.md` and `docs/specs/access-control.md` for tone, depth, and section ordering — verify by reading those files at implementation time.

**Validation**:
- [ ] `tools/drift-detector.sh` (or equivalent) reports the spec as fresh.
- [ ] Spec citations (`@spec FR-001` etc.) in `packages/migration/src/**.php` can be reverse-resolved into this document.

### Subtask T063 — `docs/extension-authoring/migration-source-readers.md`

**Purpose**: The guide for authors of packages like `waaseyaa-migrate-source-wordpress`.

**FRs covered**: FR-057.

**Files**:
- `docs/extension-authoring/migration-source-readers.md` (new, ~400 lines).

**Steps**:
1. Sections:
   1. **What is a source reader** — a composer package shipping one or more `SourcePluginInterface` implementations.
   2. **Package skeleton** — `composer.json` with `require: { waaseyaa/migration: "^X.Y" }`, `extra.waaseyaa.providers`, the `Testing/` autoload-dev pattern.
   3. **The SourcePluginInterface contract** — verbatim from the spec, with annotations.
   4. **Stable-ID semantics** — how `sourceIdFor()` returns a `SourceId`; why determinism matters; the canonical-form hash.
   5. **Streaming** — `records()` MUST be a generator; the 50 MB conformance check.
   6. **Discovery** — `HasMigrationsInterface` + `HasMigrationPluginsInterface` registration.
   7. **Conformance** — subclass `SourceConformanceTestCase`, implement three factory methods, pass all eight gates.
   8. **Packaging + naming** — composer package name convention (`waaseyaa-migrate-source-<format>`), version pinning to a `waaseyaa/migration` major.
   9. **Operator-facing recovery** — stale-lock recovery path; how to read `import:status` output.
   10. **Worked example** — the `XmlLineSource` from `quickstart.md` §B, adapted as a complete sample.
2. Mark the document with `@api`-tier guidance (decisions in this guide form the contract third parties depend on).

**Validation**:
- [ ] Cross-link with `docs/specs/migration-platform.md`.
- [ ] All sample code compiles (paste into a throwaway scratch package + verify).

### Subtask T064 — `docs/extension-authoring/migration-process-plugins.md`

**Purpose**: Guide for authors of custom process plugins (e.g. `WordPressShortcodeStrip`).

**FRs covered**: FR-058.

**Files**:
- `docs/extension-authoring/migration-process-plugins.md` (new, ~300 lines).

**Steps**:
1. Sections:
   1. **What is a process plugin** — a class implementing `ProcessPluginInterface` that transforms one source value into a destination value.
   2. **The contract** — three methods, with example.
   3. **Reserved ids + naming convention** — `pass_through`, `html_sanitize`, etc. are reserved; non-reserved authors use `<vendor>_<purpose>` (e.g. `wordpress_shortcode_strip`).
   4. **Chains** — array order is execution order; the runner threads outputs to inputs.
   5. **`ProcessContext`** — what's in it; how to use `$context->lookup` for cross-migration references.
   6. **Stability** — when to mark a plugin `'experimental'`; how the first-use deprecation notice surfaces.
   7. **Testing** — unit-test patterns; how to mock `ProcessContext`.
   8. **Worked example** — a custom `UppercaseFirstWordProcessor` walked through end to end.

**Validation**:
- [ ] All sample code compiles.

### Subtask T065 — `docs/cookbook/migration-first-cut.md`

**Purpose**: The "Writing your first migration" recipe — operator-facing, narrative.

**FRs covered**: FR-060.

**Files**:
- `docs/cookbook/migration-first-cut.md` (new, ~350 lines).

**Steps**:
1. Walk through the WP11 reference scenario (CSV → `migration_test_widget`) from an app-author's perspective:
   - Step 1: install `waaseyaa/migration`.
   - Step 2: write a `MigrationDefinition` (paste the WP11 fixture, simplified).
   - Step 3: register via a `ServiceProvider`.
   - Step 4: run `bin/waaseyaa import:run users_csv_to_widgets`.
   - Step 5: inspect status.
   - Step 6: simulate interruption + resume.
   - Step 7: rollback to start fresh.
2. Include screenshots of CLI output (ASCII tables — no images).
3. Cross-link the two author guides and the canonical spec.

**Validation**:
- [ ] A new contributor can follow the recipe end to end without referring to other docs.

### Subtask T066 — `docs/upgrades/waaseyaa-alpha-X-to-Y.md`

**Purpose**: Operator-facing upgrade notes for the alpha that ships migration platform.

**FRs covered**: FR-059.

**Files**:
- `docs/upgrades/waaseyaa-alpha-X-to-Y.md` (new, ~180 lines). At implementation time, determine the current alpha tag via `git describe --tags --abbrev=0 --match='v*.*.*'` and the next alpha via the release-cut policy; rename the file accordingly (e.g. `waaseyaa-alpha-178-to-179.md`).

**Steps**:
1. Sections (pattern after existing upgrade-guide entries; check `docs/upgrades/` for templates):
   1. **What's new** — Migration platform substrate.
   2. **New stable surface** — link to charter §5.8.
   3. **Migration steps for consumer apps** — `composer require waaseyaa/migration:^X.Y.Z`; `composer dump-autoload --optimize`; run the new schema migrations (`bin/waaseyaa migrate:up`).
   4. **Backward compatibility** — additive only; `SaveContext::isImport()` extension preserves existing call sites (default `false`).
   5. **Smoke test** — run the conformance suite against `EntityDestination` to verify install.
   6. **Common questions** — three or four anticipated questions (e.g. "do I need to write migrations now?" — no, the platform ships ready for source-reader packages, which will ship separately).
2. Cross-link to `CHANGELOG.md` and the canonical spec.

**Validation**:
- [ ] File path matches the actual alpha range at merge time.
- [ ] Migration commands listed are exact (verified against the runner output).

### Subtask T067 — Charter §5.8 amendment + CLAUDE.md update + public-surface-map

**Purpose**: Codify the stable surface in the charter and integrate the new package into project memory.

**FRs covered**: charter §5.8 (proposed), CLAUDE.md orchestration integration.

**Files**:
- `docs/specs/stability-charter.md` (modify — add §5.8).
- `CLAUDE.md` (modify — orchestration table row + Layer 3 entry).
- `docs/specs/public-surface-map.md` (modify if present; skip if absent).

**Steps**:
1. **Charter §5.8 — "Migration platform"** (new section):
   - Insert after §5.7 (or wherever the existing numbering leaves a gap; verify at implementation time).
   - Table of stable-surface symbols mirroring spec §4:
     - `SourcePluginInterface`, `ProcessPluginInterface`, `DestinationPluginInterface`
     - `HasMigrationPluginsInterface`, `HasMigrationsInterface`
     - `MigrationDefinition`
     - `EntityDestination`
     - Six process plugin concretes (`PassThroughProcessor`, ..., `DefaultValueProcessor`)
     - `SourceId`
     - DTO value objects (`SourceRecord`, `DestinationRecord`, `WriteResult`, `ProcessContext`)
     - `migration_id_map` schema
     - Eight exceptions
     - `SaveContext::isImport()` (extension to §5.3 — link from §5.8 back to §5.3 with note "added by M-002")
     - Six CLI commands
     - Two conformance test bases
     - `migration.deprecation` log channel
   - Stability commitments per the charter's existing tier definitions.
2. **CLAUDE.md orchestration table** — add a new row:
   ```
   | `packages/migration/*` | — | `docs/specs/migration-platform.md` |
   ```
   Insert in lexicographic order with the other `packages/*` rows.
3. **CLAUDE.md Layer Architecture table** — add the new package to Layer 3:
   ```
   | 3 | Services | workflows, search, seo, notification, billing, github, migration, northcloud |
   ```
4. **CLAUDE.md gotchas** — verify the existing migration-related gotchas (e.g. "Migration system boot order", "MakeMigrationCommand requires \$projectRoot") still hold; if WP04's migration files surface any new gotcha, add a bullet.
5. **`docs/specs/public-surface-map.md`** — if the file exists, add the migration symbols with `tier: stable` and `mission-status: present`. Skip if the file isn't present in the tree.

**Validation**:
- [ ] Charter §5.8 lists every symbol from spec §4 — verify by cross-grep:
  ```
  rg -hoE 'Waaseyaa\\\\Migration\\\\[A-Za-z]+' docs/specs/stability-charter.md
  ```
- [ ] CLAUDE.md table row passes the project's manual review.
- [ ] `tools/drift-detector.sh` reports `docs/specs/migration-platform.md` as fresh.
- [ ] `bin/check-package-layers` still clean (no code changes, but layers reference CLAUDE.md table).

**Edge cases**:
- If `docs/specs/public-surface-map.md` is missing AND `public-surface-map.php` exists, the source-of-truth is the PHP file — file a follow-up to update it consistently with the markdown.

## Tests

- **Unit**: none.
- **Integration**: none.
- **Conformance**: none.
- **Doc validation**: `tools/drift-detector.sh` (or equivalent) clean for all touched specs. `bin/check-milestones` clean if the WP touches GitHub issues (it does not).

## Definition of Done

- [ ] All six subtasks complete.
- [ ] All five FRs (FR-056..FR-060) cited in the respective documents.
- [ ] Charter §5.8 amendment lands — read by `bin/check-composer-policy`-equivalent doctrine checks if they exist.
- [ ] CLAUDE.md orchestration table includes the `packages/migration/*` row.
- [ ] CLAUDE.md Layer Architecture table includes `migration` in Layer 3.
- [ ] `docs/specs/migration-platform.md` exists and is `tools/drift-detector.sh` clean.
- [ ] Both extension-authoring guides exist and contain compilable sample code.
- [ ] `docs/cookbook/migration-first-cut.md` walks through the WP11 reference scenario.
- [ ] `docs/upgrades/waaseyaa-alpha-<X>-to-<Y>.md` exists with the correct alpha range.
- [ ] `composer cs-check` clean — even doc-only WPs should pass (markdown isn't checked, but any source edits triggered by WP05's `SaveContext` are honoured upstream).
- [ ] `./vendor/bin/phpunit` full suite still green — no regressions from doc changes.
- [ ] No file under `packages/` is modified by WP12.

## Risks

- **R1 — Alpha version drift between planning and merge**: the upgrade-guide filename `waaseyaa-alpha-X-to-Y.md` depends on the current tag at merge time. Mitigate by determining the alpha at implementation start AND verifying again at PR-up time.
- **R2 — Charter §5.8 numbering collision**: if another mission lands a §5.8 in parallel, renumber via a follow-up. Mitigate by checking charter HEAD immediately before merging.
- **R3 — Sample code in author guides bit-rots**: documented samples diverge from real interfaces. Mitigate by copy-pasting from the actual M-002 source (the WP11 fixture is the canonical example).
- **R4 — Public surface map missing**: documented as conditional; skip the file if absent. Verify at implementation time.

## Reviewer guidance

- Check: every section of `docs/specs/migration-platform.md` cross-links to the actual code path (FQCN + file path).
- Check: charter §5.8 lists EVERY symbol from spec §4 (use `rg` to verify).
- Check: CLAUDE.md orchestration row uses repo-relative paths.
- Check: sample code in the two author guides actually compiles against the merged framework.
- Check: upgrade-guide alpha range matches `git describe` at PR-up time.
- Check: `SaveContext::isImport()` is referenced from §5.3 of the charter (cross-link from §5.8).
- Verify: `tools/drift-detector.sh` reports no staleness across the touched specs.
- Verify: WP12 does not modify any file under `packages/` — `git diff --name-only main...HEAD | grep '^packages/'` must return empty.
- Confirm: the spec is reviewed-stamped (front-matter comment) per CLAUDE.md feedback_drift_detector_review_stamp.

## Activity Log

- 2026-05-13T17:01:50Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=29472 – Started implementation via action command
- 2026-05-13T17:12:32Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=29472 – Ready for review — docs + charter amendment complete: new docs/specs/migration-platform.md (FR-056), docs/extension-authoring/migration-source-readers.md (FR-057), docs/extension-authoring/migration-process-plugins.md (FR-058), docs/cookbook/migration-first-cut.md (FR-060), docs/upgrades/waaseyaa-alpha-177-to-178.md (FR-059), stability-charter.md §5.8 amendment, CHANGELOG [Unreleased] bullet, CLAUDE.md orchestration row + Layer 3 entry. Lane commit 1682346fc on main. Validation: composer policy / package layers / cs-check / phpstan all clean. phpunit full suite has 1 pre-existing failure (PublicSurfaceVerificationTest — stale entry from M-006 squash 0f7e1809a, untouched by WP12).
- 2026-05-13T17:13:07Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=31385 – Started review via action command
- 2026-05-13T17:15:51Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=31385 – Approved cycle-1. 5 new doc files (migration-platform.md 544 lines, source-readers 417, process-plugins 290, cookbook 330, upgrade-guide 253) + charter §5.8 amendment (100 lines, cleanly partitioned public stable surface vs internal infrastructure) + CHANGELOG [Unreleased] Added bullet (M-002 covering all WPs) + CLAUDE.md orchestration row 'packages/migration/* -> docs/specs/migration-platform.md' + Layer 3 services list updated to include migration. Charter §5.8 lists stable: SourcePluginInterface/ProcessPluginInterface/DestinationPluginInterface, HasMigrationsInterface/HasMigrationPluginsInterface, MigrationDefinition, SourceId/SourceRecord/DestinationRecord/WriteResult/ProcessContext, EntityDestination, 6 reserved processors, 8 exception types, 6 CLI commands + exit codes, SourceConformanceTestCase/DestinationConformanceTestCase, migration.deprecation channel, migration_id_map schema. Charter §5.8 explicitly marks INTERNAL: MigrationRunner, MigrationLock, RollbackWalker, MigrationIdMap class, MigrationRunState class, PluginRegistry/MigrationRegistry/CycleDetector/DependencyGraph, withRunId(), Canonical\CanonicalForm, migration_run_state table, lock file format. SaveContext::isImport() documented as additive §5.3 extension with cross-link. FQCNs cross-checked against lane-a HEAD 3b6e9af3c (all 15 sampled symbols resolved including Channels::MIGRATION_DEPRECATION='migration.deprecation'). Upgrade-guide filename waaseyaa-alpha-177-to-178.md correct (current tag v0.1.0-alpha.177). Gates: cs-check clean, phpstan 1254 files OK, check-composer-policy OK, check-package-layers OK. The 1 phpunit failure (PublicSurfaceVerificationTest::surface_map_contains_no_stale_entries referencing Waaseyaa\Entity\Testing\Translation\TranslatableEntityContractTest) is pre-existing — confirmed empirically by re-running at base commit 230cf42f4 (same failure, same FQCN, M-006 squash 0f7e1809a last touched docs/public-surface-map.php). Out-of-scope follow-up; not introduced by WP12. Tradeoffs accepted: (1) waaseyaa-alpha-177-to-178.md correct per current tag, (2) docs/specs/public-surface-map.md skip authorized by canonical T067 edge case (file doesn't exist; .php is M-006 source-of-truth — orthogonal follow-up), (3) no separate operator guide; cookbook + spec §3.9/§8 cover operator path. Mission M-002 ready for spec-kitty merge.
