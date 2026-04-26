# GitHub milestones and issues audit — waaseyaa/framework

**Date:** 2026-04-26  
**Scope:** Repository `waaseyaa/framework` only.  
**Method:** `./bin/check-milestones`, `gh issue list`, `gh api …/milestones`, Python analysis on open-issue JSON (`--limit 500`, 127 issues returned).

**Note:** Repo governance is **Spec Kitty–first** ([docs/specs/workflow.md](../specs/workflow.md)). This document is a **GitHub hygiene** snapshot after the 2026-04-26 cleanup pass.

## Executive summary

1. **Milestone model:** GitHub still uses **five open Track milestones** (Track 1–5), consistent with [workflow.md § GitHub milestone tracks](../specs/workflow.md).
2. **Machine drift:** `./bin/check-milestones` reports **no drift** — all open issues have a milestone; every open milestone has at least one open issue.
3. **Issue quality:** **0** open issues with body under 20 characters.
4. **Staleness (updatedAt):** **88** open issues have had **no update in 14+ days** (latest activity clustered around 2026-04-11–2026-04-12). **0** open issues exceed **45** days without update. This is **backlog dormancy**, not missing milestones — no bulk `gh` mutations were applied in this pass (mass “ping” comments would be noisy).
5. **Duplicates:** **0** pairs with identical titles. Title word-overlap clusters mostly reflect intentional **layer / epic families** (PHPDoc @covers, remediation series), not merge candidates.
6. **PR traceability (Rule 4):** Spot-check of recently merged PRs shows **issue-linked** titles where a filing issue exists (e.g. `feat(#1339): …`, `feat(#1347): …`). PRs without `#N` remain acceptable when work is spec- or chore-driven, per workflow guidance.

## GitHub milestones (inventory)

`gh api repos/waaseyaa/framework/milestones` — open milestones only.

| Title | Open | Closed |
|-------|-----:|-------:|
| Track 1 — Entity system & hydration | 73 | 4 |
| Track 2 — Bimaaji & agentic | 8 | 19 |
| Track 3 — Parity & performance | 38 | 6 |
| Track 4 — Schema evolution | 5 | 0 |
| Track 5 — Ecosystem identity | 3 | 10 |

## Open issues — hygiene checks

| Check | Result |
|--------|--------|
| Open count | 127 |
| `no:milestone` | **0** |
| Body under 20 chars | **0** |
| Stale ≥ 14 days | 88 |
| Stale ≥ 45 days | 0 |
| Exact duplicate titles | 0 |

## Spec Kitty vs GitHub

- **Execution map:** Missions and work packages live under `.kittify/` (partially gitignored per root `.gitignore`); this audit does not diff mission files.
- **When both exist:** Link filing issues from missions or PR bodies (workflow Rule 1 / M11 templates). **Rule 4:** Prefer `type(#N):` in PR titles when a GitHub issue exists; otherwise cite mission/WP in title or body.

## Action queue (this pass)

| # | Action | Status |
|---|--------|--------|
| 1 | Assign Track milestones to `no:milestone` issues | **N/A** — none |
| 2 | Close empty open milestones | **N/A** — none |
| 3 | Close exact-duplicate issues | **N/A** — none |
| 4 | Optional follow-up (human): batch-triage the **88** dormant issues (close superseded remediation tickets, consolidate epics, or schedule work) | Deferred |

## Verification commands

```bash
cd /path/to/waaseyaa && ./bin/check-milestones
gh issue list --repo waaseyaa/framework --state open --search "no:milestone" --limit 200
gh api repos/waaseyaa/framework/milestones --jq '.[] | select(.state=="open") | {title, open_issues, closed_issues}'
```

**Expected:** `check-milestones` prints no `WARNING` lines for missing milestones or empty open milestones; `no:milestone` search returns an empty list.

## Prior audit

Previous snapshot: [2026-04-25-github-milestones-issues-audit.md](./2026-04-25-github-milestones-issues-audit.md).
