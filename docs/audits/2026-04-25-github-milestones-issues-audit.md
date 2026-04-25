# GitHub milestones and issues audit — waaseyaa/framework

**Date:** 2026-04-25  
**Scope:** Repository `waaseyaa/framework` only.  
**Method:** `gh api` / `gh issue list` / `gh pr list` (authenticated CLI). Open issues fetched with `--limit 5000` (127 total).

**Note:** Repo governance is now **Spec Kitty–first** (`docs/specs/workflow.md`). This document remains a **GitHub hygiene** snapshot and template for optional issue/milestone audits, not the primary execution ledger.

## Executive summary

1. **Milestone model drift:** GitHub has **five open milestones** named **Track 1–5**. [docs/specs/workflow.md](../specs/workflow.md) documents **semantic version milestones** (v0.7–v2.0) with no Track naming. There are **no** separate GitHub milestones titled `v1.5`, `v1.6`, etc. Spec and GitHub naming were out of sync.
2. **Issue triage:** **13** open issues had **no milestone** (mostly new PHPDoc/@covers and DX follow-ups). All had substantive bodies; **0** open issues with empty/short body. Staleness (14d / 45d): **0** / **0** against snapshot `updatedAt` (backlog recently active).
3. **PR convention:** Many recent merged PRs use Conventional Commits **without** `(#N)` in the title — informational; enforcement remains template + review.

## GitHub milestones (full inventory)

`gh api repos/waaseyaa/framework/milestones?state=all&per_page=100` — **all** milestones returned (single page).

| # | State | Open | Closed | Title |
|---|--------|------|--------|--------|
| 68 | open | 68 | 4 | Track 1 — Entity system & hydration |
| 69 | open | 5 | 19 | Track 2 — Bimaaji & agentic |
| 70 | open | 35 | 6 | Track 3 — Parity & performance |
| 71 | open | 5 | 0 | Track 4 — Schema evolution |
| 72 | open | 1 | 10 | Track 5 — Ecosystem identity |

**Closed semantic milestones (v0.7–v2.0)** from `workflow.md` are **not present** as separate GitHub milestone objects in this snapshot — execution is grouped under Tracks.

## Drift vs docs/specs/workflow.md

| workflow.md table | On GitHub as same title? |
|-------------------|-------------------------|
| v0.7 – v1.4 Closed | Not listed as discrete milestones (history lives in closed issues/PRs). |
| v1.5 – v2.0 Open (semantic) | **Not** mirrored as `v1.5` … `v2.0` milestone titles; work is tracked under **Tracks**. |

**Resolution (applied in spec):** Keep the semantic table for **capability and release narrative**; add an explicit **GitHub Track** subsection mapping Track titles ↔ semantic intent so `bin/check-milestones` and contributors know the canonical GitHub buckets.

## Open issues by milestone (pre-cleanup)

| Milestone | Count |
|-----------|------:|
| Track 1 — Entity system & hydration | 68 |
| Track 3 — Parity & performance | 35 |
| Track 4 — Schema evolution | 5 |
| Track 2 — Bimaaji & agentic | 5 |
| Track 5 — Ecosystem identity | 1 |
| *(no milestone)* | **13** |

## Issues without milestone (pre-cleanup)

| # | Title | Proposed milestone | Rationale |
|---|--------|--------------------|-----------|
| 1338 | [Layer 0 — Foundation] PHPDoc @covers backlog | Track 1 | Foundation / entity stack. |
| 1337 | Epic: Layer 1 audit — PHPDoc @covers | Track 1 | Core data / entity area. |
| 1336 | Follow-up: Layer 2 audit — PHPDoc @covers | Track 1 | Content-type layer adjacent to core. |
| 1335 | Follow-up: Layer 3 — PHPDoc @covers | Track 1 | Same. |
| 1339 | [Layer 4 — API] CLAUDE + phpstan… bimaaji… | Track 2 | Bimaaji / L4 explicitly. |
| 1340 | [Layer 5 — AI] Sync CLAUDE… | Track 2 | AI layer. |
| 1341 | [Layer 5 — AI] PHPDoc @covers backlog | Track 2 | AI layer. |
| 1344 | [Layer 4 — API] PHPDoc @covers backlog | Track 3 | General L4 coverage / parity. |
| 1342 | [Layer 6 — Interfaces] phpstan.neon gaps | Track 3 | CI / interface parity. |
| 1343 | [Layer 6 — Interfaces] PHPDoc @covers backlog | Track 3 | Coverage parity. |
| 1276 | Rotate `SPLIT_GITHUB_TOKEN`… | Track 5 | Release / split infrastructure. |
| 1275 | ADR-004 follow-up… installed.json / `replace` | Track 1 | Package discovery / core manifest. |
| 1266 | Roll out waaseyaa/northcloud… | Track 5 | Ecosystem / consumer integration. |

## Labels (sample)

Roadmap labels `track-1-entity-system` … `track-4-schema-evolution` exist alongside `tech-debt`, `testing`, `dx`, `dependencies`, etc. No label cleanup performed in this pass.

## Duplicate heuristic

Not run to closure; titles are mostly distinct epics. Revisit if backlog grows.

## PR title hygiene (informational)

Recent merges include many titles without `#issue` (e.g. `fix:`, `docs:`, `chore(deps):`). Rule #4 in `workflow.md` remains; this audit does not rewrite history.

## Action queue (completed 2026-04-25)

1. **Done** — Assigned milestones for #1266, #1275, #1276, #1335–#1344 per table above (`gh issue edit … --milestone "…"`).
2. **Done** — Updated [docs/specs/workflow.md](../specs/workflow.md): GitHub Track subsection + Dependabot/automation note.
3. **Done** — Extended [bin/check-milestones](../../bin/check-milestones) to request up to 500 `no:milestone` hits per run (covers current open-issue scale).
4. **Done** — Noted audit cadence in [ops/observability/drift-detection.md](../../ops/observability/drift-detection.md).

## Verification after cleanup

```bash
gh issue list --repo waaseyaa/framework --state open --search "no:milestone" --limit 200
bin/check-milestones
```

**Result:** `no:milestone` search returns **0** issues; `bin/check-milestones` reports no drift (all five Tracks have at least one open issue).

### Post-cleanup milestone counts (from `bin/check-milestones`)

| Track | Open | Closed |
|-------|-----:|-------:|
| Track 1 — Entity system & hydration | 73 | 4 |
| Track 2 — Bimaaji & agentic | 8 | 19 |
| Track 3 — Parity & performance | 38 | 6 |
| Track 4 — Schema evolution | 5 | 0 |
| Track 5 — Ecosystem identity | 3 | 10 |
