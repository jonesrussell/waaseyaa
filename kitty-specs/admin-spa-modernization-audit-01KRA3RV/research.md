# Research: Admin SPA Modernization Audit

**Mission**: `admin-spa-modernization-audit-01KRA3RV`
**Phase**: 0 — Outline & Research
**Date**: 2026-05-10

## Purpose

This document records the methodology, rubrics, source corpus, and tool-selection decisions for the audit. The audit document itself (`docs/audits/admin-spa-modernization-2026-05-10.md`) is produced by the work packages; this research file is the methodology hand-off so any agent picking up a WP applies the same conventions.

## Decision 1 — Drift corpus selection

**Decision**: Use `git log --first-parent main -- <package_path>` for each drift-corpus package, accumulated over the full v1.x lifetime (= entire `main` history up to the audit date).

**Rationale**: Mission squash-merges land as single first-parent commits on `main`. Walking `--first-parent` aligns with the canonical landing history and avoids re-counting intermediate WP commits that were collapsed at merge time. It also keeps the citable commit hash stable across rebases inside the mission worktree.

**Alternatives considered**:
- Full-graph `git log` — produces noisier output, citations are less stable.
- Date window only (e.g. last 6 months) — rejected per spec FR-004 (full v1.x lifetime required).
- `git rev-list --no-merges` — drops squash-merge metadata that we want.

## Decision 2 — Drift classification rubric

**Decision**: Every drift entry carries one classification from `{broken, degraded, unsurfaced, no-op}`.

| Class | Definition |
|-------|-----------|
| `broken` | Admin SPA reads or writes against an API shape that no longer exists. Surface visibly errors, 404s, or crashes for end users. |
| `degraded` | Surface still functions but produces incorrect or misleading results (e.g. stale schema, missing fields, wrong cast, ignoring tenancy). |
| `unsurfaced` | Backend capability exists but the admin SPA has no UI for it. Maps to Section 2 (Coverage) for new subsystems; lives in Section 1 (Drift) when the capability was added to an *existing* admin-surfaced flow but not exposed. |
| `no-op` | Backend change had no admin-side impact (refactor, internal optimization, test-only change, doc/spec change). Listed so future readers know it was considered. |

**Rationale**: This four-class axis is exhaustive for backend → SPA impact and lets reviewers triage at a glance. Sizing (XS/S/M/L) is orthogonal to classification.

## Decision 3 — Coverage walk authority

**Decision**: The CLAUDE.md orchestration table is authoritative for the L2–L6 subsystem inventory. Packages present in `packages/` but missing from the table are listed with the flag `orchestration-table-orphan` so we record both the coverage gap *and* the documentation gap.

**Rationale**: A single source of truth prevents drift inside the audit itself. CLAUDE.md is the project's published index; using it pins the inventory the audit consumer will themselves use.

## Decision 4 — Sizing rubric

**Decision**: XS ≤ 0.5 day · S ≤ 2 days · M ≤ 1 week · L > 1 week (decomposition expected).

This rubric appears at the top of the audit document so every consumer of any entry reads the same definition.

**Rationale**: Aligns with the project's typical mission scoping. L items are explicit candidates for further spec-kitty decomposition rather than direct execution.

## Decision 5 — Context-budget strategy

**Decision**: All commit-log archaeology, file scans, and cross-reference grep'ing run through `mcp__plugin_context-mode_context-mode__ctx_batch_execute` or `ctx_execute_file`. Only the classified, summarized entries (one row per audit entry) land in the audit document or in the conversation.

**Rationale**: The full v1.x lifetime corpus could be hundreds of commits across ten-plus packages. Streaming raw logs into the main conversation context would burn budget for no audit value. The sandbox tools index the output and let us extract only the entries that survive classification.

**Practical pattern per WP**:
```
ctx_batch_execute(
  commands=[
    {label: "<package>_log", command: "git log --first-parent main --oneline --no-merges --since=v1.0 -- packages/<pkg>"},
    {label: "<package>_diff_paths", command: "git log --first-parent main --name-only --no-merges --pretty=format:'%h %s' -- packages/<pkg>"},
  ],
  queries=["commits affecting JSON:API attribute shape", "commits affecting EntityType field metadata", ...]
)
```

