# Bimaaji MCP — Strategic Direction

**Mission:** `bimaaji-mcp-strategic-direction-01KS3SZB`
**Mission type:** `research` (strategic-fork brainstorm — deliverable is a written decision + scoped follow-ups, not code)
**Status:** Spec
**Target branch:** `main`
**Closes:** #1463 (one outcome of the research is to close this issue, either way)
**Deferred from:** #1387

## Why this mission exists

In the current alpha range, `packages/bimaaji/` ships **PHP-only**. The previous attempt to also ship a Node-based MCP server — `packages/bimaaji/mcp/server.js`, with a `composer bimaaji-mcp-install` hook — never reached consumers reliably:

- `composer bimaaji-mcp-install` exits 254 in Minoo.
- `vendor/waaseyaa/bimaaji/mcp/server.js` is not present at runtime.
- The Node scaffolding was removed because it never worked, leaving `bimaaji` PHP-only by default.

Meanwhile, **`packages/mcp/` exists separately** as the framework's MCP endpoint surface (see `docs/specs/mcp-endpoint.md`). The framework already has a tested PHP-side MCP host; bimaaji has graph-introspection operations that an agent might want to call.

The strategic question — left "TBD by maintainer" in #1463 — is: **what shape of MCP access (if any) should bimaaji's graph operations have?** This is not a coding decision. It is a positioning decision about how Waaseyaa interfaces with agent runtimes for graph/relationship operations, and the answer determines whether code is needed at all.

