# Quickstart: Admin SPA Modernization Audit

For agents picking up any WP in this mission.

## Prereqs

- `gh auth status` — must be authenticated, repo scope.
- `bin/check-milestones` — runs clean (active Tracks visible).
- Read `spec.md`, `plan.md`, `research.md` in this directory before touching anything.

## Conventions (from `research.md`)

- **Corpus query**: `git log --first-parent main --oneline -- packages/<pkg>`
- **Classification**: `{broken, degraded, unsurfaced, no-op}` for drift; `{no-UI, minimal-UI, complete-UI}` for coverage.
- **Sizing**: XS ≤ 0.5d · S ≤ 2d · M ≤ 1w · L > 1w.
- **Citation**: every actionable entry has a commit hash, an issue/PR `#NNNN`, or a `no-op` rationale.
- **Track defaults**: cross-cutting → Track 1; ai/agentic → Track 2.

## File-handling rules

- **Never** modify a file under `packages/admin/` (C-001).
- **Never** modify a file under `packages/` outside `packages/admin/` (C-002).
- The only writable output paths in this mission are:
  - `docs/audits/admin-spa-modernization-2026-05-10.md` (created by WP03)
  - `kitty-specs/admin-spa-modernization-audit-01KRA3RV/**` (WP working files)
  - GitHub Issues (created via `gh`)

## Context-budget rules

Run heavy commands through the sandbox:

```
mcp__plugin_context-mode_context-mode__ctx_batch_execute(
  commands=[{label: "...", command: "git log --first-parent main ..."}],
  queries=["commits touching JSON:API attributes", "commits adding new fields", ...]
)
```

Only classified entries enter the audit doc and the conversation.

## Issue-filing template

When opening a follow-up issue:

```
gh issue create \
  --title "[admin-spa] <axis>: <one-line summary>" \
  --milestone "Track 1" \
  --label "admin-spa,audit-followup" \
  --body "$(cat <<'EOF'
**Audit source**: docs/audits/admin-spa-modernization-2026-05-10.md#<anchor>

**Axis**: <Drift | Coverage | Tooling | Envelope | Top5-Mission>

**Classification**: <broken|degraded|unsurfaced|no-op|no-UI|minimal-UI|stale-dep|envelope-defect>

**Size**: <XS|S|M|L>

**Citation(s)**: <commit hash, #PR, or no-op rationale>

**Affected files**: <packages/admin/...>

**Proposed remedy**: <one paragraph>

EOF
)"
```

After creating, amend the matching audit-doc row with `(#<issue>)`.

## Per-WP entry conditions

- **WP01** (Drift): proceed when `research.md` is committed.
- **WP02** (Coverage): may proceed in parallel with WP01.
- **WP03** (Tooling + Envelope + Assembly): proceed when WP01 and WP02 are merged-or-marked-done — needs entry lists to file Top 5 issues and stitch back-links.

## Done = ?

The mission is done when:
1. `docs/audits/admin-spa-modernization-2026-05-10.md` exists, contains all required sections, and lists Top 5 follow-up missions.
2. Every drift, coverage, tooling, envelope, and Top 5 entry has a GitHub issue with a Track milestone.
3. Every audit-doc entry carries its `(#issue)` back-link.
4. `git diff main -- packages/` is empty for this mission's branch.
