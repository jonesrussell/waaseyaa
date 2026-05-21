---
work_package_id: WP05
title: Phase 5 — Decide
dependencies:
- WP04
requirement_refs:
- FR-001
- FR-002
- NFR-002
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T016
- T017
- T018
history:
- date: '2026-05-20T23:57:38Z'
  agent: tasks-materializer
  action: created
authoritative_surface: kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/
execution_mode: planning_artifact
owned_files:
- kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision.md
tags: []
---

# WP05 — Phase 5: Decide

**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Branch strategy**: `main` → `main` (commit directly, no worktree)
**Effort estimate**: ~30 minutes
**Execution mode**: `planning_artifact` — no production code

## Objective

Make the decision. Write `decision.md` — the primary mission deliverable. It names one of the three options with evidence-backed rationale, stays within 2 pages, and satisfies all NFR-003 citation requirements.

**Note**: If the analysis in WP04 is inconclusive (two options are equally supported), the document should note the tie and state which criterion the maintainer should use to break it. The agent's role is to surface the evidence, not to mandate an outcome.

## Context

`decision.md` is the artifact that:
- Closes #1463 (its text will be used as the close comment in WP06)
- Drives any M-G.1 mission scope (if Option 2 or 3)
- Persists as a permanent record in the mission directory
- Gets cited by the `docs/specs/mcp-endpoint.md` edit in WP06

It must be concise (≤ 2 pages per NFR-002) and cite specific evidence (per FR-002 and NFR-003).

## Branch Strategy

- Planning/base branch: `main`
- Final merge target: `main`
- Execution: commit directly to `main`. No feature branch, no worktree.
- Dependencies resolved: WP04 (`analysis.md`) must be complete.
- Implementation command: `spec-kitty agent action implement WP05 --agent <name>`

---

## Subtask T016 — Select the winning option

**Purpose**: Apply the weighted criteria from decision-frame.md to the analysis in analysis.md and select one option.

**Steps**:

1. Read `decision-frame.md` — criteria table with weights.
2. Read `analysis.md` — pros/cons table per option.
3. Apply the weights: High-weight criteria (Consumer signal, Framework readiness) are more decisive than Medium/Low criteria.
4. Determine the winner:

   **Decision logic**:
   - If **consumer signal = None** AND **framework readiness for Option 2 = requires predecessor work** → Option 1 is strongly supported.
   - If **consumer signal = Active/Latent** AND **framework readiness for Option 2 = yes** → Option 2 is supported.
   - If **consumer signal = Active** specifically requesting Node MCP AND root cause of Option 3 failure is diagnosed and fixable → Option 3 may be considered.
   - Option 3 with no active consumer signal and undiagnosed root cause = not supported by evidence.

5. State the selection clearly: "The evidence supports **Option N: [name]**."

6. If two options are genuinely tied: State the tie and identify the tiebreaker question: "If the maintainer's priority is [criterion X], choose Option N. If [criterion Y], choose Option M."

**Validation**:
- [ ] One option is named as the recommended choice
- [ ] The selection is grounded in the analysis table (not invented reasoning)

---

## Subtask T017 — Verify NFR-003 evidence citations

**Purpose**: Before writing decision.md, confirm that the rationale will cite evidence from all three required categories.

**Steps**:

Per NFR-003, the decision must cite evidence from:

**(a) Framework-internal code state**:
- Source: `research/mcp-capability.md` and/or `research/bimaaji-surface.md`
- Example citation: "As of YYYY-MM-DD, `packages/mcp/` does not expose a PHP-tool registration API (see mcp-capability.md)."

**(b) Consumer signal** (positive, negative, or "no signal at this time"):
- Source: `research/consumer-signal.md`
- Example citation: "No consumer signal for bimaaji-via-MCP was found in Minoo's issue tracker as of YYYY-MM-DD (see consumer-signal.md). No local Minoo repo was accessible; gh CLI search returned 0 results."

**(c) Maintenance-cost history**:
- Source: `research/sidecar-cost.md`
- Example citation: "The previous Node sidecar attempt (packages/bimaaji/mcp/) was removed after [N months] due to [failure mode] — see sidecar-cost.md."

Verify that the rationale for the chosen option includes all three citation types. If any category has no evidence (e.g. mcp-capability not determinable), note that explicitly: "Framework-internal code state: inconclusive — packages/mcp/ is a stub without public documentation."

