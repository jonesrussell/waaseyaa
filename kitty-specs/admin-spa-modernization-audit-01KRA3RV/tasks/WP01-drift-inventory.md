---
work_package_id: WP01
title: Drift Inventory
dependencies: []
requirement_refs:
- C-001
- C-002
- FR-004
- FR-005
- FR-012
- FR-013
- FR-015
- NFR-001
- NFR-003
- NFR-004
- NFR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
history:
- date: '2026-05-10'
  note: Created during /spec-kitty.tasks.
authoritative_surface: kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/working/drift-section.md
execution_mode: planning_artifact
mission_id: 01KRA3RV5GPMP178F2VMEX1TW1
mission_slug: admin-spa-modernization-audit-01KRA3RV
owned_files:
- kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/working/drift-section.md
- kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/WP01-drift-inventory.md
tags: []
---

# WP01 — Drift Inventory

## Objective

Produce **Section 1: Framework Alignment Drift** of the audit as a working file at
`kitty-specs/admin-spa-modernization-audit-01KRA3RV/tasks/working/drift-section.md`,
and file one GitHub issue per non-`no-op` drift entry with a Track milestone.

Every drift entry must cite at least one commit hash or issue/PR number, name affected
admin SPA file paths, carry a classification from `{broken, degraded, unsurfaced, no-op}`,
and a size in `{XS, S, M, L}` per the rubric in `research.md`.

## Branch Strategy

- **Planning base branch**: `main`
- **Final merge target**: `main`
- **Execution workspace**: a per-lane worktree allocated by `lanes.json` after task finalization.
  Do not cd elsewhere; operate from within the lane worktree.

## Context

This is the drift axis of an audit-only research mission. **You are not modifying any
file inside `packages/admin/` or any backend package** — see `spec.md` C-001/C-002.
Your output is one working markdown file and a set of GitHub issues.

Read these before starting:
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/spec.md` — FRs, NFRs, constraints
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/plan.md` — methodology summary, acceptance gates
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/research.md` — corpus selection, classification rubric, citation conventions, in-flight overlap detection
- `kitty-specs/admin-spa-modernization-audit-01KRA3RV/quickstart.md` — issue template, conventions

The corpus packages are:
```
packages/entity packages/entity-storage packages/field packages/api
packages/access packages/auth packages/routing packages/user packages/config
packages/telescope packages/foundation/src/Http
```

## Subtasks

### T001 — Build drift corpus per backend package [P]

**Purpose**: Collect every first-parent commit on `main` that touched each corpus package
over the full v1.x lifetime, with file lists, so commits can be cross-referenced against
the admin SPA without re-traversing history.

**Steps**:
1. For each corpus package, run via `mcp__plugin_context-mode_context-mode__ctx_batch_execute`
   (raw output stays in the sandbox):
   ```
   git log --first-parent main --oneline -- <package>
   git log --first-parent main --name-only --pretty=format:'%h|%s' -- <package>
   ```
2. Index the output; query for high-signal commit subjects (e.g. "schema", "JSON:API",
   "attribute", "cast", "tenancy", "bundle", "field", "route", "session").
3. Persist a working corpus table at `tasks/working/drift-corpus.md` with columns:
   `commit_hash | package | subject | files_touched | candidate_class_guess`.

**Files**:
- `tasks/working/drift-corpus.md` (new)

**Validation**:
- [ ] Every corpus package has ≥1 row OR an explicit single-row "no in-window commits".
- [ ] Each row has a commit hash, subject, and touched-files summary.

### T002 — Cross-reference each commit against admin SPA file impact

**Purpose**: For each candidate commit, determine whether the admin SPA actually depends
on the changed surface. Either the SPA imports/calls the changed thing (potential drift)
or it doesn't (likely `no-op`).

**Steps**:
1. For each candidate commit, identify the most stable identifier(s) it changed (a class
   name, route path, JSON:API key, attribute key, enum value).
2. Grep `packages/admin/app/` for that identifier. Record any matching file:line pairs
   in the corpus table as `spa_refs`.
3. If no matches, mark the row `no-op` candidate (subject to T003 confirmation).

**Files**:
- `tasks/working/drift-corpus.md` (extended)

**Validation**:
- [ ] Every row has either an `spa_refs` list or a `no-op` mark.
- [ ] Each `spa_refs` entry uses `path:line` form.

### T003 — Classify each candidate and assign size

**Purpose**: Apply the four-class rubric from `research.md` decision 2 and the sizing
rubric from decision 4 to every candidate. Bulk-classify clearly-inert commits as `no-op`
with a single rationale line (e.g. "test-only", "rename in implementation, public API
unchanged") per NFR-001.

**Steps**:
1. For each row with `spa_refs`:
   - `broken`: the SPA call would now fail (404, type error, missing field). Hard fail.
   - `degraded`: the SPA call still succeeds but returns wrong/stale data, ignores tenancy,
     or surfaces incorrect types.
   - `unsurfaced`: the backend added a new capability accessible from an existing admin
     flow but the SPA does not surface it.
2. For rows with no `spa_refs`, mark `no-op` (with rationale if non-obvious).
3. Size every actionable (non-`no-op`) row using the rubric.
4. Update `tasks/working/drift-corpus.md`.

**Files**:
- `tasks/working/drift-corpus.md` (extended)

**Validation**:
- [ ] Every row has classification and (if actionable) size.
- [ ] `no-op` rationale is one line each (or batch-classified with one shared rationale).

### T004 — Detect in-flight overlap

**Purpose**: Flag drift entries already being addressed by an open PR or issue so the
audit doesn't generate duplicate tracking artifacts.

**Steps**:
1. Run:
   ```
   gh pr list --search 'packages/admin' --state open --json number,title,url
   gh issue list --search 'admin SPA' --state open --json number,title,url --limit 100
   ```
2. For each actionable drift row, scan PRs/issues for textual overlap with the row's
   affected files or backend identifier. Annotate `in_flight: #PR-or-issue-N` when found.