A real brainstorm requires data: the framework's current MCP capabilities, bimaaji's actual public-surface operations, downstream consumer needs (Minoo's identified use cases, if any), maintenance cost of a Node sidecar vs in-process PHP tooling. This mission's deliverable is **a written decision document + scoped follow-up missions if the decision is to ship something**, not a code change.

## Research question

What MCP-accessible surface (if any) should `packages/bimaaji/` expose, and through what implementation?

### Three options on the table

1. **PHP-only, close #1463 with conviction.** No MCP server. Agents that want to call bimaaji operations do so via the existing `packages/mcp/` host registering bimaaji tools, or via the standard HTTP API. The mission ends with a decision document and `#1463` closes as `not-planned`.

2. **Extend `packages/mcp/`.** Add bimaaji-specific MCP tools to the existing PHP-side MCP host. No Node sidecar; the tools call into bimaaji's PHP API directly. Lowest blast radius. The mission ends with a follow-up `M-G.1` mission filed to implement the tools, and `#1463` closes when M-G.1 merges.

3. **Restore a separate Node-based MCP server.** Re-add `packages/bimaaji/mcp/server.js` (or similar), fix the install path so `vendor/waaseyaa/bimaaji/mcp/server.js` actually lands, document the install in a way that works for consumers. This is the highest-cost option and the one that previously failed; choosing it requires identifying what went wrong before and how the fix differs. The mission ends with a follow-up `M-G.1` mission scoped accordingly.

## Investigation phases

Per the spec-kitty `research` mission contract, the mission progresses through these phases as work packages:

- **Phase 1 — Question framing.** Confirm the three options above are the complete decision space; add or rule out alternatives. Identify the decision-makers and decision criteria. Deliverable: a short `decision-frame.md` in this mission's directory.

- **Phase 2 — Methodology.** Decide what evidence the decision needs: bimaaji's current public PHP surface (FQCN inventory), existing `packages/mcp/` capability (does it support PHP-tool registration today? what shape?), downstream consumer needs (Minoo specifically — is there a current ticket asking for bimaaji-via-MCP?), maintenance cost benchmarks for Node sidecars in this repo. Deliverable: a `methodology.md` listing what to gather and from where.

- **Phase 3 — Gather.** Execute the methodology. Read code, search consumer repos (Minoo if accessible, otherwise note "no consumer signal at this time"), enumerate what each option would actually require. Deliverable: structured notes in `research/` under the mission directory.

- **Phase 4 — Analyze.** Synthesize. Evaluate each of the three options against the decision criteria. Deliverable: `analysis.md` with a pros/cons table per option.

- **Phase 5 — Synthesize / decide.** Land a recommendation. The recommendation may be: "Option 1 — close PHP-only." That is a complete and acceptable outcome. Or: "Option 2 — extend packages/mcp/, here is M-G.1's scope." Or: "Option 3 — Node sidecar restoration, here is M-G.1's scope and the prior-failure diagnosis." Deliverable: `decision.md`.

- **Phase 6 — Publish.** File any follow-up missions identified by the decision. Update `docs/specs/mcp-endpoint.md` with the decision context (so future contributors do not re-litigate). Close #1463 with the decision text in the close comment.

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | The mission produces a `decision.md` (or equivalent) under `kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/` that names the chosen option (1, 2, or 3) and the rationale. |
| FR-002 | Mandatory | The chosen option's rationale cites specific evidence from the gather phase — not opinion. For example: "Option 1 chosen because (a) no consumer signal as of YYYY-MM-DD, (b) packages/mcp/ does not yet support PHP-tool registration so Option 2 would require predecessor framework work, (c) Node sidecar maintenance cost in this repo has been > X in the past." |
| FR-003 | Mandatory | If the decision is Option 2 or Option 3, a follow-up mission `M-G.1` is filed with a concrete spec scope and a tracking GitHub issue. |
| FR-004 | Mandatory | `docs/specs/mcp-endpoint.md` (or `packages/bimaaji/README.md`, planner picks) gains a short "Bimaaji MCP positioning" section so future contributors understand the decision and do not re-open it absent new signal. |
| FR-005 | Mandatory | Issue #1463 closes with the decision text as the close comment. If the decision is Option 1, the issue closes as `not-planned`. |
| FR-006 | Mandatory | The mission does not ship any production PHP, JavaScript, or configuration code. All artifacts are markdown documents under `kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/` or the named spec/docs edits in FR-004. |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | The mission's total effort is bounded to ≤ 4 focused hours (research is cheap; the value is the decision, not the artifacts). If a phase blows past 2 hours, the planner pauses and re-scopes. |
| NFR-002 | Mandatory | The decision document is concise — ≤ 2 pages including the pros/cons table. A long document is a sign of unresolved ambiguity, not thoroughness. |
| NFR-003 | Mandatory | The decision cites at least one piece of evidence from each of: (a) framework-internal code state, (b) consumer signal (positive, negative, or "no signal at this time"), (c) maintenance-cost history. |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | The mission ships no production code. If a coding need surfaces, it goes into M-G.1 (a follow-up mission), not this one. |
| C-002 | Mandatory | The merge (or close-out) commit closes #1463 via the close comment, not via a `Closes #N` footer (since there is no merge PR for a research mission). |
| C-003 | Mandatory | The decision document is committed to the mission directory and remains as a permanent record even after the mission archives. |
| C-004 | Mandatory | No CI hooks bypassed during this mission. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | A written decision exists naming one of the three options. | `decision.md` file present in mission directory. |
| SC-002 | The decision is evidence-backed per NFR-003. | Reviewer (the maintainer) confirms each evidence category is cited. |
| SC-003 | Future contributors can discover the decision without reading the mission directory. | The mission's FR-004 documentation edit (in `docs/specs/mcp-endpoint.md` or `packages/bimaaji/README.md`) cites the decision. |
| SC-004 | Issue #1463 is closed with the decision text. | `gh issue view 1463` shows the issue closed with a comment quoting `decision.md`. |
| SC-005 | If the decision is Option 2 or 3, M-G.1 exists. | New mission directory under `kitty-specs/` AND new GitHub issue tracking it. |

## Key artifacts

| Artifact | Role |
|---|---|
| `decision-frame.md` (Phase 1) | The decision space, criteria, decision-makers. |
| `methodology.md` (Phase 2) | What evidence to gather. |
| `research/*.md` (Phase 3) | Gathered evidence — bimaaji surface inventory, packages/mcp/ capability snapshot, consumer signal log, sidecar-cost history. |
| `analysis.md` (Phase 4) | Pros/cons evaluation. |
| `decision.md` (Phase 5) | The final recommendation. |
| Optional new mission `M-G.1` (Phase 6) | If decision is Option 2 or 3. |
| `docs/specs/mcp-endpoint.md` or `packages/bimaaji/README.md` edit (Phase 6) | Public-facing positioning. |

## Assumptions

- A 4-hour upper bound is realistic. The decision space is small (three options), the framework code is known, and the consumer signal (Minoo) is either present or it isn't.
- The maintainer is the sole decision-maker; the mission's role is to surface evidence, not to mandate an outcome.
- Closing #1463 with `not-planned` (Option 1) is **not failure**. "We considered it carefully and decided not to ship it" is a legitimate, ship-worthy outcome of a research mission.
- If new consumer signal arrives after this mission closes (e.g. a downstream user requests bimaaji-via-MCP), a new strategic mission can re-open the question. This mission's decision is point-in-time, not eternal.

## Out of scope

- Implementing any of the three options. All implementation is M-G.1's territory.
- Refactoring `packages/bimaaji/`'s existing PHP surface.
- Changing `packages/mcp/`'s host capabilities.
- Reopening the broader `#1387` parent ticket.
- Scoping bimaaji's graph operations themselves (those are independent of MCP positioning).

## WP outline (for /spec-kitty.plan)

For a research mission, the WPs map roughly to the six investigation phases:

- **WP01 — Phase 1: Decision frame.** Confirm the three options. Identify criteria. Write `decision-frame.md`.
- **WP02 — Phase 2: Methodology.** Write `methodology.md` listing evidence to gather and where to find it.
- **WP03 — Phase 3: Gather.** Execute the methodology. Notes in `research/`.
- **WP04 — Phase 4: Analyze.** Write `analysis.md`.
- **WP05 — Phase 5: Decide.** Write `decision.md`.
- **WP06 — Phase 6: Publish + close-out.** File M-G.1 if applicable, edit docs, close #1463.

## References

- Issue #1463 body — names the three options and the prior Node-sidecar failure context.
- Parent ticket: #1387 (where bimaaji MCP scope was originally deferred).
- `docs/specs/mcp-endpoint.md` — the framework's current MCP surface.
- `packages/bimaaji/composer.json`, `packages/bimaaji/README.md`, `packages/bimaaji/src/*` — bimaaji's current PHP surface.
- `packages/mcp/composer.json`, `packages/mcp/README.md`, `packages/mcp/src/*` — the existing MCP host.
- CLAUDE.md § "Workflow" — Spec Kitty research mission contract.
- Memory: `feedback_completion_mission_pre_grep.md` — before filing a "complete the wiring for X" mission, check if the behavior already exists. (Relevant here as a Phase 3 check: does `packages/mcp/` already support PHP-tool registration?)
