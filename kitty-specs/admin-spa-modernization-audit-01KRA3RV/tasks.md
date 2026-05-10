# Tasks: Admin SPA Modernization Audit

**Mission**: `admin-spa-modernization-audit-01KRA3RV` (`01KRA3RV5GPMP178F2VMEX1TW1`)
**Mission type**: research
**Branch**: main → main
**Generated**: 2026-05-10

## Overview

Three work packages produce one audit document (`docs/audits/admin-spa-modernization-2026-05-10.md`) and one set of milestone-tagged GitHub follow-up issues. WP01 and WP02 are independent and parallelizable; WP03 assembles the final document and Top 5 section, and depends on both.

## Subtask Index

| ID | Description | WP | Parallel |
|----|-------------|----|----------|
| T001 | Build drift corpus per backend package via `ctx_batch_execute` | WP01 | [P] |
| T002 | Cross-reference each commit against admin SPA file impact | WP01 | |
| T003 | Classify each drift candidate `{broken, degraded, unsurfaced, no-op}` + size | WP01 | |
| T004 | Detect in-flight overlap via `gh pr/issue list`; annotate entries | WP01 | |
| T005 | Compile Section 1 draft to `tasks/working/drift-section.md` | WP01 | |
| T006 | File one GitHub issue per non-`no-op` drift entry; record issue numbers | WP01 | |
| T007 | WP01 self-review against NFR-001 / NFR-003 / FR-005 | WP01 | |
| T008 | Extract subsystem list from `CLAUDE.md` orchestration table | WP02 | [P] |
| T009 | Classify each subsystem `{no-UI, minimal-UI, complete-UI}` via SPA grep | WP02 | |
| T010 | Draft proposed admin surface paragraph + size for non-`complete-UI` entries | WP02 | |
| T011 | Flag `orchestration-table-orphan` packages | WP02 | |
| T012 | Compile Section 2 draft to `tasks/working/coverage-section.md` | WP02 | |
| T013 | File GitHub issues for non-`complete-UI` entries; record issue numbers | WP02 | |
| T014 | WP02 self-review against NFR-002 / NFR-003 / FR-006 / FR-007 | WP02 | |
| T015 | Tooling audit (versions, deprecations, lint/typecheck/test gaps) → `tasks/working/tooling-section.md` | WP03 | |
| T016 | Envelope audit (manifest, exports, dist/contracts, README, structure, monorepo shape) → `tasks/working/envelope-section.md` | WP03 | |
| T017 | Top 5 follow-up missions selection (rank by blast-radius, prerequisites, size) → `tasks/working/top5-section.md` | WP03 | |
| T018 | Assemble final `docs/audits/admin-spa-modernization-2026-05-10.md` from working files + new sections + Top 5 leader + UX stub + sizing rubric + Out of Scope | WP03 | |
| T019 | File GitHub issues for tooling, envelope, and Top 5 missions; record numbers | WP03 | |
| T020 | Back-fill audit doc with `(#issue)` links for all WP01/WP02/WP03 entries | WP03 | |
| T021 | Mission self-review against all FR/NFR/SC; run plan.md validation greps | WP03 | |

`[P]` in the table marks parallel-safe entry points within a WP. Tracking lives in the per-WP checkbox sections below; the index table above is reference only.

---

## WP01 — Drift Inventory

**Goal**: Produce Section 1 (Framework Alignment Drift) of the audit doc as a working file plus filed GitHub issues. Each drift entry cites at least one commit/issue/PR, names affected admin SPA file paths, carries a classification from `{broken, degraded, unsurfaced, no-op}`, and a size in `{XS, S, M, L}`.