**Validation**:
- [ ] Citation for (a) framework code is identified
- [ ] Citation for (b) consumer signal is identified (even if "no signal")
- [ ] Citation for (c) maintenance cost is identified

---

## Subtask T018 — Write `decision.md`

**Purpose**: Produce the primary mission deliverable — a concise, evidence-backed decision document.

**Steps**:

1. Write `kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision.md` with this structure (≤ 2 pages):

   ```markdown
   # Bimaaji MCP — Decision

   **Date**: YYYY-MM-DD
   **Mission**: bimaaji-mcp-strategic-direction-01KS3SZB
   **Decision-maker**: Maintainer (Russell Jones)
   **Decided by agent**: [agent name], surfacing evidence — final ratification by maintainer

   ## Decision: Option N — [Option Name]

   <One paragraph naming the option and summarizing why it was chosen.>

   ## Evidence Summary

   | Category | Evidence | Source |
   |----------|----------|--------|
   | (a) Framework code | <finding from mcp-capability.md or bimaaji-surface.md> | research/mcp-capability.md |
   | (b) Consumer signal | <finding from consumer-signal.md> | research/consumer-signal.md |
   | (c) Maintenance cost | <finding from sidecar-cost.md> | research/sidecar-cost.md |

   ## Rationale

   <2-4 sentences explaining why the chosen option best fits the decision criteria,
   citing the evidence above. Be specific — use dates and findings, not opinions.>

   ## Options Considered (summary)

   | Option | Verdict | Key reason |
   |--------|---------|------------|
   | 1 — PHP-only, close | [Chosen / Rejected] | <1 sentence> |
   | 2 — Extend packages/mcp/ | [Chosen / Rejected] | <1 sentence> |
   | 3 — Restore Node sidecar | [Chosen / Rejected] | <1 sentence> |

   ## Follow-up

   <!-- If Option 1: -->
   None. Issue #1463 closes as `not-planned`. No M-G.1 mission required.

   <!-- If Option 2: -->
   M-G.1 mission required. Scope: Add bimaaji graph operation tools to `packages/mcp/`.
   Tracked by: [GitHub issue # — to be filed in WP06].

   <!-- If Option 3: -->
   M-G.1 mission required. Scope: Restore `packages/bimaaji/mcp/server.js`, fix [root cause].
   Tracked by: [GitHub issue # — to be filed in WP06].

   ## Notes

   - This decision is point-in-time (YYYY-MM-DD). New consumer signal may re-open the question.
   - Option 1 chosen as "not-planned" is a valid, complete mission outcome — not failure.
   ```

2. Read the completed draft and verify:
   - Word count: ≤ 2 pages (roughly ≤ 800 words)
   - All three NFR-003 evidence categories are cited in the Evidence Summary table
   - The "Follow-up" section correctly states None / M-G.1 based on the chosen option
   - Dates are real (from research notes, not invented)

3. Commit:
   ```bash
   git add kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision.md
   git commit -m "tasks(M-G): WP05 decision.md — [Option N: brief option name]"
   ```
   (Replace `[Option N: brief option name]` with the actual choice.)

**Validation**:
- [ ] File exists at the correct path
- [ ] Decision names one option explicitly (Option 1, 2, or 3)
- [ ] Evidence Summary table has 3 rows covering categories (a), (b), (c)
- [ ] Rationale cites specific findings from research notes (not generic statements)
- [ ] "Follow-up" section is present and correct for the chosen option
- [ ] Document is ≤ 2 pages
- [ ] Committed to main

---

## Definition of Done

- [ ] `decision.md` exists in the mission directory
- [ ] Names one of the three options explicitly
- [ ] Evidence Summary table is present with all three NFR-003 categories cited
- [ ] Document is ≤ 2 pages
- [ ] All evidence citations reference specific research note findings (not generic)
- [ ] Committed to `main`

## Risks

- **Low**: If the analysis leaves two options genuinely tied, the document should state the tie and offer the tiebreaker criterion. The mission does not block on the maintainer's ratification — the agent's job is to surface the evidence clearly.
- **Low**: NFR-002 (≤ 2 pages) is occasionally violated by adding too much rationale. Use the table-heavy structure above to stay concise.

## Reviewer Guidance

Reviewer (the maintainer) should verify: (1) the named option matches their own reading of the evidence, (2) each evidence citation references a specific finding (not "research shows that..."), (3) the follow-up section is correctly populated for the chosen option, (4) no production code is referenced or implied.
