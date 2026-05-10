---
work_package_id: WP03
title: Tooling, Envelope, Top 5 & Assembly
dependencies:
- WP01
- WP02
requirement_refs:
- C-001
- C-002
- C-003
- C-004
- C-005
- FR-001
- FR-002
- FR-003
- FR-008
- FR-009
- FR-010
- FR-011
- FR-012
- FR-013
- FR-014
- FR-015
- NFR-003
- NFR-004
- NFR-005
- NFR-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T015
- T016
- T017
- T018
- T019
- T020
- T021
history:
- date: '2026-05-10'
  note: Created during /spec-kitty.tasks.
authoritative_surface: docs/audits/admin-spa-modernization-2026-05-10.md
execution_mode: code_change
mission_id: 01KRA3RV5GPMP178F2VMEX1TW1
mission_slug: admin-spa-modernization-audit-01KRA3RV
owned_files:
- docs/audits/admin-spa-modernization-2026-05-10.md
- kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/working/tooling-section.md
- kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/working/envelope-section.md
- kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/working/top5-section.md
- kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/WP03-tooling-envelope-assembly.md
tags: []
---

# WP03 — Tooling, Envelope, Top 5 & Assembly

## Objective

Produce three remaining sections (tooling, envelope, Top 5) as working files, then
assemble the final audit document at
`docs/audits/admin-spa-modernization-2026-05-10.md` by stitching together the WP01,
WP02, and WP03 working files. File the remaining GitHub issues, back-fill `(#issue)`
links across the assembled doc for all WP01/WP02/WP03 entries, and run validation greps.

## Branch Strategy

- **Planning base branch**: `main`
- **Final merge target**: `main`
- **Execution workspace**: a per-lane worktree allocated by `lanes.json` after
  task finalization. Must include WP01 and WP02 outputs at the time this WP runs.
- **Dependencies**: WP01 (drift section) and WP02 (coverage section) must be merged
  or available in the workspace as working files.

## Context

This is the only WP in this mission with `execution_mode: code_change` — it writes to
`docs/audits/` which is repo-tracked content (not `kitty-specs/`). It still **does not**
modify any file under `packages/admin/` or any backend `packages/` (C-001, C-002).

Read these before starting:
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/spec.md` — full FR/NFR/SC set
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/plan.md` — Acceptance & Validation greps
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/research.md` — decisions 4, 8, 9
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/quickstart.md` — issue template
- WP01 working file: `tasks/working/drift-section.md`
- WP02 working file: `tasks/working/coverage-section.md`

## Subtasks

### T015 — Tooling audit

**Purpose**: Produce Section 3: Dependency / Tooling Staleness as a working file.

**Steps**:
1. Inventory `packages/admin/package.json` dependencies and devDependencies. For each,
   determine the latest stable release (via `npm view <pkg> version` in the sandbox).
   Record current → latest deltas, with major-version risk notes.
2. Run inside `packages/admin/`:
   ```
   npm run build 2>&1
   npx nuxt build --dry-run 2>&1 || true
   npx vue-tsc --noEmit 2>&1 || true
   npm run test -- --reporter=verbose 2>&1 | tail -100
   ```
   via `ctx_execute` so raw output stays in the sandbox. Index deprecation warnings,
   typecheck errors, and test gaps.
3. Audit `packages/admin/tests/` and `packages/admin/e2e/` for coverage gaps — list
   modules under `packages/admin/app/` that have no corresponding test file.
4. Audit `nuxt.config.ts` for modules not adopted (e.g. `@nuxt/eslint`, `@nuxt/image`,
   `@nuxt/fonts`, `@nuxtjs/i18n` if relevant) and DX gaps.
5. Persist findings to `tasks/working/tooling-section.md` with one row per finding:
   `area | current | recommended | classification | size | rationale`.

**Files**:
- `tasks/working/tooling-section.md` (new)

**Validation**:
- [ ] At least one row per dependency in `package.json`'s dependencies/devDependencies.
- [ ] Build/typecheck/test output parsed; any deprecation warning yields a row.
- [ ] Coverage gaps enumerated.

### T016 — Envelope audit

**Purpose**: Produce Section 4: Package Envelope / Boundary as a working file.

**Steps**:
1. `package.json`:
   - Exports map — verify `dist/contracts/`, `dist/adapters/`, `./nuxt` resolve as documented;
     verify `files` array is correct; verify publish posture (is the package even published?
     should it be?).
   - Peer dep and engine constraints.