**Files**:
- `tasks/working/drift-corpus.md` (extended)

**Validation**:
- [ ] Every overlap is captured with the open PR/issue number.
- [ ] No row has an unverified in-flight claim.

### T005 — Compile Section 1 draft

**Purpose**: Produce the WP01 deliverable: `tasks/working/drift-section.md`, formatted
for direct inclusion in the final audit doc.

**Steps**:
1. Convert `tasks/working/drift-corpus.md` into a sectioned narrative:
   - One subsection per backend package (skip packages with all-`no-op` rows but tally
     them in a "no-op summary" line so coverage is auditable).
   - Within each subsection, a table with columns: `classification | citation(s) | spa files | size | proposed remedy | in-flight`.
2. Add a section header with a one-paragraph methodology summary (citing `research.md`
   decisions 1, 2, 4).
3. Save to `tasks/working/drift-section.md`. **Do not** write to `docs/audits/`; that's WP03's job.

**Files**:
- `tasks/working/drift-section.md` (new)

**Validation**:
- [ ] One subsection per in-window backend package (or a `no-op summary` for empty ones).
- [ ] Every actionable row has all five+ columns populated.

### T006 — File one GitHub issue per actionable drift entry

**Purpose**: Create tracking issues so the audit's findings are visible outside the doc.

**Steps**:
1. For each non-`no-op`, non-`in-flight` row, run `gh issue create` using the template in
   `quickstart.md`. Default Track 1 milestone; if the backend package is `telescope` and
   the gap is agentic-observability-shaped, use Track 2.
2. After each `gh issue create`, record the returned issue number in the working file
   row's `tracking_issue` column.
3. Skip `in-flight` rows — they already have an open PR/issue.

**Files**:
- `tasks/working/drift-section.md` (extended with issue numbers)

**Validation**:
- [ ] Every actionable, non-in-flight row has `tracking_issue: #N`.
- [ ] `gh issue list --milestone "Track 1" --milestone "Track 2" --label admin-spa,audit-followup` returns the created issues.

### T007 — WP01 self-review

**Purpose**: Confirm WP01 deliverables satisfy the relevant FRs/NFRs before signaling done.

**Steps**:
1. Verify against `spec.md`:
   - **FR-005**: every row has commit/issue citation + spa files + classification + size.
   - **NFR-001**: ≥90% of in-window commits per package classified (count rows vs corpus).
   - **NFR-003**: zero rows with empty citation column.
2. Verify against `plan.md` "Acceptance & Validation":
   - column-count grep returns ≥1 row per actionable subsection
   - citation pattern `[a-f0-9]{7,40}` or `#\d+` present per actionable row
3. Verify `git diff main -- packages/` for this WP's commits is empty.
4. If all green, signal WP01 done. If any fail, fix and re-run.

**Files**: none modified.

**Validation**:
- [ ] All NFR/FR checks pass.
- [ ] No `packages/admin/` or backend `packages/*` modifications in WP01 commits.

## Definition of Done

- `tasks/working/drift-section.md` exists with one subsection per in-window backend package.
- Every actionable row has classification + citation + spa files + size + (tracking_issue OR in_flight) columns populated.
- ≥90% of in-window commits per package addressed (NFR-001).
- All filed GitHub issues carry a Track milestone and link back to the audit anchor.
- Self-review checks all pass.

## Reviewer Guidance

- Spot-check three random drift entries: follow the citation, confirm the named SPA file/line still exists, confirm the classification reads correctly.
- Sample three `no-op` entries: confirm rationale is defensible (rename, test-only, internal refactor).
- Confirm `git diff main -- packages/` is empty for WP01 commits.
- Confirm at least one in-flight overlap is detected if any open admin-related PRs/issues exist.

## Implementation command

```bash
spec-kitty agent action implement WP01 --agent <name>
```
