# Implementation Plan: Admin SPA Modernization Audit

**Branch**: `main` | **Date**: 2026-05-10 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `kitty-specs/admin-spa-modernization-audit-01KRA3RV/spec.md`
**Mission ID**: `01KRA3RV5GPMP178F2VMEX1TW1` (mid8: `01KRA3RV`)
**Mission type**: research
**Branch contract**: Current branch: `main`. Planning/base branch: `main`. Merge target: `main`. Branch matches target: yes.

## Summary

Produce `docs/audits/admin-spa-modernization-2026-05-10.md` — a four-axis audit (framework alignment drift across the full v1.x lifetime, feature coverage gaps across L2–L6 subsystems, dependency/tooling staleness, and package envelope health) plus milestone-tagged GitHub follow-up issues. The audit leads with a Top 5 follow-up missions section that turns the perception of `packages/admin/` being "severely out of date" into a finite, sized, citable backlog. No code under `packages/admin/` is modified, and no backend code is modified. Output is a structured markdown document plus tracking issues.

## Technical Context

**Language/Version**: N/A — research mission. Output is markdown.
**Primary Dependencies**: `git`, `gh` CLI (authenticated), `bin/check-milestones`, read access to `packages/admin/` and named backend packages, `tools/drift-detector.sh`.
**Storage**: Filesystem only. Audit doc lands at `docs/audits/admin-spa-modernization-2026-05-10.md`. GitHub Issues store tracking records.
**Testing**: N/A — no code under test. Validation is structural (every required section present, every entry classified, every entry has citation + size, every entry has a backlinked GitHub issue with a Track milestone). Validation is encoded as the spec quality checklist + plan-defined acceptance gates per WP.
**Target Platform**: Repository-local execution only. GitHub.com for issue creation.
**Project Type**: single (audit doc + issues; no application code).
**Performance Goals**: N/A — wall-clock time is unbounded but the audit itself is read in ≤10 minutes (SC-001 / NFR-006).
**Constraints**: No file in `packages/admin/` may be modified (C-001). No backend source file may be modified (C-002). Audit must cite full v1.x lifetime (FR-004). 100% of orchestration-table subsystems must appear in Section 2 (NFR-002).
**Scale/Scope**: One markdown document (~10–20 pages estimated), 20–60 GitHub issues, four axes × N entries each. Citation universe ≈ all commits on `main` affecting `packages/{entity,entity-storage,field,api,access,auth,routing,user,config,telescope}` and `packages/foundation/src/Http/`.

## Charter Check

Charter context: compact mode, software-dev-default template, paradigm DDD, languages PHP + TypeScript. Active directives: DIR-001, DIR-002, DIR-003. No mission-blocking constraint surfaced for an audit-only research mission.

| Gate | Status | Notes |
|------|--------|-------|
| Branch strategy | Pass | main → main, matches target. |
| Bulk-edit detection | Pass | Not a bulk edit (new artifact, no cross-file renames). `change_mode` left at default. |
| Out-of-scope guardrails | Pass | C-001 / C-002 forbid `packages/admin/` and backend modifications. Enforced per WP acceptance. |
| Audit completeness | Pass | NFR-001/NFR-002/NFR-003 set measurable floors. Each WP carries an explicit completeness gate. |
| Traceability | Pass | NFR-005 requires bidirectional audit↔issue linking. |

Re-check after Phase 1 (below): no new gate violations introduced by the research-mission artifact set.

## Phase 0: Outline & Research

This is a research mission, so Phase 0 is substantive. The methodology, tool selection, source corpus, and classification rubric are captured in `research.md` rather than the plan body. Key decisions made in Phase 0 (mirrored here for reviewers):