2. `nuxt.config.ts`:
   - Module list, runtime config shape vs backend env contract (cross-reference admin SPA's
     expected env vars against actual server-side env reads).
   - Proxy rules.
3. `tsconfig.json` + `tsconfig.contracts.json`:
   - Strictness flags (`strict`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, …).
   - Build artifact paths.
4. `dist/contracts/` build pipeline:
   - Does `npm run build:contracts` produce expected output? Is the output gitignored or
     committed?
   - Grep the workspace and known downstream consumers (e.g. `~/dev/waaseyaa.org`,
     `~/dev/minoo/` if accessible, `~/dev/north-cloud/`) for `@waaseyaa/admin` imports.
     Identify zero / single / multiple consumer scenarios.
5. README freshness vs `docs/specs/admin-spa.md`:
   - Does the README reflect the current bootstrap contract, AdminSurface, codified-context
     telemetry, auth phase 2?
6. Directory structure: any obsolete sub-directories under `packages/admin/`?
7. Playwright/Vitest config modernity (e.g. `defineConfig` style, parallelism, reporters).
8. Concluding paragraph: **does the package's current shape (the only JS-only package in
   a PHP monorepo) still make sense?** Propose one of: keep as-is, vendor differently,
   relocate to a sibling repo, ship as pre-built tarball — with rationale and size.
9. Persist to `tasks/working/envelope-section.md` with one row per finding plus the
   concluding recommendation.

**Files**:
- `tasks/working/envelope-section.md` (new)

**Validation**:
- [ ] All eight bullets above are addressed.
- [ ] Concluding monorepo-shape recommendation is present and reasoned.

### T017 — Top 5 follow-up missions selection

**Purpose**: Produce the leader section of the audit — five proposed follow-up missions
ranked and sized.

**Steps**:
1. Collate entries from `tasks/working/drift-section.md`, `tasks/working/coverage-section.md`,
   `tasks/working/tooling-section.md`, `tasks/working/envelope-section.md`.
