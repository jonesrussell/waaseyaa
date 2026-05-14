# Tasks: Admin SPA Envelope Re-shape & Build Pipeline (M2 wrap-up)

**Mission**: `admin-spa-envelope-reshape-01KRJ9ZE` (mid8 `01KRJ9ZE`)
**Branch contract**: `main` → `main`
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Tracking issue**: [#1412](https://github.com/waaseyaa/framework/issues/1412)

## Context summary

90% of the originally-specified M2 work shipped in **M2A** (PR #1422, commit `fe5f48fd1`, merged 2026-05-11). What remains is a doc-only wrap-up:

- Audit annotations for 6 closed entries + §4.6 monorepo-shape decision
- Verify-first sync of `docs/specs/admin-spa.md`
- Reconciliation of the still-open PR #1350 (close as obsolete)
- Final verification grep
- CHANGELOG entry
- Issue closure via PR footer

The mission ships as **one work package** (`WP01`), with one PR, producing one merge commit. Estimated effort: ≤4 hours wall-clock for a focused agent.

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Verify M2A-landed envelope (FR-001..FR-008, FR-012) | WP01 | — |
| T002 | Verify CI gate intact (constraint C-008, NFR spot-check) | WP01 | [P] (with T001) |
| T003 | Sync `docs/specs/admin-spa.md` to private-app shape (verify-first) | WP01 | — |
| T004 | Annotate `docs/audits/admin-spa-modernization-2026-05-10.md` (close 6 entries + §4.6 decision + Top-5 row) | WP01 | — |
| T005 | Reconcile PR #1350 — read, salvage any artifacts, close as obsolete | WP01 | — |
| T006 | Add CHANGELOG `[Unreleased]` bullet | WP01 | [P] (with T004) |
| T007 | Open the wrap-up PR with `Closes #1412` footer; verify post-merge issue closure | WP01 | — |

7 subtasks total. Within ideal WP-sizing (3–7 subtasks, target prompt ~300–500 lines).

## Work Packages

### WP01 — M2 wrap-up: audit closure + spec sync + PR #1350 reconciliation

**Goal**: Close out the admin-spa M2 mission as a doc-only PR. Land audit annotations, sync the spec, close PR #1350, and trip GitHub issue #1412 closure.

**Priority**: P1 (only WP — blocks mission accept).

**Independent test**: Reviewer runs the WP01 verification block from `quickstart.md` Step 1 against the merged commit and confirms all checks pass + CI is green + #1350 is closed + #1412 is closed.

**Estimated prompt size**: ~450 lines (7 subtasks × ~60 lines).

**Owned files**:
- `docs/specs/admin-spa.md`
- `docs/audits/admin-spa-modernization-2026-05-10.md`
- `CHANGELOG.md`

**Authoritative surface**: `docs/` (primary surface; CHANGELOG is a leaf append).

**Execution mode**: `code_change` (edits live in the working repo, not in kitty-specs).

**Verify-only (NOT owned, but read & assert)**:
- `packages/admin/package.json` — verify M2A shape (FR-001..FR-006)
- `packages/admin/README.md` — verify 50–80 line count (FR-008)
- `packages/admin/.gitignore` — verify `dist/` exclusion (FR-007)
- `.github/workflows/admin.yml` — verify intact, do NOT edit (constraint C-008)

**Out-of-tree actions** (allowed; not file edits):
- `gh pr close 1350 --comment "..."` (FR-011)
- `gh issue view 1412 --json state` post-merge (FR-013)

**Included subtasks**:

- [ ] T001 Verify M2A-landed envelope (FR-001..FR-008, FR-012) (WP01)
- [ ] T002 Verify CI gate intact (constraint C-008, NFR spot-check) (WP01)
- [ ] T003 Sync `docs/specs/admin-spa.md` to private-app shape (verify-first) (WP01)
- [ ] T004 Annotate `docs/audits/admin-spa-modernization-2026-05-10.md` (close 6 entries + §4.6 decision + Top-5 row) (WP01)
- [ ] T005 Reconcile PR #1350 — read, salvage any artifacts, close as obsolete (WP01)
- [ ] T006 Add CHANGELOG `[Unreleased]` bullet (WP01)
- [ ] T007 Open the wrap-up PR with `Closes #1412` footer; verify post-merge issue closure (WP01)

**Implementation sketch**:

1. Run the verification block (T001 + T002) before touching any files. If anything fails, stop and escalate — the M2A foundation is intact and the wrap-up assumes it.
2. Spec sync (T003): grep first, edit only if drift remains. Minimum-viable change.
3. Audit annotations (T004): apply the strikethrough + `**CLOSED — ...**` pattern (matches the existing E-Pkg-05 closure idiom). Six entries + §4.6 decision paragraph + a brief Top-5 row footnote. Use a `<wrap-up-PR-sha>` placeholder during the edit; replace it in a follow-up commit on the same branch after the PR is opened.
4. CHANGELOG (T006): one bullet under `[Unreleased]`.
5. PR #1350 reconciliation (T005): read the PR diff and comments; if anything is salvageable (e.g. a CI step), cherry-pick it into this branch first; then close PR #1350 with an explanatory comment.
6. Open the PR (T007) with `Closes #1412` in the footer. Wait for CI; merge once green and reviewer-approved.
7. After merge, verify #1412 closed automatically. If not, close manually with citation per `feedback_pr_traceability_signals`.

**Parallel opportunities**:
- T001 and T002 are read-only checks; they can run in any order or together.
- T006 (CHANGELOG) is independent of T004 (audit doc) and can be sequenced freely within the WP. Marked `[P]` only insofar as they touch different files; a single agent will sequence them naturally.
- T003 / T004 / T005 / T007 are sequential (each builds on the previous).

**Dependencies**:
- Mission-level: depends on **PR #1422 (M2A) already merged** ✓ (verified at planning time, `fe5f48fd1`).
- Inter-WP: none (single-WP mission).

**Risks** (refined from plan):
- Audit-doc annotation format drift — mitigated by following the existing E-Pkg-05 idiom; reviewer to spot-check pattern consistency.
- Spec already-current — if `docs/specs/admin-spa.md` already reflects the private-app shape, T003 becomes a verified-only step recorded in WP findings, not an edit. Do not synthesize a fake edit.
- PR #1350 salvageable bits — read the PR fully before closing; cherry-pick if needed.
- `<wrap-up-PR-sha>` placeholder must be replaced in a follow-up commit after the PR is opened (the PR's own merge SHA isn't known until merge time, so the citation will reference the wrap-up PR by number + opening commit SHA, updated to merge SHA post-merge if desired).

**Reviewer guidance**:
- Verify all 7 verification checks in `quickstart.md` Step 1 pass on the merged HEAD.
- Confirm audit doc annotations use the existing `**CLOSED — ...**` pattern and cite both `fe5f48fd1` (M2A) AND the wrap-up PR.
- Confirm PR #1350 was closed with an explanatory comment, not a silent close.
- Confirm CHANGELOG `[Unreleased]` bullet exists and references #1412 + #1422.
- Confirm PR footer contains `Closes #1412`.

**Prompt file**: [tasks/WP01-m2-wrap-up.md](tasks/WP01-m2-wrap-up.md)

## MVP scope

WP01 **is** the MVP. There is no follow-on WP. Mission accepts when WP01's PR merges + CI green + #1412 closed + #1350 closed.

## Estimated timeline

- Lane allocation + agent dispatch: 5 min
- T001 + T002 verification: 10 min
- T003 spec grep + verify-only OR minor edit: 15 min
- T004 audit annotations: 30 min (6 entries + §4.6 + footnote)
- T005 PR #1350 read + close: 15 min
- T006 CHANGELOG: 5 min
- T007 PR open + review + merge: 30 min — 2 hours (CI runtime dominates)

**Total wall-clock target**: ≤ 4 hours focused work. Mission NFR-006 target: merge by 2026-05-21 (7 days).