1. **Drift corpus selection**: Use `git log --first-parent main -- <package paths>` rather than the full graph. Rationale: missions land via squash-merge to main; first-parent traversal aligns with the canonical history without inflating counts from intermediate WP commits.
2. **Drift classification rubric**: `{broken, degraded, unsurfaced, no-op}` is exhaustive for backend → SPA impact. Definitions pinned in `research.md` so all entries use the same axis.
3. **Coverage walk source of truth**: the CLAUDE.md orchestration table is authoritative (per Assumptions in spec). Packages not in the table are flagged `orchestration-table-orphan`.
4. **Sizing rubric**: XS ≤ 0.5 day, S ≤ 2 days, M ≤ 1 week, L > 1 week (decomposition expected). Pinned in `research.md` and reproduced at the top of the audit doc.
5. **Context-budget strategy**: heavy git archaeology runs through `mcp__plugin_context-mode_context-mode__ctx_batch_execute` / `ctx_execute_file` so raw commit logs stay in the sandbox; only the classified, summarized entries enter the audit doc.
6. **GitHub issue lifecycle**: issues are created in the WP that owns each axis (drift issues in WP01, coverage in WP02, tooling+envelope+Top 5 in WP03). Each issue body links its audit-doc anchor; each audit entry is amended with its issue number once filed.
7. **Track milestone defaults**: cross-cutting gaps default to Track 1 (Entity system & hydration); admin-only or agentic gaps go to Track 2 (Bimaaji & agentic). New Tracks may be proposed in WP03 but not created in this mission.

**Output**: `research.md` — methodology, rubrics, citation conventions, and tool selection notes.

## Phase 1: Design & Contracts

**Data model**: N/A — this mission produces a markdown document and GitHub issues, not application data. Key audit entities (drift entry, coverage entry, tooling finding, envelope finding, follow-up mission, tracking issue) are documented in the spec under "Key Entities" and that document is the canonical schema.

**Contracts**: N/A — no API surface produced by this mission.

**Quickstart**: A short `quickstart.md` is generated below for any agent picking up the mission: how to run the corpus query, how to file issues with Track milestones, how to keep audit↔issue links in sync.

**Re-check Charter**: No design choice in Phase 1 introduces a gate violation. The N/A entries for data-model/contracts are appropriate for a research mission per the spec-kitty research mission phase contract (question → methodology → gather → analyze → synthesize → publish).

## Project Structure

### Documentation (this feature)

```
kitty-specs/admin-spa-modernization-audit-01KRA3RV/
├── spec.md              # Feature spec (already created)
├── plan.md              # This file
├── research.md          # Phase 0: methodology, rubrics, corpus selection, tool plan
├── quickstart.md        # Phase 1: agent quickstart for executing the audit
├── checklists/
│   └── requirements.md  # Spec quality checklist
├── tasks/               # WP files (created by /spec-kitty.tasks; not by this plan)
├── meta.json
└── status.events.jsonl
```

No `data-model.md` or `contracts/` directory — N/A for a markdown-output research mission.

### Source Code (repository root)

This mission produces no application code. The single non-spec output is:

```
docs/audits/
└── admin-spa-modernization-2026-05-10.md   # Final audit document (produced by WP03)
```

Read-only inputs referenced during execution (no modification):

```
packages/admin/                              # Audit subject (read-only)
packages/{entity,entity-storage,field,api,
         access,auth,routing,user,config,
         telescope}/                         # Drift corpus sources (read-only)
packages/foundation/src/Http/                # Drift corpus source (read-only)
CLAUDE.md                                    # Orchestration-table authority (read-only)
docs/specs/admin-spa.md                      # Current admin SPA spec (read-only)
bin/check-milestones                         # Milestone validation tool
```

**Structure Decision**: Single-deliverable research mission. All output is the audit document and the set of GitHub issues it spawns. The mission worktree never touches `packages/`.

## Complexity Tracking

No charter gate violations. Complexity tracking is empty by design.

## Acceptance & Validation

The audit's correctness is verifiable structurally, not via tests:

- `grep -c '^| .* | .* | .* | .* | .* |' docs/audits/admin-spa-modernization-2026-05-10.md` — every row in the four axis tables has at least 5 columns (status, citation, files, classification, size).
- Every entry must contain a commit hash (`[a-f0-9]{7,40}`), an issue/PR reference (`#\d+`), or an explicit `no-op` rationale.
- `gh issue list --milestone "Track 1" --milestone "Track 2"` should grow by the number of entries spawned during the mission.
- Final commit grep: `grep -E 'modifies packages/admin/' git diff` must return empty for the mission's WP commits — C-001 enforcement.
- Top 5 section must precede the four axis sections in the rendered doc.

## Next command

`/spec-kitty.tasks` — generate the work-package outline and materialize WP01/WP02/WP03 files. Do NOT run that command from this plan; user invokes it explicitly.
