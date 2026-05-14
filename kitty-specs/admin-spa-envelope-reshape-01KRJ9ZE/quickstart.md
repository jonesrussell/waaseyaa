# Quickstart — M2 wrap-up verification & implementation

This document is the operational runbook for the WP01 implementing agent. Run the verification block first; if every check passes, the M2A work is intact and you only need to do the doc edits + admin actions.

## Pre-flight: branch & worktree

```bash
# From the worktree spec-kitty allocated for this WP:
pwd                                       # should match .worktrees/admin-spa-envelope-reshape-01KRJ9ZE-lane-a/
git branch --show-current                 # should be the WP01 feature branch
git log --oneline -1                      # should descend from main
```

## Step 1: Verify M2A-landed FRs (FR-001..FR-006, FR-008, FR-012)

```bash
# FR-001..FR-005 — package.json shape
jq '{private, engines, exports: (.exports // "absent"), main: (.main // "absent"), peerDeps: (.peerDependencies // "absent")}' packages/admin/package.json
# Expect: {"private": true, "engines": {"node": ">=22.12.0"}, "exports": "absent", "main": "absent", "peerDeps": "absent"}

# FR-006 — build:contracts script still wired
jq '.scripts."build:contracts"' packages/admin/package.json
# Expect: a non-null string like "tsc -p tsconfig.contracts.json"

# FR-007 — dist gitignored
grep -E '^/?dist/?$' packages/admin/.gitignore
# Expect: a match (e.g. `dist` or `/dist` or `dist/`)

# FR-008 — README line count
wc -l packages/admin/README.md
# Expect: a value in [50, 80] inclusive

# FR-012 — no @waaseyaa/admin import statements anywhere
rg "@waaseyaa/admin" --type ts --type js --type json \
  -g '!**/node_modules/**' \
  -g '!**/package-lock.json' \
  -g '!packages/admin/**' \
  -g '!kitty-specs/**' \
  -g '!docs/**'
# Expect: empty output
```

If any expectation fails, **stop**. Re-verify against the spec and either (a) record a regression in WP findings + fix or (b) escalate to the user before continuing. Do NOT do the doc edits while the foundation is broken.

## Step 2: Verify CI gate is intact (NFR-001..NFR-004 spot check)

```bash
# Confirm the workflow file is unchanged
gh workflow view admin.yml --yaml 2>/dev/null | head -80 || cat .github/workflows/admin.yml | head -80
# Expect: an admin/contracts job that runs build:contracts and admin/build, admin/integration, admin/adapters jobs.
```

No edits to `.github/workflows/admin.yml` are permitted by the spec (constraint C-008).

## Step 3: Spec sync (FR-009) — verify-first

```bash
# Check whether docs/specs/admin-spa.md already reflects the private-app shape
rg -n "private|monorepo-internal|not published|build:contracts" docs/specs/admin-spa.md | head -20

# Check whether the spec mentions build:contracts in a way that implies a published export
rg -n "exports.*dist|installable|@waaseyaa/admin.*import|consumers.*import" docs/specs/admin-spa.md | head -20
```

- If the spec already says "private package", "monorepo-internal", or "verification artifact": FR-009 is verified-only. Record this in WP findings and skip to Step 4.
- If the spec implies an installable/exportable shape: edit the relevant section(s) to add the private-app framing and the "build:contracts is a CI verification artifact, not a published export" note. **Minimum-viable edit only** — do not rewrite the spec.

## Step 4: Audit doc annotation (FR-010)

Open `docs/audits/admin-spa-modernization-2026-05-10.md` and apply the following edits using the existing E-Pkg-05 closure pattern (strikethrough + `**CLOSED — ...**`):

For **E-Pkg-01** (line ~412): prepend the finding cell with `~~` and append ` **CLOSED — closed by M2A (commit fe5f48fd1, PR #1422). `exports` map removed; package now declares `private: true`. M2 wrap-up confirms (PR <wrap-up-PR>).**`.

For **E-Pkg-02** (line ~413): same pattern. `**CLOSED — closed by M2A (commit fe5f48fd1, PR #1422). `engines.node = >=22.12.0` (stricter than the audit-suggested >=22.0.0, matches Nuxt 4.4.4 constraint). M2 wrap-up confirms.**`

For **E-Pkg-03** (line ~414): `**CLOSED — closed by M2A (commit fe5f48fd1, PR #1422). `"private": true` declared; package is monorepo-internal, not published. No `publishConfig` needed.**`

For **E-Pkg-04** (line ~415): `**CLOSED — closed by M2A and M2 wrap-up decision (2026-05-13). No `peerDependencies` added: package is private and has zero published consumers (E-Pkg-06).**`

For **E-Pkg-06** (line ~422): `**CLOSED — confirmed YAGNI by M2A and M2 wrap-up verification grep. Zero `@waaseyaa/admin` import statements across `waaseyaa/framework` and `waaseyaa.org`. Private-app shape adopted.**`

For **E-Docs-01** (line ~428): `**CLOSED — closed by M2A (commit fe5f48fd1, PR #1422). README expanded from 21 lines to ~63 lines covering stack, develop/test/build commands, build:contracts verification gate, i18n, and modernization-audit pointer. M2 wrap-up verifies.**`

