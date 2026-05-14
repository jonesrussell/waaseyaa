---
work_package_id: WP01
title: 'M2 wrap-up: audit closure + spec sync + PR #1350 reconciliation'
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-009
- FR-010
- FR-011
- FR-012
- FR-013
planning_base_branch: main
merge_target_branch: main
branch_strategy: single-lane; lane allocated at /spec-kitty.next time via lanes.json; final merge target is main
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
history:
- timestamp: '2026-05-14T04:04:32Z'
  actor: claude-opus-4-7
  event: wp_prompt_generated
  note: Single-WP mission. Doc-only wrap-up after M2A (#1422) shipped envelope changes on 2026-05-11.
authoritative_surface: docs/
execution_mode: code_change
mission_id: 01KRJ9ZE3KW2KHH5J1442AAH12
mission_slug: admin-spa-envelope-reshape-01KRJ9ZE
owned_files:
- docs/specs/admin-spa.md
- docs/audits/admin-spa-modernization-2026-05-10.md
- CHANGELOG.md
tags: []
---

# WP01 — M2 wrap-up: audit closure + spec sync + PR #1350 reconciliation

## Branch Strategy

- **Planning base branch**: `main`
- **Final merge target**: `main`
- **Execution worktree**: allocated per `lanes.json` at `/spec-kitty.next` time. Expected path: `.worktrees/admin-spa-envelope-reshape-01KRJ9ZE-lane-a/` (single lane; single WP).
- **Implementation entry command**: `spec-kitty agent action implement WP01 --agent <name>` from repo root after `spec-kitty next --agent <name> --mission 01KRJ9ZE` has allocated the lane.

## Objective

Close out the admin-spa **M2** mission (issue #1412) as a doc-only PR. The envelope reshape itself already shipped in **M2A** (PR #1422, commit `fe5f48fd1`, merged 2026-05-11). This WP:

1. Verifies the M2A-landed envelope is intact (regression guard).
2. Syncs `docs/specs/admin-spa.md` to the private-app shape (verify-first).
3. Annotates `docs/audits/admin-spa-modernization-2026-05-10.md` to record audit-entry closures and the §4.6 monorepo-shape decision.
4. Closes the still-open PR #1350 as obsolete.
5. Adds a CHANGELOG `[Unreleased]` bullet.
6. Opens the wrap-up PR with `Closes #1412` in the footer.

**This is a doc-only PR.** No `packages/admin/` files are edited. No CI workflow files are edited. No dependencies are bumped. Constraints C-001..C-008 from the spec are load-bearing.

## Context you need

Before starting, read (these are short):

- `kitty-specs/admin-spa-envelope-reshape-01KRJ9ZE/spec.md` — the contract (FR/NFR/C tables)
- `kitty-specs/admin-spa-envelope-reshape-01KRJ9ZE/plan.md` — the rationale + verification matrix
- `kitty-specs/admin-spa-envelope-reshape-01KRJ9ZE/research.md` — the 7 decisions that shaped the plan (especially Decision 1: M2A overlap; Decision 4: audit-annotation convention)
- `kitty-specs/admin-spa-envelope-reshape-01KRJ9ZE/quickstart.md` — the operational runbook (you will follow this verbatim)

Then read the live working state:

- `packages/admin/package.json` (M2A's manifest — for verification)
- `packages/admin/README.md` (M2A's README — for verification)
- `docs/specs/admin-spa.md` (might already be accurate)
- `docs/audits/admin-spa-modernization-2026-05-10.md` (you'll edit it)

External:

- PR #1422 (M2A) — `gh pr view 1422` for context on what already shipped
- PR #1350 (open, to be closed) — `gh pr view 1350` to read before closing

## Subtasks

### T001 — Verify M2A-landed envelope (FR-001..FR-008, FR-012)

**Purpose**: Confirm M2A's envelope changes are still on `main`. If anything regressed, stop and escalate — the wrap-up assumes M2A's foundation is intact.

**Steps**:

1. Run the package.json shape check:

   ```bash
   jq '{private, engines, exports: (.exports // "absent"), main: (.main // "absent"), peerDeps: (.peerDependencies // "absent"), buildContracts: (.scripts."build:contracts" // "absent")}' packages/admin/package.json
   ```

   Expect:
   - `private: true` (FR-001)
   - `engines: {"node": ">=22.12.0"}` (FR-004 — value is stricter than spec's `>=22.0.0`; both pass)
   - `exports: "absent"` (FR-002)
   - `main: "absent"` (FR-003)
   - `peerDeps: "absent"` (FR-005)
   - `buildContracts: <non-null string>` (FR-006)

2. Verify dist gitignored (FR-007):

   ```bash
   grep -E '^/?dist/?$' packages/admin/.gitignore
   ```

   Expect a match.

3. Verify README line count (FR-008 / NFR-005):

   ```bash
   wc -l packages/admin/README.md
   ```

   Expect a value in `[50, 80]` inclusive.

4. Verify zero `@waaseyaa/admin` importers (FR-012):

   ```bash
   rg "@waaseyaa/admin" --type ts --type js --type json \
     -g '!**/node_modules/**' \
     -g '!**/package-lock.json' \
     -g '!packages/admin/**' \
     -g '!kitty-specs/**' \
     -g '!docs/**'
   ```

   Expect empty output.

**Files touched**: none (read-only checks).

**Validation**:
- [ ] All 4 checks pass with expected output
- [ ] If any check fails, STOP and post a finding to WP01 history; do not proceed to T003+

**Edge cases**:
- If `packages/admin/.gitignore` has `dist` without trailing slash and no leading slash, the regex still matches — good.
- If `rg` is not available, fall back to `grep -rEn "@waaseyaa/admin" --include="*.ts" --include="*.js" --include="*.json" --exclude-dir=node_modules .` from repo root and filter manually.

### T002 — Verify CI gate intact (constraint C-008, NFR-001..NFR-004 spot-check)

**Purpose**: Confirm `.github/workflows/admin.yml` is unchanged and still defines the admin/contracts, admin/build, admin/integration, admin/adapters jobs. Do NOT edit the workflow file.

**Steps**:

1. Inspect the workflow:

   ```bash
   head -100 .github/workflows/admin.yml
   ```

   Expect: `name: admin`, jobs include at least `contracts`, `build`, `integration`. The `contracts` job runs `npm run build:contracts` and uploads `dist/` as a 14-day artifact.

2. Confirm no diff against the version that was on `main` at M2A merge time:

   ```bash
   git log -1 --format=%H .github/workflows/admin.yml
   git log --oneline -5 .github/workflows/admin.yml
   ```

   If the most recent commit touching the workflow predates `fe5f48fd1`, the gate is intact and unchanged since M2A. If it postdates M2A, read the diff and confirm it doesn't undo the E-Pkg-05 closure.

**Files touched**: none (read-only).

**Validation**:
- [ ] Workflow contains an `admin/contracts` job that runs `npm run build:contracts`
- [ ] No regression in CI coverage since M2A

### T003 — Sync `docs/specs/admin-spa.md` to private-app shape (verify-first)

**Purpose**: If the canonical spec still implies an installable/exportable shape, edit it minimally. If it already reflects private-app, record verify-only.

**Steps**:

1. Grep for current Distribution language:

   ```bash
   rg -n "private|monorepo-internal|not published|build:contracts|verification artifact" docs/specs/admin-spa.md | head -20
   rg -n "exports.*dist|installable|@waaseyaa/admin.*import|consumers.*import|publish" docs/specs/admin-spa.md | head -20
   ```

2. Decision branch:
   - **If the spec already says "private package", "monorepo-internal", or "verification artifact"**: T003 is **verified-only**. Add a one-line note in `kitty-specs/admin-spa-envelope-reshape-01KRJ9ZE/tasks/WP01-m2-wrap-up.md` history block (or in the PR body) documenting the verification. Skip to T004.
   - **If the spec implies an installable/exportable shape**: edit the relevant section(s) to add the private-app framing. Minimum-viable edit only.

3. If editing, target sections like "Distribution", "Package shape", or "Build outputs". The edit should:
   - Replace any "consumers import `@waaseyaa/admin`" framing with "monorepo-internal; served via `admin-surface` PHP runtime".
   - Add (or clarify) that `build:contracts` is a CI verification artifact (gitignored `dist/`), not a published export.
   - Cite the audit and M2A: `(closed by audit M2 follow-up — M2A: PR #1422 / commit fe5f48fd1; M2 wrap-up: PR <wrap-up-PR>)`.

**Files touched** (only if editing): `docs/specs/admin-spa.md`.

**Validation**:
- [ ] Decision branch taken (verify-only OR minimal edit)
- [ ] If edited: the diff is section-level, not a rewrite
- [ ] If verify-only: noted in WP history

**Edge cases**:
- Don't edit unrelated sections (auth, codified-context, AdminSurface boot) even if you spot drift — that's out of scope for M2.

### T004 — Annotate `docs/audits/admin-spa-modernization-2026-05-10.md` (FR-010)

**Purpose**: Mark 6 audit entries as **CLOSED** and record the §4.6 monorepo-shape decision. Follow the existing E-Pkg-05 closure idiom (strikethrough + `**CLOSED — ...**`).

**Annotation pattern** (apply to each entry):

For the finding text in the table row, wrap the original text in `~~...~~` (strikethrough), then append in **bold**: ` **CLOSED — <reason>. (closed by M2A — commit fe5f48fd1, PR #1422; M2 wrap-up — PR <wrap-up-PR>)**`.

**Entries to close** (find the rows around the cited line numbers):

1. **E-Pkg-01** (line ~412, "`exports` map references missing `dist/contracts/`"):
   - Reason: "`exports` map removed; package now declares `private: true`."

2. **E-Pkg-02** (line ~413, "No `engines` constraint"):
   - Reason: "`engines.node = >=22.12.0` declared (stricter than audit-suggested >=22.0.0, matches Nuxt 4.4.4 constraint)."

3. **E-Pkg-03** (line ~414, "No `publishConfig`, no `private: true`"):
   - Reason: "`\"private\": true` declared; package is monorepo-internal, not published. No `publishConfig` needed."

4. **E-Pkg-04** (line ~415, "No peerDependencies"):
   - Reason: "No `peerDependencies` added by M2 wrap-up decision (2026-05-13). Package is private and has zero published consumers (E-Pkg-06)."

5. **E-Pkg-06** (line ~422, "No known downstream consumer..."):
   - Reason: "YAGNI confirmed by M2A and M2 wrap-up verification grep. Zero `@waaseyaa/admin` import statements across `waaseyaa/framework` and `waaseyaa.org`. Private-app shape adopted."

6. **E-Docs-01** (line ~428, "README is 21 lines..."):
   - Reason: "README expanded from 21 lines to ~63 lines covering stack, develop/test/build commands, build:contracts verification gate, i18n, and modernization-audit pointer."

**§4.6 monorepo-shape decision** (around line ~440-446): append a new paragraph at the end of §4.6:

```markdown
**Decision (2026-05-13, M2 wrap-up)**: Option 1 (status quo) adopted. The admin SPA remains the only JS-only package in a PHP monorepo. The `exports` map and `dist/` references were removed by M2A (commit `fe5f48fd1`, PR #1422). PR #1350 (the manual dist-rebuild PR) is closed as obsolete in the same window. The pre-built tarball model (option 2) and the sibling-repo model (option 3) remain documented as escape hatches if a future external Waaseyaa app justifies them. (M2 wrap-up: PR <wrap-up-PR>.)
```

**Top-5 mission table row M2** (around line ~46): append the following to the Issue column or as a footnote referenced from the row:

```markdown
M2 status (2026-05-14): M2A (PR #1422) shipped envelope + README on 2026-05-11; M2 wrap-up (PR <wrap-up-PR>) lands doc-sync + audit closure + PR #1350 reconciliation.
```

**Placeholder handling**:

`<wrap-up-PR>` is unknown until you open the PR (T007). Two options:
- (a) Use the literal placeholder string `<wrap-up-PR>` while editing T004; after `gh pr create`, run a single follow-up commit on the same branch replacing all instances with the actual `#NNNN`. Preferred.
- (b) Open a draft PR first (T007 partial) to get the number, then edit T004, then mark PR ready-for-review. Acceptable but more ceremony.

**Files touched**: `docs/audits/admin-spa-modernization-2026-05-10.md`.

**Validation**:
- [ ] Six audit-entry rows annotated with `**CLOSED — ...**` pattern
- [ ] §4.6 has a new "Decision (2026-05-13, M2 wrap-up)" paragraph
- [ ] Top-5 row M2 has the status footnote
- [ ] All annotations cite `fe5f48fd1` AND `PR #1422` AND `PR <wrap-up-PR>` (placeholder until T007's follow-up commit)
- [ ] No content removed from the audit — only annotations added

**Edge cases**:
- The audit may have line drift from when the spec was written. Find rows by their `<a name="E-Pkg-01"></a>` anchors (etc.), not by line number alone.
- If you find that any of these audit entries has already been independently closed by some other mission, defer to the existing annotation and only add the M2 wrap-up citation suffix.

### T005 — PR #1350 reconciliation (FR-011)

**Purpose**: Close PR #1350 as obsolete after salvaging anything worth keeping.

**Steps**:

1. Read PR #1350 in full:

   ```bash
   gh pr view 1350 --json title,body,state,files,commits,comments
   gh pr diff 1350 | head -200
   ```

2. Classify each change in the PR:
   - **Pure dist rebuild** (the `admin-surface/public/dist` blob) → discard.
   - **CI workflow tweak** (e.g. better cache key, faster build step) → cherry-pick into this branch before closing.
   - **Build script change** (e.g. `package.json scripts.build`) → defer (likely belongs to a separate dependency or build mission).
   - **Doc change** → defer (likely already absorbed by M2A's README).

3. If anything was salvaged, commit the cherry-pick with a clear message referencing #1350.

4. Close PR #1350 with an explanatory comment:

   ```bash
   gh pr close 1350 --comment "Closing as obsolete. M2A (#1422) landed the admin SPA envelope reshape on 2026-05-11 and M2 wrap-up (PR <wrap-up-PR>) adopts the status-quo monorepo shape (no pre-built tarball model — see docs/audits/admin-spa-modernization-2026-05-10.md §4.6 \"Decision (2026-05-13, M2 wrap-up)\"). The dist-rebuild workflow this PR represents is no longer needed because the admin SPA is now a private workspace member served through admin-surface, not published as a tarball. Reopen if the dist-rebuild use case re-emerges."
   ```

5. If `<wrap-up-PR>` isn't known yet (T007 not done), close PR #1350 with a placeholder in the comment and edit the comment after the wrap-up PR opens (or skip T005 closure until after T007 — your choice; both are acceptable).

**Files touched** (only if salvaging): potentially `.github/workflows/admin.yml` or similar. **Important**: if you salvage a workflow change, you are intentionally violating constraint C-008 with rationale recorded in the WP history. The default expectation is **no salvage**.

**Validation**:
- [ ] PR #1350 was read in full before closing
- [ ] Closure comment cites M2A, the §4.6 decision, and the wrap-up PR
- [ ] If anything was salvaged, the rationale is recorded in WP history AND in the PR body
- [ ] PR #1350 state is `CLOSED` after the action

**Edge cases**:
- If PR #1350 has new commits since 2026-05-11, re-read its body — the maintainer may have updated the scope. If so, escalate to the user before closing.
- If PR #1350 was merged or closed by someone else in the meantime, this subtask is a no-op (record in WP history).

### T006 — CHANGELOG `[Unreleased]` bullet

**Purpose**: Add a release-notes bullet so the next release cut includes the M2 closure.

**Steps**:

1. Read the current `[Unreleased]` section:

   ```bash
   awk '/^## \[Unreleased\]/,/^## \[/' CHANGELOG.md | head -30
   ```

2. Append one bullet under `Changed` (or under `Documentation` if that subsection exists; create the subsection if neither fits):

   ```markdown
   - Close out admin-spa M2 mission: doc sync in `docs/specs/admin-spa.md`, audit annotations marking E-Pkg-01..04, E-Pkg-06, E-Docs-01 as closed in `docs/audits/admin-spa-modernization-2026-05-10.md`, and status-quo monorepo-shape decision recorded. PR #1350 closed as obsolete. M2A (PR #1422) shipped the envelope reshape + README on 2026-05-11. (#1412)
   ```

3. Per `feedback_changelog_release_workflow` (in MEMORY.md): do **not** add a version heading — only add bullets under `[Unreleased]`. `release-cut.yml` promotes them at tag time.

**Files touched**: `CHANGELOG.md`.

**Validation**:
- [ ] One new bullet under `[Unreleased]` referencing #1412 + #1422
- [ ] No version heading edits
- [ ] Markdown is valid (no broken list nesting)

### T007 — Open the wrap-up PR with `Closes #1412` footer (FR-013)

**Purpose**: Land all WP01 edits as a single PR; trip GitHub issue #1412 closure on merge.

**Steps**:

1. Confirm working tree is clean except for the WP01 edits:

   ```bash
   git status --short
   ```

   Expect: M for `docs/specs/admin-spa.md` (if T003 edited), M for `docs/audits/admin-spa-modernization-2026-05-10.md` (T004), M for `CHANGELOG.md` (T006).

2. Commit:

   ```bash
   git add docs/specs/admin-spa.md docs/audits/admin-spa-modernization-2026-05-10.md CHANGELOG.md
   git commit -m "$(cat <<'EOF'
   docs(admin-spa-m2): close audit entries + sync spec + reconcile PR #1350

   M2 wrap-up for #1412. Envelope changes themselves landed in M2A
   (PR #1422, commit fe5f48fd1) on 2026-05-11. This commit:

   - Marks E-Pkg-01..04, E-Pkg-06, E-Docs-01 CLOSED in the audit doc
     with citation to M2A and this wrap-up PR.
   - Records §4.6 monorepo-shape decision: status quo (option 1) adopted.
   - Syncs docs/specs/admin-spa.md to private-app shape (verify-first;
     edit only if drift).
   - Adds CHANGELOG [Unreleased] bullet documenting the closure.

   Refs #1412
   Refs #1422 (M2A — already merged)

   Closes #1412

   Co-Authored-By: Claude <noreply@anthropic.com>
   EOF
   )"
   ```

3. Push the branch and open the PR:

   ```bash
   git push -u origin HEAD
   gh pr create --title "docs(admin-spa-m2): close audit entries + sync spec + reconcile PR #1350" --body "$(cat <<'EOF'
   ## Summary

   Closes out admin-spa **M2** (#1412) as a wrap-up mission. The envelope reshape itself already shipped in **M2A** (PR #1422, commit `fe5f48fd1`) on 2026-05-11. This PR:

   - Annotates `docs/audits/admin-spa-modernization-2026-05-10.md` to mark E-Pkg-01..04, E-Pkg-06, E-Docs-01 as **CLOSED** with citation to M2A and this wrap-up PR.
   - Records the §4.6 monorepo-shape decision: **status quo (option 1) adopted**.
   - Syncs `docs/specs/admin-spa.md` to the private-app shape (or verifies it's already accurate).
   - Closes PR #1350 (pre-built tarball / manual dist rebuild) as obsolete.
   - CHANGELOG `[Unreleased]` bullet documents the closure.

   ## Changes

   - `docs/audits/admin-spa-modernization-2026-05-10.md`: audit-entry closures + §4.6 decision.
   - `docs/specs/admin-spa.md`: section-level sync (if any drift remains).
   - `CHANGELOG.md`: `[Unreleased]` entry.

   ## Verification

   - Existing CI re-runs `admin/contracts`, `admin/build`, `admin/integration`, `admin/adapters`.
   - `rg "@waaseyaa/admin"` across the workspace confirms zero importers (FR-012).
   - `wc -l packages/admin/README.md` returns 63 (FR-008 / NFR-005).
   - `jq '.private' packages/admin/package.json` returns `true` (FR-001).

   ## Refs

   - Closes #1412
   - Refs PR #1422 (M2A — already merged)
   - Refs PR #1350 (closed in this PR)
   - Audit source: `docs/audits/admin-spa-modernization-2026-05-10.md#m2`
   - Mission: `kitty-specs/admin-spa-envelope-reshape-01KRJ9ZE/`

   🤖 Generated with [Claude Code](https://claude.com/claude-code)
   EOF
   )"
   ```

4. Replace `<wrap-up-PR>` placeholders in the audit doc:

   ```bash
   PR_NUM=$(gh pr view --json number -q .number)
   sed -i "s|<wrap-up-PR>|#${PR_NUM}|g" docs/audits/admin-spa-modernization-2026-05-10.md
   git add docs/audits/admin-spa-modernization-2026-05-10.md
   git commit -m "docs(admin-spa-m2): fill in wrap-up PR number in audit citations"
   git push
   ```

5. Wait for CI green; ensure all admin/* jobs pass.

6. After merge, verify issue auto-closure:

   ```bash
   gh issue view 1412 --json state    # expect "CLOSED"
   ```

   If somehow not closed (rare), close manually with citation per `feedback_pr_traceability_signals`:

   ```bash
   gh issue close 1412 --comment "Closed by PR #${PR_NUM} (M2 wrap-up) and PR #1422 (M2A)."
   ```

7. If T005 (PR #1350 closure) deferred to "after wrap-up PR known", do it now with the real PR number.

**Files touched**: this is the commit+push+PR-open step.

**Validation**:
- [ ] Commit pushed and PR opened
- [ ] PR title and body match the spec
- [ ] PR body contains `Closes #1412`
- [ ] `<wrap-up-PR>` placeholders replaced in audit doc with the real PR number
- [ ] CI is green on the PR
- [ ] PR #1350 is closed (FR-011)
- [ ] After merge: issue #1412 is closed (FR-013)
- [ ] Reviewer agent approved

## Definition of Done

WP01 is **done** when ALL of the following are true:

- [ ] T001 verification block passed (all 4 checks)
- [ ] T002 CI gate verified intact
- [ ] T003 spec sync done OR verified-only with WP history note
- [ ] T004 audit doc annotated (6 entries + §4.6 + Top-5 footnote)
- [ ] T005 PR #1350 closed with explanatory comment
- [ ] T006 CHANGELOG `[Unreleased]` bullet added
- [ ] T007 PR opened, CI green, merged, #1412 auto-closed
- [ ] All `<wrap-up-PR>` placeholders replaced in audit doc
- [ ] Reviewer agent approved (will run the verification block one more time)

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| `docs/specs/admin-spa.md` is already current; T003 becomes a no-op | medium | none | Verify-first via grep. Record verified-only in WP history. Do not synthesize a fake edit. |
| Audit doc annotation format diverges from E-Pkg-05's idiom | low | low | Read E-Pkg-05's existing closure (line ~421) and mimic the pattern exactly. |
| PR #1350 has a salvageable artifact that gets discarded | low | medium | Read the full PR diff and comments before closing. Cherry-pick anything that survives the "pure dist rebuild" classification. |
| `<wrap-up-PR>` placeholder forgotten in some annotation | medium | low | Single sed pass + grep verification at end of T007. Reviewer also greps. |
| Issue #1412 footer doesn't auto-close (rare, per `feedback_partial_fix_closes_footer`) | low | low | Manually close post-merge. |
| CI fails on the PR (regression in CI scripts or workflow drift) | low | medium | This WP touches no code or workflow files. If CI fails, the regression predates this WP — escalate to the user; do NOT debug in this WP. |

## Reviewer guidance

The reviewer agent should:

1. Re-run the T001 verification block on the merged HEAD — all 4 checks pass.
2. Confirm `docs/audits/admin-spa-modernization-2026-05-10.md` has 6 `**CLOSED — ...**` annotations matching the E-Pkg-05 pattern, plus the §4.6 decision paragraph, plus the Top-5 row footnote.
3. Confirm citations include both `fe5f48fd1`/`PR #1422` AND the wrap-up PR (no `<wrap-up-PR>` placeholders left).
4. Confirm `docs/specs/admin-spa.md` either is already-accurate (T003 verify-only with WP history note) or has a minimal section-level edit (no rewrite).
5. Confirm PR #1350 is `CLOSED` with an explanatory comment that cites M2A and the §4.6 decision.
6. Confirm `CHANGELOG.md` `[Unreleased]` has a bullet referencing #1412 and #1422.
7. Confirm CI is green on the wrap-up PR.
8. Confirm PR footer contains `Closes #1412`.
9. Confirm `packages/admin/app/` was not touched (constraint C-001).
10. Confirm `.github/workflows/admin.yml` was not touched (constraint C-008) — unless explicitly salvaged from PR #1350 with rationale recorded.

## Implementation entry command

```bash
spec-kitty agent action implement WP01 --agent <your-agent-name>
```

(Invoke after `spec-kitty next --agent <your-agent-name> --mission 01KRJ9ZE` has allocated lane-a.)