2. Cluster related entries into candidate follow-up missions (e.g. "Bundle-aware admin
   list/forms"; "Workflows admin Phase 1"; "Admin SPA dependency bump 2026-Q3"; "Package
   envelope re-shape"; "Coverage gap Phase 1 — AI/agentic surfaces").
3. Score each candidate: `blast_radius` (count of audit entries it would close),
   `prerequisites` (does another candidate need to land first), `size` per rubric.
4. Rank by the tuple `(blast_radius_desc, prerequisites_first, size_asc_at_tie)` per
   `research.md` decision 9. Pick the top 5.
5. For each of the five, write a name, scope summary (3–5 sentences), covered audit
   entries (back-link by anchor), size, ordering note, inter-mission dependencies.
6. Persist to `tasks/working/top5-section.md`.

**Files**:
- `tasks/working/top5-section.md` (new)

**Validation**:
- [ ] Exactly five missions named.
- [ ] Each has scope, covered entries, size, ordering, dependencies.

### T018 — Assemble final audit document

**Purpose**: Stitch all working files into the single deliverable at
`docs/audits/admin-spa-modernization-2026-05-10.md`.

**Document order** (strict):
1. Title + metadata block (date, mission link, sizing rubric definition).
2. Top 5 Follow-up Missions section (from `top5-section.md`).
3. Section 1: Framework Alignment Drift (from `drift-section.md`).
4. Section 2: Feature Coverage Gaps (from `coverage-section.md`).
5. Section 3: Dependency / Tooling Staleness (from `tooling-section.md`).
6. Section 4: Package Envelope / Boundary (from `envelope-section.md`).
7. UX / Visual Polish — Deferred (one-paragraph stub per FR-011).
8. Out of Scope (per FR-014).
9. Appendix: Citation conventions, classification rubrics, methodology pointer to
   `kitty-specs/admin-spa-modernization-audit-01KRA3RV/research.md`.

**Steps**:
1. Compose the doc by concatenating working files in the order above, harmonizing column
   shape across the four axis tables (every row has the same column set: `classification |
   citation(s) | files | size | proposed remedy | tracking_issue`).
2. Add the front-matter metadata block; include the sizing rubric at the top so every
   reader interprets sizes identically.
3. Add anchors (`<a name="drift-entity"></a>` style) at each entry so GitHub issues can
   back-link.
4. Write `docs/audits/admin-spa-modernization-2026-05-10.md`.

**Files**:
- `docs/audits/admin-spa-modernization-2026-05-10.md` (new)

**Validation**:
- [ ] All nine sections present in order.
- [ ] Top 5 section appears before Section 1.
- [ ] Column shape is uniform across axis tables.
- [ ] Anchors present for every entry.

### T019 — File remaining GitHub issues

**Purpose**: Create tracking issues for tooling, envelope, and Top 5 missions; WP01 and
WP02 already filed their own.

**Steps**:
1. For each actionable row in `tasks/working/tooling-section.md` and
   `tasks/working/envelope-section.md`, run `gh issue create` per the template in
   `quickstart.md`. Track default per `research.md` decision 8 (Track 1 unless agentic).
2. For each of the five Top 5 missions, file an "umbrella" issue titled
   `[admin-spa] Mission: <name>` with the scope summary as body and `--label
   admin-spa,top5-mission`.
3. Record issue numbers in the respective working files.

**Files**:
- `tasks/working/tooling-section.md` (extended)
- `tasks/working/envelope-section.md` (extended)
- `tasks/working/top5-section.md` (extended)

**Validation**:
- [ ] Every actionable tooling and envelope row has `tracking_issue: #N`.
- [ ] All five Top 5 missions have umbrella issues.

### T020 — Back-fill audit doc with issue links

**Purpose**: Bidirectional traceability per NFR-005.

**Steps**:
1. For every actionable row across all four axis sections and the Top 5 section in
   `docs/audits/admin-spa-modernization-2026-05-10.md`, append `(#N)` next to the entry
   using the row's tracking_issue column from the working files.
2. For every GitHub issue (including those filed by WP01 and WP02), confirm the issue
   body links to the audit doc anchor that spawned it. If not, edit the issue via
   `gh issue edit --body-file -` to add the link.

**Files**:
- `docs/audits/admin-spa-modernization-2026-05-10.md` (extended)

**Validation**:
- [ ] `grep -E '\(#[0-9]+\)' docs/audits/admin-spa-modernization-2026-05-10.md | wc -l`
      matches the actionable-row count.
- [ ] Three random sampled issues link back to the audit doc.

### T021 — Mission self-review + final validation

**Purpose**: Confirm all FR/NFR/SC are satisfied and run the validation greps from `plan.md`.

**Steps**:
1. Run validation greps from `plan.md` "Acceptance & Validation":
   - audit doc exists and has all required sections
   - every row has ≥5 columns
   - every actionable row has commit hash, `#NNNN`, or `no-op` rationale
   - every row has a size token from `{XS, S, M, L}`
   - `git diff main -- packages/` is empty for this mission's commits
   - Top 5 heading precedes Section 1 heading
2. Run completeness counts:
   - NFR-001: drift coverage ≥ 90% per package — count `no-op`-batched + per-row entries
     vs `git log --first-parent main` count
   - NFR-002: coverage walk = 100% of orchestration-table subsystems
3. Confirm Success Criteria SC-001 through SC-006 are met by reading the audit doc's
   Top 5 section and confirming a maintainer could pick the next mission within
   10 minutes.
4. If any check fails: fix, re-run.

**Files**: none modified beyond fixes.

**Validation**:
- [ ] All `plan.md` validation greps pass.
- [ ] All FR-001 through FR-015 satisfied.
- [ ] All NFR-001 through NFR-006 satisfied.
- [ ] All SC-001 through SC-006 satisfied.

## Definition of Done

- `docs/audits/admin-spa-modernization-2026-05-10.md` exists with all required sections.
- Every entry across the four axis sections and Top 5 section carries a tracking issue
  link.
- All Top 5 follow-up missions have umbrella issues.
- All validation greps from `plan.md` pass.
- `git diff main -- packages/` is empty for this mission.

## Reviewer Guidance

- Read the Top 5 section first. Can you sequence the next quarter's admin SPA work in
  under 10 minutes? If yes, SC-001 is met.
- Spot-check three random rows from each axis section. Citations and file paths must
  resolve.
- Verify column shape is uniform across the four axis tables.
- Confirm `git diff main -- packages/` is empty (only `docs/audits/…` and
  `kitty-specs/…` should be touched).
- Confirm three random GitHub issues link back to the audit doc.

## Implementation command

```bash
spec-kitty agent action implement WP03 --agent <name>
```