For **§4.6 Monorepo-shape recommendation** (around line ~440-446): append a final paragraph:

```
**Decision (2026-05-13, M2 wrap-up)**: Option 1 (status quo) adopted. The admin SPA remains the only JS-only package in a PHP monorepo. The `exports` map and `dist/` references were removed by M2A (commit fe5f48fd1, PR #1422). PR #1350 (the manual dist-rebuild PR) is closed as obsolete in the same window. The pre-built tarball model (option 2) and the sibling-repo model (option 3) remain documented as escape hatches if a future external Waaseyaa app justifies them.
```

For the **Top 5 mission table row M2** (around line ~46): append ` (M2A: PR #1422 envelope + README; M2 wrap-up: PR <wrap-up-PR> doc sync + audit closure)` to the row's Issue column or in a footnote.

Replace `<wrap-up-PR>` with the actual PR number once the PR is created (use a placeholder until then, then update in a follow-up commit on the same branch).

## Step 5: PR #1350 reconciliation (FR-011)

```bash
# Read PR #1350 first
gh pr view 1350 --json title,body,state,files,commits
gh pr diff 1350 | head -100
```

- If PR #1350 contains anything salvageable (CI script tweak, deploy doc), cherry-pick it into this WP's branch first.
- Then close with a comment:

```bash
gh pr close 1350 --comment "Closing as obsolete: M2A (#1422) landed the envelope reshape, and M2 wrap-up (PR <wrap-up-PR>) adopts status-quo monorepo shape (no pre-built tarball model — see docs/audits/admin-spa-modernization-2026-05-10.md §4.6 \"Decision (2026-05-13)\"). The dist-rebuild workflow this PR represents is no longer needed because the admin SPA is now a private workspace member served through admin-surface, not published as a tarball. Reopen if the dist-rebuild use case re-emerges."
```

## Step 6: CHANGELOG entry

Append to `CHANGELOG.md` under `[Unreleased]` → `Changed` (or `Documentation` if that section exists):

```
- Close out admin-spa M2 mission: doc sync in `docs/specs/admin-spa.md`, audit annotations marking E-Pkg-01..04, E-Pkg-06, E-Docs-01 as closed, and status-quo monorepo-shape decision recorded. PR #1350 closed as obsolete. M2A (#1422) shipped envelope + README on 2026-05-11. (#1412)
```

## Step 7: Open the PR

Branch + commit conventions:
- Branch: derive from the WP slug spec-kitty assigns (typically `admin-spa-envelope-reshape-01KRJ9ZE-wp01-...`).
- Commit subject: `docs(admin-spa-m2): close audit entries + sync spec + reconcile PR #1350`.
- Commit body: bullet the deliverables; cite `fe5f48fd1` and `#1422`; include `Refs #1412`.
- PR body: include `Closes #1412` (FR-013).

```bash
gh pr create \
  --title "docs(admin-spa-m2): close audit entries + sync spec + reconcile PR #1350" \
  --body "$(cat <<'EOF'
## Summary

Closes out admin-spa **M2** (#1412) as a wrap-up mission. The envelope reshape itself already shipped in M2A (PR #1422, commit `fe5f48fd1`) on 2026-05-11. This PR:

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

- `npm run build:contracts` / `npm test` / `npm run typecheck` (existing CI re-runs).
- `rg "@waaseyaa/admin"` across the workspace confirms zero importers (FR-012).
- `wc -l packages/admin/README.md` returns a value in `[50, 80]` (FR-008 / NFR-005).

## Refs

- Closes #1412
- Refs PR #1422 (M2A)
- Refs PR #1350 (closed in this PR)
- Audit source: `docs/audits/admin-spa-modernization-2026-05-10.md#m2`
- Mission: `kitty-specs/admin-spa-envelope-reshape-01KRJ9ZE/`

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

## Step 8: Post-merge cleanup (orchestrator does this after merge)

Per `feedback_pr_traceability_signals` in memory:

```bash
# Verify the issue auto-closed via the PR footer
gh issue view 1412 --json state    # expect "CLOSED"

# If not closed (rare), close manually with citation
# gh issue close 1412 --comment "Closed by PR <wrap-up-PR> (M2 wrap-up) and PR #1422 (M2A)."

# Edit the GitHub Release notes if a release tag is cut in this window
# gh release edit v<X.Y.Z> --notes-file <notes-file>
```

## Step 9: Archive the mission

```bash
spec-kitty merge --mission 01KRJ9ZE
# If merge state is opaque, fall back to the manual archive path per feedback_archive_stale_missions:
#   mv kitty-specs/admin-spa-envelope-reshape-01KRJ9ZE kitty-specs/archive/admin-spa-envelope-reshape-01KRJ9ZE
```

## Acceptance summary for the implementing agent

When all of the following are true, mark WP01 done:

- [ ] Verification block (Step 1) passed in full
- [ ] Spec sync (Step 3) done or verified-only with WP findings note
- [ ] Audit doc annotations (Step 4) applied to 6 audit entries + §4.6 + Top-5 row
- [ ] PR #1350 closed with explanatory comment (Step 5)
- [ ] CHANGELOG entry added (Step 6)
- [ ] PR opened with `Closes #1412` footer (Step 7)
- [ ] CI green on the PR (NFR-001..NFR-004)
- [ ] Reviewer agent approved
