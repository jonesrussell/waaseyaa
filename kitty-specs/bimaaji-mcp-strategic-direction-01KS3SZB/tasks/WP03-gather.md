---
work_package_id: WP03
title: Phase 3 — Gather
dependencies:
- WP02
requirement_refs:
- FR-002
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T008
- T009
- T010
- T011
history:
- date: '2026-05-20T23:57:38Z'
  agent: tasks-materializer
  action: created
authoritative_surface: kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/research/
execution_mode: planning_artifact
owned_files:
- kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/research/bimaaji-surface.md
- kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/research/mcp-capability.md
- kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/research/consumer-signal.md
- kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/research/sidecar-cost.md
tags: []
---

# WP03 — Phase 3: Gather

**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Branch strategy**: `main` → `main` (commit directly, no worktree)
**Effort estimate**: ~90 minutes (largest WP — bounded by NFR-001's 4-hour total cap)
**Execution mode**: `planning_artifact` — no production code

## Objective

Execute the methodology from WP02. Produce four structured evidence notes in `research/`. These notes are the raw material for WP04 (Analyze) and must be factual, date-stamped, and clearly tied to the decision options.

**Important**: If a source is inaccessible (e.g. Minoo repo not present locally), record "no signal found at YYYY-MM-DD" — that is valid evidence in favor of Option 1.

## Context

The four research notes map to four distinct evidence categories:

| File | Evidence category | NFR-003 |
|------|-----------------|---------|
| `bimaaji-surface.md` | Bimaaji's public PHP API | (a) framework code |
| `mcp-capability.md` | `packages/mcp/` PHP-tool registration capability | (a) framework code |
| `consumer-signal.md` | Downstream consumer requests for bimaaji-via-MCP | (b) consumer signal |
| `sidecar-cost.md` | Node sidecar maintenance cost history | (c) maintenance cost |

## Branch Strategy

- Planning/base branch: `main`
- Final merge target: `main`
- Execution: commit directly to `main`. No feature branch, no worktree.
- Dependencies resolved: WP02 (`methodology.md`) must be complete first.
- Implementation command: `spec-kitty agent action implement WP03 --agent <name>`

---

## Subtask T008 — Inventory bimaaji's public PHP surface

**Purpose**: Determine what graph/relationship operations bimaaji currently exposes publicly. This informs Option 2 (which operations would become MCP tools) and Option 1 (whether the HTTP API already covers agent needs).

**Steps**:

1. List the bimaaji package structure:
   ```bash
   find /home/jones/dev/waaseyaa/packages/bimaaji/src -name "*.php" | sort
   ```

2. For each public class/interface found, record:
   - FQCN
   - Type (class / interface / abstract class / trait)
   - Primary public methods (just names and signatures, not full implementations)
   - Any `@api` annotation or PHPDoc indicating it's a public extension point

3. Identify which operations are graph-traversal or relationship-query operations (these are the candidates for MCP tools if Option 2 is chosen).

4. Check `packages/bimaaji/composer.json` for the package description and any declared provides/suggests.

5. Check `packages/bimaaji/README.md` for any documented public API.

**Output format for `research/bimaaji-surface.md`**:

```markdown
# Bimaaji Public PHP Surface

**Date gathered**: YYYY-MM-DD
**Source**: packages/bimaaji/src/

## Public Classes/Interfaces

| FQCN | Type | Key public methods | @api? |
|------|----|-------------------|-------|
| ... | ... | ... | ... |

## Graph Operation Candidates (for Option 2)

<list of operations that would make sense as MCP tools>

## HTTP API Coverage

<does the existing HTTP API surface already expose these operations? cite route files if present>

## Implications per option

- Option 1: <one sentence>
- Option 2: <one sentence>
- Option 3: <one sentence>
```

**Effort cap**: 30 minutes. If the package is large, capture the top-level public interfaces only — do not descend into implementation details.

**Validation**:
- [ ] At least one FQCN inventoried
- [ ] Graph operation candidates listed (or explicitly noted as "none found")
- [ ] Implications per option present
- [ ] Date-stamped

---

## Subtask T009 — Snapshot `packages/mcp/` capability

**Purpose**: Determine whether the existing PHP MCP host already supports PHP-tool registration. This is the single most important question for Option 2 viability.

**Steps**:

1. List the mcp package structure:
   ```bash
   find /home/jones/dev/waaseyaa/packages/mcp/src -name "*.php" | sort
   ```

2. Read `docs/specs/mcp-endpoint.md` — the framework's current MCP surface specification.

3. Search for tool registration interfaces or patterns:
   ```bash
   grep -r "ToolInterface\|ToolRegistrar\|registerTool\|McpTool\|tool_name\|tool_description" \
     /home/jones/dev/waaseyaa/packages/mcp/src/ 2>/dev/null | head -40
   ```

4. Determine: Does `packages/mcp/` today support registering a custom PHP callable as an MCP tool? Possible answers:
   - **Yes, fully**: Tool registration API exists, is documented, and is extensible.
   - **Yes, partially**: Something exists but is incomplete or not designed for external extension.
   - **No**: No tool registration mechanism. Option 2 would require this to be built first (= predecessor framework work).
   - **Unknown**: Package exists but is a stub or scaffold with minimal implementation.

5. If tool registration exists, record: the interface FQCN, how a consumer would register a tool, and whether bimaaji could register its graph operations without changes to `packages/mcp/`.

**Output format for `research/mcp-capability.md`**:

```markdown
# packages/mcp/ Capability Snapshot

**Date gathered**: YYYY-MM-DD
**Source**: packages/mcp/src/, docs/specs/mcp-endpoint.md

## PHP Tool Registration: YES / NO / PARTIAL / UNKNOWN

<one paragraph explaining the finding>

## Key Interfaces/Classes Found

| FQCN | Purpose | Relevant to Option 2? |
|------|---------|----------------------|
| ... | ... | ... |

## Gap Assessment for Option 2

<If tool registration does not exist: what would need to be built first?>
<If it does exist: what would bimaaji need to do to register its tools?>

## Implications per option

- Option 1: <one sentence>
- Option 2: <one sentence — is it straightforward or does it require predecessor work?>
- Option 3: <one sentence>
```

**Effort cap**: 25 minutes. The key question is binary: does PHP-tool registration exist? Answer it definitively.

**Validation**:
- [ ] Binary answer to "does PHP-tool registration exist?" is stated clearly
- [ ] Gap assessment for Option 2 is present
- [ ] Date-stamped

---

## Subtask T010 — Log consumer signal

**Purpose**: Determine whether any downstream consumer (Minoo primarily) has explicitly requested bimaaji-via-MCP. Absence of signal is valid evidence for Option 1.

**Steps**:

1. Check if Minoo repo is accessible locally:
   ```bash
   ls /home/jones/dev/waaseyaa.org/ 2>/dev/null | head -20
   # OR check other likely paths:
   ls /home/jones/dev/ 2>/dev/null | grep -i minoo
   ```

2. If accessible: search for bimaaji MCP references in Minoo:
   ```bash
   grep -r "bimaaji\|mcp" /home/jones/dev/waaseyaa.org/ --include="*.php" --include="*.md" \
     --include="*.json" -l 2>/dev/null | head -20
   ```

3. Search GitHub issues for consumer signal:
   ```bash
   gh issue list --state all --search "bimaaji mcp" --limit 20 2>/dev/null
   gh issue list --state all --search "bimaaji MCP" --limit 20 2>/dev/null
   ```

4. Check if any consumer's `composer.json` requires bimaaji:
   ```bash
   find /home/jones/dev -name "composer.json" -not -path "*/vendor/*" \
     -exec grep -l "waaseyaa/bimaaji" {} \; 2>/dev/null | head -10
   ```

5. Record the signal as one of:
   - **Active signal**: A consumer has an open issue, PR, or code requesting bimaaji-via-MCP.
   - **Latent signal**: A consumer uses bimaaji and might benefit from MCP, but has not explicitly asked.
   - **No signal**: No consumer uses or has requested bimaaji-via-MCP as of the gather date.

**Output format for `research/consumer-signal.md`**:

```markdown
# Consumer Signal Log

**Date gathered**: YYYY-MM-DD
**Sources checked**: [list repos/CLIs checked]

## Signal Level: ACTIVE / LATENT / NONE

<one paragraph with findings>

## Evidence

<list any specific issues, PRs, or code references found>

## Implication

<one sentence per option based on signal level>
```

**Validation**:
- [ ] Signal level stated (Active / Latent / None)
- [ ] Sources checked are listed (even if the answer is "repo not accessible")
- [ ] Date-stamped

---

## Subtask T011 — Document Node sidecar cost history

**Purpose**: Quantify (qualitatively) the maintenance cost of the previous Node sidecar attempt. This is the primary evidence against Option 3.

**Steps**:

1. Check git history for the sidecar:
   ```bash
   git log --all --oneline --diff-filter=D -- packages/bimaaji/mcp/ 2>/dev/null
   git log --all --oneline --diff-filter=A -- packages/bimaaji/mcp/ 2>/dev/null
   git log --all --oneline -- "packages/bimaaji/mcp*" 2>/dev/null | head -20
   ```

2. Read the #1463 issue body for the failure description:
   ```bash
   gh issue view 1463 2>/dev/null
   ```

3. Read the #1387 issue body for prior context:
   ```bash
   gh issue view 1387 2>/dev/null
   ```

4. Search for the failure mode:
   ```bash
   git log --all --grep="bimaaji-mcp\|bimaaji mcp\|exit 254" --oneline 2>/dev/null | head -20
   grep -r "bimaaji-mcp-install\|mcp/server.js\|exit 254" /home/jones/dev/waaseyaa/ \
     --include="*.md" --include="*.json" -l 2>/dev/null | head -10
   ```

5. Identify and record:
   - When the sidecar was added and when it was removed (approximate dates from git)
   - The failure mode: `composer bimaaji-mcp-install` exits 254; `vendor/waaseyaa/bimaaji/mcp/server.js` not present at runtime
   - Root cause (if determinable from commits/issues)
   - Effort estimate: number of commits, PRs, issues related to bimaaji MCP
   - Whether the failure was fixed and re-broke, or was never fixed

**Output format for `research/sidecar-cost.md`**:

```markdown
# Node Sidecar Maintenance Cost History

**Date gathered**: YYYY-MM-DD
**Sources**: git log, #1387, #1463

## Timeline

| Date | Event |
|------|-------|
| approx YYYY-MM | Sidecar added in packages/bimaaji/mcp/ |
| approx YYYY-MM | Failure reported (exit 254, server.js missing at runtime) |
| approx YYYY-MM | Sidecar removed |

## Failure Mode

<description of what failed and why>

## Root Cause (if determinable)

<diagnosis or "root cause not determinable from git history">

## Cost Estimate

- Commits related to bimaaji MCP: N
- Issues: #1387, #1463 (+ any others found)
- Developer time estimate: <qualitative: "low/medium/high based on N commits over M months">

## Implications per option

- Option 3 (restore sidecar): <is the failure addressable? what would it take?>
- Option 1 / 2: <sidecar cost history supports choosing these>
```

**Validation**:
- [ ] Timeline present (even if approximate)
- [ ] Failure mode described
- [ ] Cost estimate (qualitative) present
- [ ] Date-stamped

---

## After all four notes are written

Commit all four files together:

```bash
git add kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/research/
git commit -m "tasks(M-G): WP03 gather — bimaaji surface, mcp capability, consumer signal, sidecar cost"
```

---

## Definition of Done

- [ ] `research/bimaaji-surface.md` exists and is date-stamped
- [ ] `research/mcp-capability.md` exists with a binary answer on PHP-tool registration
- [ ] `research/consumer-signal.md` exists with a signal level (Active/Latent/None)
- [ ] `research/sidecar-cost.md` exists with failure mode and cost estimate
- [ ] All four files committed to `main`
- [ ] Each file's "Implications per option" section is present

## Risks

- **Medium**: Minoo may not be locally accessible — record "no local repo; gh CLI search returned N results" as the evidence.
- **Medium**: The git log for packages/bimaaji/mcp/ may be sparse if the directory was short-lived or added/removed in a single squash commit — note this limitation.
- **Low**: packages/mcp/ may be a small stub — "no tool registration API" is a complete and valid finding.

## Reviewer Guidance

Reviewer should verify: (1) all four research notes are present, (2) each has a date stamp, (3) the mcp-capability.md answers the PHP-tool-registration question unambiguously, (4) consumer-signal.md names all sources checked (not just sources that returned results), (5) sidecar-cost.md identifies at least the failure mode even if root cause is unknown.
