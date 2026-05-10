---
work_package_id: WP02
title: Coverage Walk
dependencies: []
requirement_refs:
- C-001
- C-002
- FR-006
- FR-007
- FR-012
- FR-013
- FR-015
- NFR-002
- NFR-003
- NFR-004
- NFR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T008
- T009
- T010
- T011
- T012
- T013
- T014
history:
- date: '2026-05-10'
  note: Created during /spec-kitty.tasks.
authoritative_surface: kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/working/coverage-section.md
execution_mode: planning_artifact
mission_id: 01KRA3RV5GPMP178F2VMEX1TW1
mission_slug: admin-spa-modernization-audit-01KRA3RV
owned_files:
- kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/working/coverage-section.md
- kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/WP02-coverage-walk.md
tags: []
---

# WP02 — Coverage Walk

## Objective

Produce **Section 2: Feature Coverage Gaps** of the audit as a working file at
`kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/working/coverage-section.md`,
and file one GitHub issue per non-`complete-UI` entry with a Track milestone.

Every subsystem in the `CLAUDE.md` orchestration table must be classified
`{no-UI, minimal-UI, complete-UI}`. Non-`complete-UI` entries carry a one-paragraph
proposed admin surface description and a size from `{XS, S, M, L}`. Packages present in
`packages/` but missing from the orchestration table are flagged `orchestration-table-orphan`.

## Branch Strategy

- **Planning base branch**: `main`
- **Final merge target**: `main`
- **Execution workspace**: a per-lane worktree allocated by `lanes.json` after task finalization.

## Context

Audit-only research mission. **No file under `packages/admin/` or any backend package is
modified** (C-001, C-002). Output is one working markdown file and a set of GitHub issues.

Read these before starting:
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/spec.md` — FRs (especially FR-006, FR-007), NFR-002
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/plan.md` — methodology summary, acceptance gates
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/research.md` — decision 3 (coverage walk authority), decision 8 (Track defaults)
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/quickstart.md` — issue template

Source of truth for subsystem inventory: **`CLAUDE.md`** orchestration table.

## Subtasks

### T008 — Extract subsystem list from CLAUDE.md orchestration table [P]

**Purpose**: Build a canonical list of subsystems that the audit must cover.

**Steps**:
1. Read the orchestration table in `CLAUDE.md` (the table mapping file patterns →
   specialist skills → cold-memory specs).
2. Extract every package path referenced in the left column. De-duplicate. Preserve the
   layer (L0–L6) attribution where present.
3. Persist to `tasks/working/coverage-inventory.md` with columns: `package | layer |
   spec_pointer | initial_class_guess`.

**Files**:
- `tasks/working/coverage-inventory.md` (new)

**Validation**:
- [ ] Inventory contains every distinct package path from the orchestration table.
- [ ] No duplicates.

### T009 — Classify each subsystem `{no-UI, minimal-UI, complete-UI}`

**Purpose**: Determine the admin SPA's current coverage of each subsystem.

**Definitions** (pinned per spec assumptions):
- `complete-UI`: SPA has dedicated page(s) or component(s) covering the subsystem's
  primary CRUD or operational concerns. Example: `entity` (full SchemaForm/SchemaList
  pipeline), `user` (auth pages).
- `minimal-UI`: SPA imports or references the subsystem's entity type, route, or
  component in at least one place but does not provide a dedicated surface.
- `no-UI`: nothing in `packages/admin/` references the subsystem.

**Steps**:
1. For each subsystem in the inventory, grep `packages/admin/app/` for the package's
   entity-type ids, route prefixes, exported class names, and composable names.
2. Apply the definitions above; record the classification and the strongest matching
   path:line evidence.
3. Update `tasks/working/coverage-inventory.md`.

**Files**:
- `tasks/working/coverage-inventory.md` (extended)

**Validation**:
- [ ] Every inventory row classified.
- [ ] Each classification cites at least one SPA path or an "explicit no match" note.

### T010 — Draft proposed admin surface paragraph + size

**Purpose**: For every non-`complete-UI` row, give the future implementer enough to
scope a follow-up mission.