**Priority**: P1 (audit MVP — without this, follow-up missions can't be scoped)
**Independent test**: `tasks/working/drift-section.md` exists, contains ≥1 entry per backend package in the corpus, every actionable entry carries citation + classification + size, and `gh issue list --milestone "Track 1" --milestone "Track 2"` lists matching tracking issues.
**Estimated prompt size**: ~360 lines (7 subtasks × ~50 lines)
**Dependencies**: none.

**Included subtasks**:
- [ ] T001 Build drift corpus per backend package via `ctx_batch_execute` (WP01)
- [ ] T002 Cross-reference each commit against admin SPA file impact (WP01)
- [ ] T003 Classify each drift candidate + size (WP01)
- [ ] T004 Detect in-flight overlap via `gh pr/issue list` (WP01)
- [ ] T005 Compile Section 1 draft to `tasks/working/drift-section.md` (WP01)
- [ ] T006 File GitHub issue per non-`no-op` drift entry (WP01)
- [ ] T007 WP01 self-review against NFR-001 / NFR-003 / FR-005 (WP01)

**Implementation sketch**:
1. Per backend package run `git log --first-parent main --oneline -- packages/<pkg>` and indexed `git log --first-parent main --name-only --pretty=format:'%h %s'`. Aggregate via `ctx_batch_execute` to keep raw output in sandbox.
2. For each commit grep admin SPA for type names, route paths, JSON:API attribute keys, and component imports that the commit touched. Either the grep finds a match (potential drift) or it doesn't (likely `no-op`).
3. Apply rubric from `research.md` decision 2; size with rubric from decision 4.
4. `gh pr list --search 'packages/admin'` and `gh issue list --search 'admin SPA' --state open` for in-flight overlap.
5. Materialize draft to `tasks/working/drift-section.md` with one row per entry: classification, citation, files, size, proposed remedy, in-flight flag.
6. File one issue per non-`no-op` entry using template from `quickstart.md`. Default Track 1; agentic-area entries (`telescope`) may go Track 2.
7. Self-review: every actionable row has citation + classification + size; ≥90% of in-window commits per package are addressed; in-flight entries linked.

**Parallel opportunities**: T001 fans out per package (one `ctx_batch_execute` call per package or grouped). T006 fans out per entry.

**Risks**:
- Corpus too large to classify individually → use commit-message grouping for refactor-only commits and batch-classify as `no-op` with a single rationale (allowed by NFR-001 phrasing).
- False-negative on `no-op` (real drift missed) → mitigated by Section 2 coverage walk catching unsurfaced capabilities.

---

## WP02 — Coverage Walk

**Goal**: Produce Section 2 (Feature Coverage Gaps) of the audit doc as a working file plus filed GitHub issues. 100% of CLAUDE.md orchestration-table subsystems classified `{no-UI, minimal-UI, complete-UI}`; non-`complete-UI` entries carry a one-paragraph proposed admin surface and a size.

**Priority**: P1 (audit MVP — coverage gaps are half the "out of date" perception)
**Independent test**: `tasks/working/coverage-section.md` exists, contains every subsystem from the CLAUDE.md orchestration table, every non-`complete-UI` row has surface paragraph + size + tracking issue, and any `orchestration-table-orphan` packages are flagged.
**Estimated prompt size**: ~340 lines (7 subtasks × ~48 lines)
**Dependencies**: none.

**Included subtasks**:
- [ ] T008 Extract subsystem list from `CLAUDE.md` orchestration table (WP02)
- [ ] T009 Classify each subsystem `{no-UI, minimal-UI, complete-UI}` (WP02)
- [ ] T010 Draft proposed admin surface paragraph + size for gaps (WP02)
- [ ] T011 Flag `orchestration-table-orphan` packages (WP02)
- [ ] T012 Compile Section 2 draft to `tasks/working/coverage-section.md` (WP02)
- [ ] T013 File GitHub issues for non-`complete-UI` entries (WP02)
- [ ] T014 WP02 self-review against NFR-002 / NFR-003 / FR-006 / FR-007 (WP02)

**Implementation sketch**:
1. Parse `CLAUDE.md` orchestration table (the file-pattern → spec table); enumerate every package referenced in the left column.
2. For each subsystem: grep `packages/admin/app/` for the entity-type id, route prefix, component name, or composable that would surface it. Classify accordingly.
3. `complete-UI` = there is a dedicated admin page or component covering the subsystem's primary operations; `minimal-UI` = touched but incomplete; `no-UI` = nothing in `packages/admin/`.
4. For non-`complete-UI`: write a single-paragraph proposed admin surface (e.g. "Admin > Workflows: list workflow definitions, drill into transitions, dry-run state changes, audit recent transitions"). Size per rubric.
5. `ls packages/ | grep -v -F -f <orchestration_table_packages>` to find orphans; flag.
6. Materialize draft; file issues with Track defaults from `research.md` decision 8.
7. Self-review: 100% subsystem coverage, every row classified, every non-`complete-UI` row has surface + size + issue link.

**Parallel opportunities**: T008 is a single read; T009 fans out across subsystems.

**Risks**:
- Subjective `minimal-UI` vs `no-UI` boundary → pin definition: `minimal-UI` requires the SPA to import or reference the subsystem's entity type in at least one component; otherwise it's `no-UI`.
- Subsystem is itself stub in framework → list under coverage gaps with a note "blocked on backend"; don't file an admin issue unless the backend is ready.

---

## WP03 — Tooling, Envelope, Top 5 & Assembly

**Goal**: Produce Sections 3, 4, UX deferred stub, Top 5 leader, sizing-rubric header, and Out of Scope footer; assemble the final `docs/audits/admin-spa-modernization-2026-05-10.md` from WP01/WP02/WP03 working files; file remaining issues; back-fill all entries with their `(#issue)` links; run validation greps.

**Priority**: P1 (assembles all axes; mission cannot finish without it)
**Independent test**: `docs/audits/admin-spa-modernization-2026-05-10.md` exists, leads with Top 5 section, contains all four axis sections in correct order, every row carries `(#issue)` back-link, validation greps from `plan.md` pass.
**Estimated prompt size**: ~450 lines (7 subtasks × ~64 lines — assembly subtasks carry more detail)
**Dependencies**: WP01, WP02.

**Included subtasks**:
- [ ] T015 Tooling audit → `tasks/working/tooling-section.md` (WP03)
- [ ] T016 Envelope audit → `tasks/working/envelope-section.md` (WP03)
- [ ] T017 Top 5 follow-up missions selection → `tasks/working/top5-section.md` (WP03)
- [ ] T018 Assemble final `docs/audits/admin-spa-modernization-2026-05-10.md` (WP03)
- [ ] T019 File GitHub issues for tooling, envelope, and Top 5 missions (WP03)
- [ ] T020 Back-fill audit doc with `(#issue)` links for all entries (WP03)
- [ ] T021 Mission self-review against all FR/NFR/SC + plan.md validation greps (WP03)

**Implementation sketch**:
1. Tooling: inventory each `packages/admin/package.json` dep against latest stable (use `npm view <pkg> version` via sandbox); run `cd packages/admin && npm run build 2>&1` and parse deprecation warnings; `npm run lint`, `vue-tsc --noEmit`, vitest/playwright coverage reports.
2. Envelope: read package.json (exports, files, publish posture, peers, engines), nuxt.config.ts (modules, runtime config, proxy), tsconfig.json + tsconfig.contracts.json (strictness), check `dist/contracts/` build pipeline (does `build:contracts` produce expected output? are there real consumers? grep waaseyaa.org, minoo, north-cloud for `@waaseyaa/admin` imports), README freshness vs `docs/specs/admin-spa.md`, directory structure, Playwright/Vitest config modernity. Conclude with a one-paragraph monorepo-shape recommendation.
3. Top 5: collate WP01/WP02/WP03 entries, group into proposed follow-up missions, rank by tuple `(blast_radius_desc, prerequisites_first, size_asc_at_tie)`, name and size each, declare dependencies.
4. Assemble: write `docs/audits/admin-spa-modernization-2026-05-10.md`. Order: front matter (date, mission link, sizing rubric) → Top 5 → Section 1 (drift, from WP01 working file) → Section 2 (coverage, from WP02 working file) → Section 3 (tooling) → Section 4 (envelope) → UX deferred stub → Out of Scope → Appendix (citation conventions).
5. File issues for tooling, envelope, and Top 5 missions; record numbers.
6. Back-fill `(#issue)` links across the assembled doc for every actionable row from all three WPs.
7. Self-review: run validation greps from `plan.md` "Acceptance & Validation" — column counts, citation patterns, `Top 5` heading precedes `Section 1`, no `packages/admin/` modifications in the diff.

**Parallel opportunities**: T015, T016, T017 are independent and can run in parallel during execution.

**Risks**:
- WP01/WP02 working files have inconsistent column shape → WP03 must normalize during T018.
- Top 5 selection feels arbitrary → tuple-based ranking from `research.md` decision 9 is the tiebreaker; documented in the audit doc front matter.
- Issue back-fill missing entries → T021 grep validates every actionable row has `(#\d+)`.

---

## Phase / dependency summary

- **Phase 1 (parallel)**: WP01, WP02 — independent corpus and subsystem walks.
- **Phase 2 (final)**: WP03 — depends on WP01 + WP02 working files.

## MVP scope

The mission's MVP equals all three WPs. There is no shippable subset — WP01 alone leaves the coverage axis un-walked; WP01+WP02 alone produces no assembled doc and no Top 5. WP03 is the mission's terminal output.

## Validation gates (per plan.md)

After T021:
1. `docs/audits/admin-spa-modernization-2026-05-10.md` exists and is well-formed markdown.
2. Top 5 section appears before Section 1.
3. Every actionable row contains a commit hash, `#NNNN`, or explicit `no-op` rationale.
4. Every row carries a size from `{XS, S, M, L}`.
5. Every row's tracking issue exists on GitHub with a Track milestone.
6. `git diff main -- packages/` is empty for this mission.

## Next command

`/spec-kitty.analyze` (optional consistency check across spec/plan/tasks) or `/spec-kitty.implement` (start executing WP01/WP02 in parallel).
