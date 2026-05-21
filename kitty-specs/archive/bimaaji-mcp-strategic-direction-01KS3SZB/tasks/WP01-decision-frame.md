---
work_package_id: WP01
title: Phase 1 — Decision Frame
dependencies: []
requirement_refs:
- FR-001
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T001
- T002
- T003
- T004
<<<<<<< HEAD
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "712047"
=======
>>>>>>> kitty/mission-m006-translation-hardening-01KS3RY9-lane-a
history:
- date: '2026-05-20T23:57:38Z'
  agent: tasks-materializer
  action: created
authoritative_surface: kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/
execution_mode: planning_artifact
owned_files:
- kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision-frame.md
tags: []
---

# WP01 — Phase 1: Decision Frame

**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Branch strategy**: `main` → `main` (commit directly, no worktree)
**Effort estimate**: ~30 minutes
**Execution mode**: `planning_artifact` — no production code

## Objective

Confirm the complete decision space for bimaaji's MCP surface, establish the decision criteria and weights, identify the decision-maker, and write `decision-frame.md`. This document is the foundation for all subsequent WPs — without a clear frame, the gather phase (WP03) will collect the wrong evidence.

## Context

`packages/bimaaji/` is currently PHP-only. A previous Node-based MCP sidecar (`packages/bimaaji/mcp/server.js`) was removed because it never reached consumers reliably (`composer bimaaji-mcp-install` exited 254 in Minoo; `vendor/waaseyaa/bimaaji/mcp/server.js` was absent at runtime). The framework has a separate MCP endpoint package (`packages/mcp/`). Issue #1463 left the question of bimaaji's MCP surface as "TBD by maintainer".

The spec names three options:

1. **PHP-only, close #1463 with conviction.** No MCP server. Agents call bimaaji via the standard HTTP API or via `packages/mcp/` registering bimaaji tools if/when that capability exists.
2. **Extend `packages/mcp/`.** Add bimaaji-specific MCP tools to the existing PHP-side MCP host. No Node; tools call bimaaji PHP API directly.
3. **Restore a Node-based MCP sidecar.** Re-add `packages/bimaaji/mcp/server.js`, fix the install path, document it. Highest cost; previously failed.

## Branch Strategy

- Planning/base branch: `main`
- Final merge target: `main`
- Execution: commit directly to `main`. No feature branch, no worktree.
- Implementation command: `spec-kitty agent action implement WP01 --agent <name>`

---

## Subtask T001 — Confirm the decision space is complete

**Purpose**: Verify that options 1, 2, 3 from the spec are exhaustive. A missed option surfaces as a surprise later.

**Steps**:

1. Read `kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/spec.md` §"Three options on the table" (lines 29–34).
2. Search GitHub issues and existing docs for any 4th option that may have been discussed but not captured in the spec:
   ```bash
   gh issue list --label bimaaji --state all --limit 20 2>/dev/null | head -30
   gh issue view 1463 2>/dev/null
   gh issue view 1387 2>/dev/null
   ```
3. Check if there is a "do nothing + document" option that differs from Option 1 (Option 1 is "close with conviction", meaning active decision not to do MCP; "do nothing" could mean "defer indefinitely without closing"). If distinct, add as Option 4.
4. Check if an "HTTP REST / JSON-API only (no MCP at all)" positioning is distinct from Option 1 in practice, or if Option 1 already covers it.

**Deliverable**: A list of confirmed options (min 3, max 4) with one-line summaries.

**Validation**:
- [ ] Each option has a distinct implementation path
- [ ] No two options overlap completely
- [ ] The option list is complete enough to make a decision

---

## Subtask T002 — Identify any unlisted options

**Purpose**: A quick scan for options the spec might have missed. Should take ≤ 10 minutes — do not over-invest.

**Steps**:

1. Consider: is there an option 4 such as "publish bimaaji as a standalone MCP server on npm, not tied to the Composer package"? Assess whether this is distinct from Option 3 or a variant of it.
2. Consider: is there an option for "document how to register bimaaji tools in packages/mcp/ without shipping the registration code"? This would be a documentation-only variant of Option 2.
3. For each candidate 4th option: Is it meaningfully different from the three already listed? If yes, add it. If it's a sub-variant, note it as a variant under the parent option.

**Deliverable**: Either "options 1–3 are exhaustive" or "option 4 added: [description]".

**Validation**:
- [ ] You have actively looked for alternatives, not just accepted the spec's framing
- [ ] Any new option is clearly distinct, not redundant

