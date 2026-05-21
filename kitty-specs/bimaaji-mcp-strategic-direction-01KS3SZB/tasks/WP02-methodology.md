---
work_package_id: WP02
title: Phase 2 — Methodology
dependencies:
- WP01
requirement_refs:
- FR-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T005
- T006
- T007
history:
- date: '2026-05-20T23:57:38Z'
  agent: tasks-materializer
  action: created
authoritative_surface: kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/
execution_mode: planning_artifact
owned_files:
- kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/methodology.md
tags: []
---

# WP02 — Phase 2: Methodology

**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Branch strategy**: `main` → `main` (commit directly, no worktree)
**Effort estimate**: ~20 minutes
**Execution mode**: `planning_artifact` — no production code

## Objective

Produce `methodology.md` — a map of what evidence to gather and where to find it. This document drives WP03 (Gather) so the agent executing that phase does not have to re-discover sources. The methodology should be specific enough that someone unfamiliar with the codebase could follow it.

## Context

WP01 established the decision space (options 1–3) and criteria. WP02 asks: for each option × criterion pair, what concrete evidence would confirm or disconfirm that pairing, and where does that evidence live? The output is a research plan, not the research itself.

## Branch Strategy

- Planning/base branch: `main`
- Final merge target: `main`
- Execution: commit directly to `main`. No feature branch, no worktree.
- Dependencies resolved: WP01 must be merged before WP02 begins.
- Implementation command: `spec-kitty agent action implement WP02 --agent <name>`

---

## Subtask T005 — Enumerate evidence needed per option

**Purpose**: For each option, identify what a rational decision-maker needs to see before accepting or rejecting it.

**Steps**:

1. For **Option 1 (PHP-only, close)**:
   - Evidence needed: (a) absence of consumer signal requesting bimaaji-via-MCP, (b) confirmation that the existing HTTP/API path is sufficient for known agent use cases, (c) confirmation that re-opening is cheap if signal arrives later.

2. For **Option 2 (extend packages/mcp/)**:
   - Evidence needed: (a) packages/mcp/ already supports PHP-tool registration (or the gap is small), (b) bimaaji's public PHP surface has operations worth exposing as MCP tools, (c) no consumer requires the Node-level protocol that PHP cannot serve.

3. For **Option 3 (restore Node sidecar)**:
   - Evidence needed: (a) the root cause of the previous failure (exit 254, missing server.js) is diagnosed and fixable, (b) a consumer has explicitly requested Node-level MCP, (c) the maintenance cost is acceptable relative to the value.

4. Across all options:
   - **Consumer signal**: any Minoo issue, PR, or code comment requesting bimaaji-via-MCP
   - **Framework readiness**: what `packages/mcp/src/` exposes today for PHP-tool registration
   - **Maintenance cost history**: git log on `packages/bimaaji/mcp/`, issue #1387, #1463 body

**Deliverable**: A table of evidence items (min 4 distinct items), each linked to at least one option.

**Validation**:
- [ ] Each option has at least 2 evidence items assigned to it
- [ ] All three NFR-003 categories are represented: (a) framework code, (b) consumer signal, (c) maintenance cost

---

## Subtask T006 — Map evidence to source locations

**Purpose**: Give WP03 a precise gather plan so it does not waste time hunting sources.

**Steps**:

1. For **bimaaji public PHP surface** (supports Option 2 evaluation):
   - Source: `packages/bimaaji/src/` — enumerate public classes/interfaces
   - Method: `find packages/bimaaji/src -name "*.php" | head -40` then read key files

2. For **packages/mcp/ capability snapshot** (supports Option 2 evaluation):
   - Source: `packages/mcp/src/` — look for ToolRegistrar, ToolInterface, or similar
   - Source: `docs/specs/mcp-endpoint.md` — framework's current MCP surface description
   - Method: `find packages/mcp/src -name "*.php"` + read the spec

