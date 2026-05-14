# Implementation Plan: Admin SPA Envelope Re-shape & Build Pipeline (M2 wrap-up)

**Mission**: `admin-spa-envelope-reshape-01KRJ9ZE` (mid8 `01KRJ9ZE`)
**Branch**: `main` → `main` (no worktree at planning time; one lane worktree at implement time)
**Date**: 2026-05-14
**Spec**: [spec.md](spec.md)
**Tracking issue**: [#1412](https://github.com/waaseyaa/framework/issues/1412)

## Summary

M2 was specified before discovery — most of its work is already done. Pull request [#1422](https://github.com/waaseyaa/framework/pull/1422) ("chore(admin): tighten package envelope as private workspace member (M2A, #1412)") landed on 2026-05-11 and shipped:

- `packages/admin/package.json`: `"private": true`, `engines.node >=22.12.0`, no `exports` map, no `main`/`types`, no `peerDependencies`, description updated, `files` array removed.
- `packages/admin/README.md`: rewritten from 21 lines to 63 lines covering stack, develop/test/build commands, the `build:contracts` verification gate, i18n, modernization audit pointer.

That commit satisfies **FR-001..FR-006** and **FR-008** as already-met. M2 therefore becomes a **wrap-up mission**: doc-sync, audit-doc annotation to record the closure, PR #1350 reconciliation, final verification, and #1412 closure. The mission ships as a single PR consisting of doc-only edits plus an issue/PR closure step. No `packages/admin/` code touched.

## Technical Context

**Language/Version**: Markdown (docs), JSON (audit annotations are markdown-table edits)
**Primary Dependencies**: none for the changes themselves; `gh` CLI for issue/PR closure
**Storage**: N/A
**Testing**: Existing CI gates run on the merging PR — `admin/contracts`, `admin/build`, `admin/integration`, `admin/adapters`. These cover regression risk for the already-landed envelope changes.
**Target Platform**: GitHub-hosted repo (docs + audit + issues + PRs)
**Project Type**: single (monorepo, doc-only PR)
**Performance Goals**: N/A (doc edits)
**Constraints**: must not touch `packages/admin/app/`; must not modify `.github/workflows/admin.yml`; must not bump dependencies
**Scale/Scope**: ~4-6 files edited, 1 issue closed, 1 PR closed

## Discovery findings (Phase 0 → research.md)

During planning, three discoveries changed the mission's effective scope:

1. **M2A already landed** (commit `fe5f48fd1`, PR #1422, merged 2026-05-11). All `package.json` shape changes (FR-001..FR-005) and the README rewrite (FR-008) are done.
2. **README is already 63 lines** — well within the 50–80 line target (NFR-005 already met).
3. **PR #1350 is still OPEN** as of 2026-05-11 ("chore(admin-surface): update pre-built SPA dist"). The maintainer's status-quo monorepo-shape decision makes PR #1350 obsolete; it needs explicit closure.

These findings are recorded in [research.md](research.md). They do **not** invalidate the spec — they refine it. The spec already covers verification-only paths via wording like "MUST validate against npm schema" (FR-001) and "MUST verify" (FR-007, FR-012). The plan treats already-met FRs as **verify steps in the quickstart**, not as new work.

## Charter Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Charter (compact mode, template `software-dev-default`):

- **DIR-001 / DIR-002 / DIR-003**: Generic software-dev directives (test coverage, branching, commit hygiene). M2 wrap-up complies trivially — doc-only, no test surface to grow, normal commit/branching, traceability via `#1412` and mission slug.
- **Languages**: php, typescript. M2 touches neither codepath; only markdown.
- **Paradigms**: domain-driven-design. N/A for doc-only wrap-up.
- **Tools**: git, spec-kitty. Used.
- **Branch Strategy**: matches (`main` → `main`).

**Status**: PASS. No complexity to justify. Re-check after Phase 1 design: still PASS — Phase 1 produces no new code surface.

## Project Structure

### Documentation (this feature)

```
kitty-specs/admin-spa-envelope-reshape-01KRJ9ZE/
├── plan.md           # This file
├── spec.md           # Already written at specify
├── research.md       # Phase 0 — discovery (M2A already landed)
├── data-model.md     # N/A — no domain entities; stub explains why
├── quickstart.md     # Phase 1 — verification commands + closure steps
├── contracts/        # N/A — no API contracts; stub explains why
├── checklists/
│   └── requirements.md   # Already written at specify
└── tasks/            # Populated by /spec-kitty.tasks
```

### Source files touched at implement-time (repository root)

```
packages/admin/.gitignore                                  # verify-only (no edit expected)
packages/admin/package.json                                # verify-only (no edit expected — M2A landed)
packages/admin/README.md                                   # verify-only (no edit expected — M2A landed)
docs/specs/admin-spa.md                                    # spec sync — add note that build:contracts is CI verification artifact + confirm private-app shape
docs/audits/admin-spa-modernization-2026-05-10.md          # annotate E-Pkg-01..04, E-Pkg-06, E-Docs-01 as CLOSED with citation to fe5f48fd1 and PR #1422; record §4.6 status-quo decision
CHANGELOG.md                                               # [Unreleased] bullet: "M2 wrap-up: audit closure for admin SPA envelope reshape"
```

### Out-of-tree actions

- `gh pr close 1350` — close as obsolete (recommended), with closing comment citing M2A and the status-quo decision.
- `gh issue close 1412` — closed automatically by `Closes #1412` in the merging PR footer.

**Structure Decision**: doc-only PR. No package code, no CI workflow changes, no test additions.

## Approach

### Single WP (recommended)

Given the small scope, this mission ships as **one work package** producing **one PR**:

**WP01 — M2 wrap-up: audit closure + spec sync + PR #1350 reconciliation**

Scope:
1. Verify FR-001..FR-007 and FR-012 against current `main` state (commands in `quickstart.md`). Document any discrepancy in WP findings.
2. Edit `docs/specs/admin-spa.md` to reflect the private-app shape and the role of `build:contracts` as a CI verification artifact (not a published export). Section-level changes only — do not rewrite the doc.
3. Edit `docs/audits/admin-spa-modernization-2026-05-10.md`:
   - Mark E-Pkg-01..04, E-Pkg-06, E-Docs-01 as **CLOSED** with citation `(closed by M2A — commit fe5f48fd1, PR #1422; M2 wrap-up — commit <this PR's merge sha>, PR <this PR>)`.
   - In §4.6, record "**Decision (2026-05-13)**: status quo (option 1) adopted. M2 wrap-up confirms no pre-built tarball model."
   - In the Top-5 row for M2, append `(M2A: PR #1422 envelope + README; M2 wrap-up: PR <this PR> doc sync + audit closure)`.
4. `gh pr close 1350` with a closing comment: "Closing as obsolete: M2A (#1422) landed the envelope reshape; M2 wrap-up adopts status-quo monorepo shape (no pre-built tarball model). Reopen if the dist-rebuild use case re-emerges."
5. Update `CHANGELOG.md` `[Unreleased]` with a bullet under "Changed" or "Documentation": `Close out admin-spa M2 mission: audit annotations + spec sync; PR #1350 closed as obsolete; M2A (#1422) already shipped envelope + README. (#1412)`.
6. Open the PR with `Closes #1412` in the footer.

Reviewer agent runs the verification grep one more time before approve.

**Why a single WP**: scope is doc-only, low risk, no codepath touched. Splitting into multiple WPs would create lane-coordination overhead with no parallelism benefit (every step touches the same handful of files).

### Alternative considered: two-WP split (rejected)

- WP1 = audit + spec sync (PR), WP2 = PR #1350 closure (admin action).

Rejected because GitHub PR/issue closure is a 1-command action that the implementing agent does at the end of WP1; pulling it into its own WP adds ceremony, not value.

## Acceptance gates (mapped from spec)

| FR/NFR | Implementation step | Verification |
|---|---|---|
| FR-001 .. FR-005 | (already met by M2A) | `jq '.private, .engines, .exports // "absent", .main // "absent", .peerDependencies // "absent"' packages/admin/package.json` — expect `true`, `{"node":">=22.12.0"}`, `"absent"`, `"absent"`, `"absent"` |
| FR-006 | (already met by M2A) | `jq '.scripts."build:contracts"' packages/admin/package.json` — expect non-null |
| FR-007 | verify `.gitignore` | `grep -E '^/?dist/?$' packages/admin/.gitignore` — expect a match |
| FR-008 | (already met by M2A) | `wc -l packages/admin/README.md` — expect a value in `[50, 80]` |
| FR-009 | spec edit | grep `docs/specs/admin-spa.md` for the new "build:contracts is CI verification artifact" note and for "private package" phrasing |
| FR-010 | audit edit | grep `docs/audits/admin-spa-modernization-2026-05-10.md` for "CLOSED" annotations on E-Pkg-01..04, E-Pkg-06, E-Docs-01 with citation to `fe5f48fd1` AND the wrap-up PR sha |
| FR-011 | `gh pr close 1350` | `gh pr view 1350 --json state` returns `"CLOSED"` |
| FR-012 | grep | `rg "@waaseyaa/admin" --type ts --type js --type json -g '!**/node_modules/**' -g '!**/package-lock.json' -g '!packages/admin/**'` returns empty |
| FR-013 | PR footer | `gh issue view 1412 --json state` returns `"CLOSED"` after merge |
| NFR-001 | CI | `admin/build`, `admin/integration` jobs green on the wrap-up PR |
| NFR-002 | CI | `admin/build` job green |
| NFR-003 | CI | `admin/contracts` job green |
| NFR-004 | CI | all four admin jobs green |
| NFR-005 | line count | covered by FR-008 verification |
| NFR-006 | calendar | mission `created_at` 2026-05-14; target merge ≤ 2026-05-21 |

## Risks & mitigations (refined from spec)

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Audit-doc annotation format conflicts with the way other audit entries are closed elsewhere in the file | low | very low | grep the file for prior "closed" annotations before writing; mimic the existing convention (E-Pkg-05 already uses "**CLOSED — finding was stale**" pattern). |
| `docs/specs/admin-spa.md` already documents the private-app shape and the spec edit is a no-op | medium | very low | verify-first: grep the spec for current Distribution language; if already accurate, mark FR-009 verified-only with a comment in the WP finding rather than synthesizing a fake edit. |
| PR #1350 has follow-on commits or discussion that suggest closing is wrong | low | low | the implementing agent reads PR #1350 body, comments, and commits before closing; if anything is salvageable (e.g. a CI tweak), cherry-pick before closing. |
| `engines.node >=22.12.0` (current) vs `>=22.0.0` (spec FR-004) | already resolved | none | the stricter value is correct per M2A's rationale (matches Nuxt 4.4.4 constraint). Spec FR-004's threshold was a baseline minimum; current value exceeds it, so FR-004 is met. No edit. |

## Mission acceptance summary

The mission is **accepted** when:

1. The wrap-up PR merges with `Closes #1412` in the footer (FR-013).
2. PR #1350 is closed (FR-011).
3. `docs/audits/admin-spa-modernization-2026-05-10.md` shows the 6 audit entries closed with traceable citations (FR-010).
4. `docs/specs/admin-spa.md` reflects the private-app shape (FR-009) or is verified as already-accurate.
5. The verification grep returns empty (FR-012).
6. CI is green on the wrap-up PR (NFR-001..NFR-004).

Then run `spec-kitty merge` and post-merge cleanup per [`feedback_pr_traceability_signals`](MEMORY.md) and `docs/specs/workflow.md`: edit the GitHub Release notes if this lands during a release cycle, otherwise leave it for the next release-cut.

## Complexity Tracking

*Fill ONLY if Charter Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|

(none — charter check passes)

## Branch contract (restated for /spec-kitty.tasks)

- **Current branch at plan-time**: `main`
- **Planning / base branch**: `main`
- **Final merge target**: `main`
- **branch_matches_target**: `true`

The implement worktree at `/spec-kitty.next` time will be `.worktrees/admin-spa-envelope-reshape-01KRJ9ZE-lane-a/` (single lane; single WP).