---

## Subtask T003 — Identify decision criteria, weights, and decision-maker

**Purpose**: Establish the evaluation framework. The analysis (WP04) and decision (WP05) both depend on having agreed criteria with weights.

**Steps**:

1. The spec (plan.md §"Decision Criteria") pre-loads these criteria:

   | Criterion | Weight | Notes |
   |-----------|--------|-------|
   | Consumer signal | High | Real downstream ask? No signal = lean Option 1 |
   | Framework readiness | High | Does `packages/mcp/` support PHP-tool registration today? |
   | Maintenance cost | Medium | Node sidecar history |
   | Implementation complexity | Medium | Blast radius of each option |
   | Reversibility | Low | Can we change cheaply if signal arrives later? |

2. Confirm these criteria are appropriate. Consider adding:
   - **Security surface** (Node sidecar adds a new process + attack surface — relevant for Option 3)
   - **Consumer install experience** (for Option 3, the install path previously failed — how is this weighted?)
3. Assign the decision-maker: per the spec, "the maintainer is the sole decision-maker". Record this explicitly.
4. Note: "Closing #1463 as not-planned (Option 1) is not failure." This should appear in the frame document.

**Deliverable**: Finalized criteria table (≥4 criteria with weights) + decision-maker statement.

**Validation**:
- [ ] Each criterion has a weight (High/Medium/Low)
- [ ] Decision-maker is named
- [ ] "Option 1 is not failure" note is present

---

## Subtask T004 — Write `decision-frame.md`

**Purpose**: Produce the framing document that all subsequent WPs reference.

**Steps**:

1. Write `kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision-frame.md` with these sections:

   ```
   # Bimaaji MCP — Decision Frame

   **Date**: YYYY-MM-DD
   **Mission**: bimaaji-mcp-strategic-direction-01KS3SZB
   **Decision-maker**: <name>

   ## Decision Space

   <one table or list: option ID, name, one-line summary>

   ## Decision Criteria

   <criteria table with weights>

   ## Framing Notes

   - Option 1 (PHP-only, close) is a valid and complete outcome.
   - The decision is point-in-time; new consumer signal can re-open later.
   - No production code is produced by this mission.
   ```

2. Keep it to ≤ 1 page. This is a framing doc, not an analysis.
3. Commit the file:
   ```bash
   git add kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision-frame.md
   git commit -m "tasks(M-G): WP01 decision-frame.md — options and criteria"
   ```

**Validation**:
- [ ] File exists at the correct path
- [ ] ≤ 1 page
- [ ] Options listed (≥3)
- [ ] Criteria table present with weights
- [ ] Decision-maker named
- [ ] "Option 1 is not failure" note present
- [ ] Committed to main

---

## Definition of Done

- [ ] `decision-frame.md` exists in `kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/`
- [ ] File is committed to `main`
- [ ] Decision space is confirmed (≥3 options)
- [ ] Criteria table has ≥4 entries with weights
- [ ] Decision-maker is named explicitly

## Risks

- **Low**: Options are well-defined in the spec. The main risk is a 4th option being overlooked — mitigated by the gh issue search in T001.
- **Low**: Over-investing in the frame. Cap at 30 minutes; if criteria are debatable, pick sensible defaults and note them as "subject to maintainer override".

## Reviewer Guidance

Reviewer should verify: (1) options match the spec exactly unless a 4th was explicitly justified, (2) criteria weights are reasonable for a framework-level MCP decision, (3) decision-maker is the maintainer (not the implementing agent).
<<<<<<< HEAD

## Activity Log

- 2026-05-21T00:26:37Z – claude:sonnet:researcher:implementer – shell_pid=707975 – Started implementation via action command
- 2026-05-21T00:27:29Z – claude:sonnet:researcher:implementer – shell_pid=707975 – decision-frame.md committed; 3 options confirmed exhaustive; 5 criteria defined with weights
- 2026-05-21T00:28:32Z – claude:opus-4-7:reviewer:reviewer – shell_pid=712047 – Started review via action command
- 2026-05-21T00:37:38Z – claude:opus-4-7:reviewer:reviewer – shell_pid=712047 – Research approved: 3-option frame with explicit dismissal of 3 alternatives; 5 measurable independent criteria; decision-maker + 4h bound confirmed; well under 1 page.
=======
>>>>>>> kitty/mission-m006-translation-hardening-01KS3RY9-lane-a