3. For **consumer signal — Minoo** (supports Option 1 rejection or confirmation):
   - Source: `/home/jones/dev/waaseyaa.org/` if that directory is Minoo's repo, otherwise `gh issue list` on the Minoo GitHub org
   - Method: `ls /home/jones/dev/waaseyaa.org/ 2>/dev/null` to check if accessible; if not, use `gh issue list --repo <minoo-repo>` or note "no consumer repo locally"
   - Also search: any local Minoo composer.json for bimaaji dependencies

4. For **Node sidecar cost history** (supports Option 3 evaluation):
   - Source: `git log --all --oneline -- packages/bimaaji/mcp/ 2>/dev/null`
   - Source: `gh issue view 1387`, `gh issue view 1463`
   - Source: any git notes or commit messages mentioning "exit 254" or "bimaaji-mcp-install"

5. Record the methodology as a table:

   | Evidence item | Option(s) | Source location | Gather method |
   |---------------|-----------|-----------------|---------------|
   | bimaaji public PHP surface | 2 | `packages/bimaaji/src/` | file listing + read |
   | packages/mcp/ tool registration capability | 2 | `packages/mcp/src/`, `docs/specs/mcp-endpoint.md` | file listing + read spec |
   | consumer signal (Minoo) | 1, 2, 3 | `/home/jones/dev/waaseyaa.org/` or gh CLI | directory check / issue search |
   | Node sidecar failure diagnosis | 3 | git log, #1387, #1463 | git log, gh issue view |

**Validation**:
- [ ] Every evidence item has a concrete source location (path or gh command)
- [ ] Every evidence item has a gather method
- [ ] Table covers all three NFR-003 evidence categories

---

## Subtask T007 — Write `methodology.md`

**Purpose**: Produce the methodology document.

**Steps**:

1. Write `kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/methodology.md` with these sections:

   ```
   # Research Methodology — Bimaaji MCP Strategic Direction

   **Date**: YYYY-MM-DD
   **Mission**: bimaaji-mcp-strategic-direction-01KS3SZB
   **Based on**: decision-frame.md

   ## Evidence Requirements

   <paragraph: what this phase determines>

   ## Evidence Map

   | Evidence item | Option(s) | Source | Gather method | Research note |
   |---------------|-----------|--------|---------------|---------------|
   | ... | ... | ... | ... | research/bimaaji-surface.md |
   | ... | ... | ... | ... | research/mcp-capability.md |
   | ... | ... | ... | ... | research/consumer-signal.md |
   | ... | ... | ... | ... | research/sidecar-cost.md |

   ## NFR-003 Coverage

   - (a) Framework code: [evidence item name] → research/mcp-capability.md, research/bimaaji-surface.md
   - (b) Consumer signal: [evidence item name] → research/consumer-signal.md
   - (c) Maintenance cost: [evidence item name] → research/sidecar-cost.md
   ```

2. Keep to ≤ 1 page.
3. Commit:
   ```bash
   git add kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/methodology.md
   git commit -m "tasks(M-G): WP02 methodology.md — evidence map for gather phase"
   ```

**Validation**:
- [ ] File exists at correct path
- [ ] Evidence map table has ≥4 rows
- [ ] Each row maps to one of the four `research/*.md` output files
- [ ] NFR-003 coverage section present
- [ ] Committed to main

---

## Definition of Done

- [ ] `methodology.md` exists in `kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/`
- [ ] Evidence map covers ≥4 distinct items
- [ ] Each item maps to a source location and a `research/*.md` target file
- [ ] NFR-003 three-category coverage is explicitly mapped
- [ ] Committed to `main`

## Risks

- **Low**: Sources are all local or accessible via gh CLI. Only risk is Minoo not being locally accessible — methodology should note "if repo not accessible locally, use gh CLI" as a fallback.

## Reviewer Guidance

Reviewer should verify: (1) methodology.md gives WP03 enough specificity to execute without further research design, (2) all four `research/*.md` targets are mapped, (3) NFR-003 coverage is complete.
