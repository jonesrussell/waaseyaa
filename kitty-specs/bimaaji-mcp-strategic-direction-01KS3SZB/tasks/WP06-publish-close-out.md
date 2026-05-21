---
work_package_id: WP06
title: Phase 6 — Publish + Close-out
dependencies:
- WP05
requirement_refs:
- FR-003
- FR-004
- FR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T019
- T020
- T021
history:
- date: '2026-05-20T23:57:38Z'
  agent: tasks-materializer
  action: created
authoritative_surface: docs/specs/
execution_mode: planning_artifact
owned_files:
- docs/specs/mcp-endpoint.md
tags: []
---

# WP06 — Phase 6: Publish + Close-out

**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Branch strategy**: `main` → `main` (commit directly, no worktree)
**Effort estimate**: ~30 minutes
**Execution mode**: `planning_artifact` — the only non-mission-directory file touched is `docs/specs/mcp-endpoint.md`

## Objective

Land the decision publicly: update the framework spec so future contributors can find the decision without reading the mission directory, file M-G.1 if the decision was Option 2 or 3, and close #1463 with the decision text as the close comment.

**This WP has three distinct outcomes, each conditional on the option chosen in WP05:**

| Chosen option | T019 (docs edit) | T020 (M-G.1) | T021 (#1463 close) |
|--------------|------------------|--------------|-------------------|
| Option 1 | Always required | Skip | Close as `not-planned` |
| Option 2 | Always required | File M-G.1 | Close as `completed` (after M-G.1 filed) |
| Option 3 | Always required | File M-G.1 | Close as `completed` (after M-G.1 filed) |

## Context

The final commit of this WP is the mission close-out record. Per C-002, #1463 must close via a close comment, not a `Closes #N` footer. Per C-003, `decision.md` must remain in the mission directory permanently.

## Branch Strategy

- Planning/base branch: `main`
- Final merge target: `main`
- Execution: commit directly to `main`. No feature branch, no worktree.
- Dependencies resolved: WP05 (`decision.md`) must be complete.
- Implementation command: `spec-kitty agent action implement WP06 --agent <name>`

---

## Subtask T019 — Add "Bimaaji MCP positioning" to `docs/specs/mcp-endpoint.md`

**Purpose**: Make the decision discoverable to future contributors without requiring them to read the mission directory (SC-003, FR-004).

**Steps**:

1. Read the full current content of `docs/specs/mcp-endpoint.md`:
   ```bash
   # Read the file before editing — required for Edit tool
   ```

2. Find an appropriate location for the new section. Good candidates:
   - Near the top under a "Related decisions" or "History" heading if one exists
   - At the end of the file in a "Decisions" section

3. Add a section with this structure (3-5 sentences + citation, not a long essay):

   ```markdown
   ## Bimaaji MCP Positioning

   **Decision**: [Option N — Option Name] (YYYY-MM-DD, mission `bimaaji-mcp-strategic-direction-01KS3SZB`)

   [One sentence summarizing the decision.]
   [One sentence explaining the rationale — cite the key evidence type.]
   [One sentence for follow-up if applicable: "Option 2/3 is tracked by M-G.1 / GitHub issue #N." or "Issue #1463 was closed as not-planned."]

   If consumer signal for bimaaji-via-MCP emerges after this date, a new strategic mission should re-open the question.
   ```

4. Do **not** add this section to `packages/bimaaji/README.md` — per the plan, `docs/specs/mcp-endpoint.md` was selected as the canonical location because decision context belongs with the framework's MCP surface spec.

5. Commit the edit:
   ```bash
   git add docs/specs/mcp-endpoint.md
   git commit -m "tasks(M-G): WP06 mcp-endpoint.md — bimaaji MCP positioning section (FR-004)"
   ```

**Validation**:
- [ ] Section "Bimaaji MCP Positioning" is present in `docs/specs/mcp-endpoint.md`
- [ ] Section cites the mission slug and date
- [ ] Section names the chosen option
- [ ] Section is ≤ 5 sentences
- [ ] File committed to main

---

## Subtask T020 — File M-G.1 follow-up mission (Option 2 or 3 only)

**Purpose**: If the decision is not Option 1, create the follow-up implementation mission and a GitHub tracking issue (FR-003, SC-005).

**Steps (Option 2 — extend packages/mcp/)**:

1. File the GitHub issue first:
   ```bash
   gh issue create \
     --title "M-G.1: Add bimaaji graph operation tools to packages/mcp/" \
     --body "Follow-up from bimaaji MCP strategic direction (mission bimaaji-mcp-strategic-direction-01KS3SZB). Decision: Option 2 — extend packages/mcp/ with bimaaji PHP tools. See kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision.md for scope." \
     --label "enhancement"
   ```
   Record the resulting issue number.

2. File the spec-kitty mission:
   ```bash
   spec-kitty specify --title "bimaaji-mcp-extend-packages-mcp" \
     --description "Implement Option 2 from bimaaji MCP strategic direction: add bimaaji graph operation tools to packages/mcp/. Scope defined in decision.md." \
     --parent-issue <issue number from step 1>
   ```
   Or use the interactive `spec-kitty specify` flow if the CLI does not support inline args.

**Steps (Option 3 — restore Node sidecar)**:

1. File the GitHub issue first:
   ```bash
   gh issue create \
     --title "M-G.1: Restore packages/bimaaji/mcp/server.js Node sidecar" \
     --body "Follow-up from bimaaji MCP strategic direction (mission bimaaji-mcp-strategic-direction-01KS3SZB). Decision: Option 3 — restore Node sidecar. Prior failure diagnosis: [quote from sidecar-cost.md]. See kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision.md for scope." \
     --label "enhancement"
   ```
   Record the resulting issue number.

2. File the spec-kitty mission similarly.

**Steps (Option 1 — no M-G.1 needed)**:

Skip this subtask entirely. Record "T020: skipped — Option 1 chosen, no M-G.1 required" in the commit message or a brief close-out note.

**Validation**:
- [ ] If Option 2 or 3: M-G.1 GitHub issue created with issue number recorded
- [ ] If Option 2 or 3: spec-kitty mission directory exists under `kitty-specs/`
- [ ] If Option 1: explicitly noted as skipped

---

## Subtask T021 — Close #1463 with decision text

**Purpose**: Close the long-standing issue with the decision text as the close comment (C-002, FR-005, SC-004).

**Critical constraints**:
- Per C-002: use `gh issue comment` + `gh issue close`, NOT a `Closes #1463` footer in a commit.
- Option 1: close as `not-planned`. Option 2 or 3: close as `completed` (after M-G.1 is filed).
- The close comment should quote the decision, not just reference it.

**Steps**:

1. Prepare the close comment text (adapt to chosen option):

   **Option 1**:
   ```
   Closing as not-planned.

   Decision: Option 1 — PHP-only with conviction (bimaaji-mcp-strategic-direction-01KS3SZB, YYYY-MM-DD).

   Rationale (from decision.md):
   - [Key evidence (a): framework code finding]
   - [Key evidence (b): consumer signal finding — no signal as of YYYY-MM-DD]
   - [Key evidence (c): maintenance cost finding]

   The decision is point-in-time. New consumer signal may re-open the question via a new strategic mission.
   Full decision: kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision.md
   ```

   **Option 2**:
   ```
   Closing as completed — follow-up implementation tracked by #<M-G.1 issue number>.

   Decision: Option 2 — extend packages/mcp/ with bimaaji PHP tools (bimaaji-mcp-strategic-direction-01KS3SZB, YYYY-MM-DD).

   Rationale (from decision.md):
   - [Key evidence (a): framework code finding]
   - [Key evidence (b): consumer signal finding]
   - [Key evidence (c): maintenance cost finding]

   Implementation tracked: #<M-G.1 issue number>
   Full decision: kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision.md
   ```

   **Option 3**: similar to Option 2, substituting Option 3 text.

2. Post the close comment:
   ```bash
   gh issue comment 1463 --body "$(cat <<'EOF'
   [paste the prepared text above]
   EOF
   )"
   ```

3. Close the issue:
   ```bash
   # Option 1:
   gh issue close 1463 --reason "not planned"

   # Option 2 or 3:
   gh issue close 1463 --reason "completed"
   ```

4. Verify:
   ```bash
   gh issue view 1463 --json state,stateReason | head -5
   ```

**Validation**:
- [ ] `gh issue view 1463` shows `state: CLOSED`
- [ ] Close comment quotes the decision rationale with evidence citations
- [ ] Close reason matches option chosen (not-planned for Option 1, completed for 2/3)
- [ ] No `Closes #1463` footer appears in any commit from this mission

---

## Final commit

After all three subtasks are done (or T020 is explicitly skipped for Option 1), create a final close-out commit:

```bash
git add kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/
git commit -m "tasks(M-G): WP06 close-out — mcp-endpoint.md updated, #1463 closed [Option N]"
```

---

## Definition of Done

- [ ] `docs/specs/mcp-endpoint.md` has "Bimaaji MCP Positioning" section
- [ ] If Option 2 or 3: M-G.1 GitHub issue created + spec-kitty mission filed
- [ ] If Option 1: T020 explicitly noted as skipped
- [ ] #1463 is closed (gh issue view 1463 shows CLOSED)
- [ ] Close comment on #1463 quotes decision text with evidence citations
- [ ] No `Closes #1463` footer in any commit
- [ ] All commits on `main`

## Risks

- **Medium**: The close comment on #1463 must be accurate — it becomes a permanent public record. Draft it from `decision.md` content, not from memory.
- **Medium**: `gh issue close --reason "not planned"` uses underscore in some gh CLI versions and space in others. Test with `--reason "not planned"` first; if rejected, try `--reason not_planned`.
- **Low**: docs/specs/mcp-endpoint.md may be large — read the full file before editing to avoid accidentally removing unrelated content.
- **Low**: spec-kitty specify flow may be interactive — if the CLI does not support inline args, run it interactively and fill in the prompts.

## Reviewer Guidance

Reviewer should verify: (1) #1463 is closed with appropriate reason (not-planned vs completed), (2) the close comment quotes decision.md evidence (not generic text), (3) the mcp-endpoint.md section is discoverable and concise, (4) no production code was added to any `packages/` or `src/` path during this WP.
