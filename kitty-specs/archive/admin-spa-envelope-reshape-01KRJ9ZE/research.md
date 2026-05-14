# Phase 0 Research — Admin SPA Envelope Re-shape (M2 wrap-up)

## Research question

The spec was written at 2026-05-14 against the audit dated 2026-05-10. Has any of the M2 scope already been completed by intervening work?

## Sources consulted

- `git log --oneline -5 packages/admin/package.json` (commit history of the manifest)
- Current `packages/admin/package.json` and `packages/admin/README.md` on `main`
- `gh pr view 1422` (the M2A pull request)
- `gh pr view 1350` (the pre-built tarball PR)
- `docs/audits/admin-spa-modernization-2026-05-10.md` §4.1, §4.3, §4.6, E-Pkg / E-Docs rows

## Decision 1: M2A already landed; treat overlapping FRs as verify-only

**Finding**: Commit `fe5f48fd1` ("chore(admin): tighten package envelope as private workspace member (M2A, #1412) (#1422)") merged 2026-05-11. PR #1422 body explicitly scopes itself as "Audit follow-up **M2A** (subset of M2 #1412)" and ships:

- `package.json`: `"private": true`, `engines.node >=22.12.0`, no `exports` map, no `main`/`types`, no `peerDependencies`, no `files` array, description updated.
- `README.md`: rewritten from 21 lines to ~55 lines (currently 63 lines on `main`).

**Decision**: FR-001..FR-006 and FR-008 are **already met**. The plan treats them as verify-only steps in `quickstart.md`; the implementing agent runs the grep/jq/wc checks but is not expected to edit those files.

**Alternatives considered**: 
- Re-do the M2A work as part of M2 wrap-up → rejected; would produce no-op commits and obscure the actual changes.
- Drop the satisfied FRs from the spec → rejected; spec is immutable post-specify, and the verification step is still load-bearing (catches regression).

## Decision 2: `engines.node >=22.12.0` (current) supersedes spec's `>=22.0.0`

**Finding**: spec FR-004 said `>=22.0.0`. M2A used `>=22.12.0` to match Nuxt 4.4.4's runtime constraint. The current value is **stricter than the spec** and was chosen for a documented reason.

**Decision**: Accept current value. FR-004 is met because `>=22.12.0` satisfies "MUST declare `engines.node >=22.0.0`" — the stricter constraint is a superset. No edit needed.

**Alternatives**: relax to `>=22.0.0` — rejected; would break the Nuxt 4.4.4 alignment for no benefit.

## Decision 3: PR #1350 is the right reconciliation target

**Finding**: PR #1350 is OPEN as of 2026-05-11 ("chore(admin-surface): update pre-built SPA dist"). The maintainer's status-quo monorepo-shape decision (no pre-built tarball model) makes this PR obsolete. The audit §4.6 ranks status-quo as option 1, M2A's body explicitly defers "Pre-built-tarball model from PR #1350" to M2-wrap-up.

**Decision**: Close PR #1350 with an explanatory comment when the M2 wrap-up PR merges. If PR #1350 has any salvageable artifact (e.g. a CI workflow tweak or a dist-build script change), cherry-pick it into the wrap-up PR before closing.

**Alternatives**:
- Keep PR #1350 open in case the decision flips later → rejected; an open PR collects merge-conflict debt and dependabot churn. Reopening is cheap if the decision flips.
- Merge PR #1350 first, then re-architect later → rejected; the status-quo decision is final per the maintainer's 2026-05-13 confirmation.

## Decision 4: Audit doc annotation convention follows E-Pkg-05's pattern

**Finding**: `docs/audits/admin-spa-modernization-2026-05-10.md` E-Pkg-05 already uses the pattern `~~No CI step verifies `build:contracts` output~~ **CLOSED — finding was stale**` with a follow-on explanation. This is the established in-file convention for closing audit entries.

**Decision**: Use the same strikethrough + "**CLOSED — ...**" pattern for E-Pkg-01..04, E-Pkg-06, E-Docs-01. Include commit citation (`fe5f48fd1`, PR #1422) and a one-line explanation of what closed it.

**Alternatives**: 
- Introduce a new status table → rejected; mid-doc table addition would break existing layout and require re-anchoring named anchors.
- Move closed entries to a separate "Resolved" appendix → rejected; same anchor-breakage problem and the audit is the historical record, not a working backlog.

## Decision 5: Single-WP mission shape

**Finding**: remaining work is doc edits (audit + spec) + admin actions (close PR #1350, close #1412 via PR footer). Total LOC change estimated at ~20-40 lines of markdown. No code, no tests, no CI changes.

**Decision**: ship as a single work package (`WP01 — M2 wrap-up`) on a single lane (`lane-a`). Reviewer is a separate agent to verify the audit-doc annotations and the PR #1350 closing comment.

**Alternatives**: 
- Two WPs (doc edits / admin actions) → rejected; admin actions are a single command at the end of WP1, no parallelism gain.
- Inline-edit on main (no Spec Kitty WP) → rejected; the user asked to drive M2 through Spec Kitty, and the audit closure is part of the deliverable that benefits from formal review.

## Decision 6: Spec sync is verify-first

**Finding**: `docs/specs/admin-spa.md` may already reflect the private-app shape (it's the canonical spec and is updated when behavior changes; M2A might have touched it). The plan's FR-009 step is verify-first.

**Decision**: Implementing agent first greps `docs/specs/admin-spa.md` for the current Distribution language and the role of `build:contracts`. If the spec is already accurate, FR-009 is marked verified-only with a WP-findings note. If the spec is stale, a minimal section-level edit syncs it. No full rewrite.

**Alternatives**: blind rewrite — rejected; risks erasing nuance and creates merge-conflict surface for unrelated in-flight spec edits.

## Decision 7: NFR-006 mission-clock starts 2026-05-14

**Finding**: mission `created_at` is 2026-05-14T03:55:08Z. Spec NFR-006 says "Calendar elapsed ≤ 7 days from mission `created_at` to merge".

**Decision**: target merge by 2026-05-21. Realistically achievable as a same-day or next-day single-PR mission given the small scope and the doc-only changes.

## Unresolved clarifications

None. All spec ambiguities resolved at specify-time. Phase 1 design can proceed.