**Steps**:
1. For each `no-UI` or `minimal-UI` row, write a one-paragraph proposed admin surface:
   what pages/components it adds, what entity types or APIs it consumes, what role it
   plays for an admin (e.g. "Workflows admin: list workflow definitions, drill into
   transition history, dry-run state changes, edit guard expressions"). Size per the
   rubric.
2. If the subsystem is a stub/incomplete in the framework (e.g. backend itself not yet
   wired), add a `blocked_on: backend` note and size as `M` minimum.

**Files**:
- `tasks/working/coverage-inventory.md` (extended)

**Validation**:
- [ ] Every non-`complete-UI` row has surface paragraph + size + (if applicable) blocked_on flag.

### T011 — Flag orchestration-table-orphan packages

**Purpose**: Surface packages that exist on disk but are missing from `CLAUDE.md`'s
orchestration table — both an admin-coverage gap *and* a documentation gap.

**Steps**:
1. `ls packages/` → list every package directory.
2. Subtract the inventory's package set.
3. For each orphan, add a row to `tasks/working/coverage-inventory.md` with the flag
   `orchestration-table-orphan`, classify it the same as any other subsystem, and note
   "needs orchestration-table entry" as part of the proposed remedy.

**Files**:
- `tasks/working/coverage-inventory.md` (extended)

**Validation**:
- [ ] Every package on disk appears in the inventory or as an orphan row.

### T012 — Compile Section 2 draft

**Purpose**: Produce the WP02 deliverable: `tasks/working/coverage-section.md`, formatted
for direct inclusion in the final audit doc.

**Steps**:
1. Convert the inventory to a sectioned narrative grouped by layer (L0 Foundation, L1
   Core Data, … L6 Interfaces) so reviewers see coverage by architectural layer.
2. Within each layer, a table with columns: `subsystem | classification | spa evidence
   (path:line or "none") | proposed surface | size | blocked_on | tracking_issue`.
3. Add a section header with the methodology summary (citing `research.md` decision 3).
4. Append an "Orchestration-table orphans" subsection listing any flagged packages.

**Files**:
- `tasks/working/coverage-section.md` (new)

**Validation**:
- [ ] All seven layers present (or a stated reason for any omission).
- [ ] 100% of inventory subsystems appear in their layer's table (NFR-002).

### T013 — File GitHub issues for non-`complete-UI` entries

**Purpose**: Create tracking issues per the audit's bidirectional-link requirement.

**Steps**:
1. For each non-`complete-UI` row, run `gh issue create` using the template in
   `quickstart.md`. Default Track per `research.md` decision 8 (Track 2 for `ai-*`,
   `mcp`, `bimaaji`, agentic-shaped telescope coverage; Track 1 otherwise).
2. Record returned issue number in the row's `tracking_issue` column.
3. Skip rows marked `blocked_on: backend` — file the issue but flag it `blocked` in the
   body and ensure the milestone reflects that.

**Files**:
- `tasks/working/coverage-section.md` (extended with issue numbers)

**Validation**:
- [ ] Every non-`complete-UI` row has `tracking_issue: #N`.
- [ ] `gh issue list --label admin-spa,coverage-gap` matches the filed issues.

### T014 — WP02 self-review

**Purpose**: Confirm WP02 satisfies relevant FRs/NFRs before signaling done.

**Steps**:
1. Verify against `spec.md`:
   - **FR-006**: every orchestration-table subsystem appears with a classification.
   - **FR-007**: every non-`complete-UI` entry has surface paragraph + size.
   - **NFR-002**: 100% subsystem coverage — count inventory rows vs orchestration-table
     packages.
   - **NFR-003**: zero rows with empty citation column (path:line OR "explicit no match").
2. Verify against `plan.md` "Acceptance & Validation":
   - column-count grep returns ≥1 row per layer subsection
3. Verify `git diff main -- packages/` for this WP's commits is empty.

**Files**: none modified.

**Validation**:
- [ ] All NFR/FR checks pass.
- [ ] No `packages/admin/` or backend modifications in WP02 commits.

## Definition of Done

- `tasks/working/coverage-section.md` exists, grouped by layer, listing every
  orchestration-table subsystem plus any orphans.
- 100% subsystem coverage (NFR-002).
- Every non-`complete-UI` entry has surface paragraph + size + tracking issue link.
- All filed GitHub issues carry a Track milestone.
- Self-review checks all pass.

## Reviewer Guidance

- Pick three subsystems from the inventory at random; confirm the classification by
  spot-checking the SPA evidence.
- Confirm at least one orphan is detected (or confirm there are none by spot-checking
  three random `packages/` entries against the inventory).
- Confirm classifications are consistent: `minimal-UI` rows show real SPA references,
  `no-UI` rows show "explicit no match".
- Confirm `git diff main -- packages/` is empty for WP02 commits.

## Implementation command

```bash
spec-kitty agent action implement WP02 --agent <name>
```