## Decision 6 — Citation convention

**Decision**: Every actionable entry carries at least one of: a commit hash (`[a-f0-9]{7,40}`), an issue or PR reference (`#NNNN`), or — for `no-op` entries only — a one-line rationale (no citation required because the entry is explicitly inert).

**Rationale**: NFR-003 requires zero unsupported assertions. Pinning the citation taxonomy here keeps WP outputs uniform.

## Decision 7 — GitHub issue lifecycle and ownership

**Decision**:
- WP01 (Drift) files drift issues.
- WP02 (Coverage) files coverage issues.
- WP03 (Tooling + Envelope + Assembly) files tooling and envelope issues *and* the Top 5 follow-up mission issues, then back-fills audit-entry rows with their issue numbers.

Each issue body contains a markdown anchor link to the audit-doc heading or row that spawned it. Each audit entry is amended with `(#issue-N)` once the issue is created.

**Rationale**: Bidirectional linking (NFR-005) requires both directions. Creating issues per-WP and stitching the back-links during WP03 keeps the dependency graph linear.

## Decision 8 — Track milestone defaults

**Decision**:
- Cross-cutting framework-alignment drift → Track 1 (Entity system & hydration).
- Pure feature coverage gaps for AI/agentic subsystems (`ai-*`, MCP, bimaaji, telescope agentic surfaces) → Track 2 (Bimaaji & agentic).
- Admin-only tooling/envelope issues that don't fit either Track → Track 1 by default, with an explicit note proposing a new admin-focused Track if more than five such issues accumulate. Proposal lives in the audit doc; new-Track creation is out of scope for this mission.

**Rationale**: `bin/check-milestones` reports two active Tracks. Defaulting cross-cutting work to Track 1 matches the framework's current emphasis on entity/hydration maturity; agentic work has its own Track. Capping admin-only debt under Track 1 with a proposed-new-Track threshold avoids forcing premature governance decisions inside an audit mission.

## Decision 9 — Top 5 selection criteria

**Decision**: The "Top 5 follow-up missions" section ranks proposed missions by a tuple `(blast_radius_descending, prerequisites_first, size_ascending_at_tie)`:
- `blast_radius` = number of audit entries the proposed mission would close.
- `prerequisites_first` = missions that unblock others rank higher.
- `size_ascending_at_tie` = smaller missions break ties to favor early wins.

**Rationale**: Maintainers funding the next quarter of admin SPA work want high-blast-radius items first, but they also want momentum. Tie-breaking on size lets a quick win surface ahead of a similarly impactful larger item.

## Decision 10 — In-flight overlap detection

**Decision**: Before classifying any drift or coverage entry, run a quick check via `gh pr list --search 'packages/admin'` and `gh issue list --search 'admin SPA' --state open`. Any in-flight match is noted on the entry with the open PR/issue number and a flag `(in-flight)`.

**Rationale**: FR-015 requires this. Pinning it here means each WP applies it without re-deriving the convention.

## Source inventory (read-only)

| Source | Used by | Purpose |
|--------|---------|---------|
| `packages/admin/` (tree) | WP01, WP02, WP03 | File-path citations and current-state inspection |
| `packages/{entity,entity-storage,field,api,access,auth,routing,user,config,telescope}` | WP01 | Drift corpus |
| `packages/foundation/src/Http/` | WP01 | Drift corpus (HTTP inbound shape, kernel) |
| `CLAUDE.md` orchestration table | WP02 | Subsystem authority |
| `docs/specs/admin-spa.md` | WP01, WP03 | Current SPA contract for diffing against actual code |
| `docs/specs/*` (relevant files) | WP01, WP02 | Background context per subsystem |
| `bin/check-milestones` | WP03 | Track milestone validation |
| `tools/drift-detector.sh` | WP03 | Spec freshness signal (informational, not gating) |
| GitHub Issues + PRs API (via `gh`) | All WPs | Citation + issue creation |

## Output

This methodology is consumed by `/spec-kitty.tasks` to materialize WP01/WP02/WP03 and then by each WP's agent during execution. No further research is required before `/spec-kitty.tasks`.
