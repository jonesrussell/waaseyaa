# Tasks — Bimaaji MCP Strategic Direction

**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Mission type**: `research` — all WPs are `planning_artifact`; no production code ships
**Branch**: `main` → `main`
**Generated**: 2026-05-20T23:57:38Z

---

## Subtask Index

| ID | Description | WP | Parallel |
|----|-----------|----|---------|
| T001 | Review spec's three options and confirm they are the complete decision space | WP01 | — | [D] |
| T002 | Identify any unlisted options (e.g. option 4: do nothing differently + document) | WP01 | — | [D] |
| T003 | Identify decision criteria, weights, and who the decision-maker is | WP01 | — | [D] |
| T004 | Write `decision-frame.md` in mission directory | WP01 | — | [D] |
| T005 | Enumerate what evidence each option needs before it can be accepted or rejected | WP02 | — |
| T006 | Map each evidence item to a concrete source location in the repo or consumer repos | WP02 | — |
| T007 | Write `methodology.md` in mission directory | WP02 | — |
| T008 | Inventory bimaaji's public PHP surface (FQCNs, public methods, doc annotations) | WP03 | [P] |
| T009 | Snapshot `packages/mcp/` capability — does it support PHP-tool registration today? | WP03 | [P] |
| T010 | Log consumer signal — search Minoo for bimaaji-via-MCP requests or tickets | WP03 | [P] |
| T011 | Document Node sidecar cost history — what failed before, effort cost estimate | WP03 | [P] |
| T012 | Evaluate Option 1 (PHP-only, close) against all decision criteria | WP04 | — |
| T013 | Evaluate Option 2 (extend packages/mcp/) against all decision criteria | WP04 | — |
| T014 | Evaluate Option 3 (restore Node sidecar) against all decision criteria | WP04 | — |
| T015 | Write `analysis.md` with structured pros/cons table per option | WP04 | — |
| T016 | Select the winning option based on analysis + weighted criteria | WP05 | — |
| T017 | Verify evidence citations cover all three NFR-003 categories | WP05 | — |
| T018 | Write `decision.md` (named option + rationale, ≤ 2 pages with pros/cons table) | WP05 | — |
| T019 | Add "Bimaaji MCP positioning" section to `docs/specs/mcp-endpoint.md` (FR-004) | WP06 | — |
| T020 | File M-G.1 follow-up mission + GitHub tracking issue if decision is Option 2 or 3 | WP06 | — |
| T021 | Close #1463 with decision text as close comment (not a `Closes #N` footer) | WP06 | — |

---

## Work Package 1 — Phase 1: Decision Frame

**Goal**: Confirm the complete decision space, identify decision criteria and their weights, and produce `decision-frame.md`.
**Priority**: P0 — all other WPs depend on this
**Estimated prompt size**: ~250 lines
**Independent test**: `decision-frame.md` is present in the mission directory and names ≥3 options with ≥4 decision criteria
**Prompt file**: `tasks/WP01-decision-frame.md`

### Subtasks

- [x] T001 Review spec's three options and confirm they are the complete decision space (WP01)
- [x] T002 Identify any unlisted options (e.g. option 4: do nothing differently + document) (WP01)
- [x] T003 Identify decision criteria, weights, and who the decision-maker is (WP01)
- [x] T004 Write `decision-frame.md` in mission directory (WP01)

### Implementation sketch

Read spec.md §"Three options on the table" and §"Decision Criteria" from plan.md. Confirm whether those are exhaustive — a quick search for any GitHub issues or README text suggesting a 4th option (e.g. REST API only, gRPC, no MCP at all but document why). Produce a short document: decision-maker (maintainer), options enumerated with one-line summaries, criteria table with weights. No recommendation yet — that's WP05.

### Dependencies

None — first WP.

### Risks

Low. The options are well-defined in the spec. The main risk is overlooking a 4th option (e.g. "defer indefinitely" as a distinct choice from "close with conviction").

---

## Work Package 2 — Phase 2: Methodology

**Goal**: Produce `methodology.md` — a map of what evidence to gather and where to find it.
**Priority**: P0 — WP03 cannot execute without this
**Estimated prompt size**: ~220 lines
**Independent test**: `methodology.md` lists ≥4 evidence items, each with a concrete source location (file path or repo)
**Prompt file**: `tasks/WP02-methodology.md`

### Subtasks

- [ ] T005 Enumerate what evidence each option needs before it can be accepted or rejected (WP02)
- [ ] T006 Map each evidence item to a concrete source location in the repo or consumer repos (WP02)
- [ ] T007 Write `methodology.md` in mission directory (WP02)

### Implementation sketch

For each option, ask: "What would make me confident choosing or rejecting this?" Then trace where that evidence lives. Example: Option 2 requires knowing if `packages/mcp/` already supports PHP-tool registration — the source is `packages/mcp/src/`. Consumer signal source is Minoo's issue tracker or codebase. Sidecar cost source is git log + #1387 / #1463 context. Output a table: evidence item → source location → gather method.

### Dependencies

Depends on WP01 (decision frame must exist before methodology can be scoped per option)

### Risks

Low. The evidence sources are all local to the repo or accessible via gh CLI.

---

## Work Package 3 — Phase 3: Gather

**Goal**: Execute the methodology. Produce four structured evidence notes in `research/`.
**Priority**: P1 — inputs for analysis
**Estimated prompt size**: ~400 lines
**Independent test**: All four files exist in `research/`: `bimaaji-surface.md`, `mcp-capability.md`, `consumer-signal.md`, `sidecar-cost.md`
**Prompt file**: `tasks/WP03-gather.md`

### Subtasks

- [ ] T008 Inventory bimaaji's public PHP surface (FQCNs, public methods, doc annotations) (WP03)
- [ ] T009 Snapshot `packages/mcp/` capability — does it support PHP-tool registration today? (WP03)
- [ ] T010 Log consumer signal — search Minoo for bimaaji-via-MCP requests or tickets (WP03)
- [ ] T011 Document Node sidecar cost history — what failed before, effort cost estimate (WP03)

### Implementation sketch

T008/T009 are parallelizable (different packages). T010 requires checking the Minoo repo (if accessible at ~/dev or as a GitHub repo) and the issue tracker. T011 is git archaeology: `git log --all --oneline -- packages/bimaaji/mcp/` plus reading #1387 and #1463 body context. Each note should be structured: what was found, what it implies for each option, date-stamped.

### Parallel opportunities

T008, T009, T010, T011 can all proceed in parallel within the WP (each reads different files/sources).

### Dependencies

Depends on WP02 (methodology.md must be written before gather can execute against it)

### Risks

Medium. Minoo may not be accessible locally — note "no consumer signal at this time" with date if so. packages/mcp/ may have a complex API — do not over-invest, just determine the single key question: does it support PHP-tool registration?

---

## Work Package 4 — Phase 4: Analyze

**Goal**: Synthesize gathered evidence into a structured pros/cons evaluation of all three options.
**Priority**: P1
**Estimated prompt size**: ~300 lines
**Independent test**: `analysis.md` exists with a table scoring each of the 3 options against each decision criterion
**Prompt file**: `tasks/WP04-analyze.md`

### Subtasks

- [ ] T012 Evaluate Option 1 (PHP-only, close) against all decision criteria (WP04)
- [ ] T013 Evaluate Option 2 (extend packages/mcp/) against all decision criteria (WP04)
- [ ] T014 Evaluate Option 3 (restore Node sidecar) against all decision criteria (WP04)
- [ ] T015 Write `analysis.md` with structured pros/cons table per option (WP04)

### Implementation sketch

Read decision-frame.md (criteria + weights) and all four research/*.md notes. For each option × criterion pair, record a short assessment and a score (e.g. Low/Medium/High benefit, Low/Medium/High cost). Summarize strengths and weaknesses per option. Do not make the decision here — that's WP05. The document should make the decision obvious but leave the call to WP05.

### Dependencies

Depends on WP03 (all research notes must exist before analysis)

### Risks

Low if WP03 is complete. The risk is over-analysis — keep to the 40-minute estimate, not a dissertation.

---

## Work Package 5 — Phase 5: Decide

**Goal**: Write `decision.md` — the named option with evidence-backed rationale, ≤ 2 pages.
**Priority**: P1 — primary mission deliverable
**Estimated prompt size**: ~280 lines
**Independent test**: `decision.md` exists, names one option (1, 2, or 3), cites evidence from all three NFR-003 categories
**Prompt file**: `tasks/WP05-decide.md`

### Subtasks

- [ ] T016 Select the winning option based on analysis + weighted criteria (WP05)
- [ ] T017 Verify evidence citations cover all three NFR-003 categories (WP05)
- [ ] T018 Write `decision.md` (named option + rationale, ≤ 2 pages with pros/cons table) (WP05)

### Implementation sketch

Read analysis.md. Apply the criteria weights from decision-frame.md. Make the call. Write decision.md: header with the option name, a one-paragraph rationale, a compact pros/cons table (can be embedded from analysis.md), and a section listing the specific evidence citations per NFR-003 category (a) framework code state, (b) consumer signal, (c) maintenance-cost history. If option 2 or 3: add a stub for M-G.1's scope (to be expanded in WP06). Keep to ≤2 pages.

### Dependencies

Depends on WP04 (analysis.md must exist before decision)

### Risks

Low. The analysis should make the choice clear. If two options are close, the maintainer breaks the tie — note the ambiguity and the tiebreaker criteria in decision.md.

---

## Work Package 6 — Phase 6: Publish + Close-out

**Goal**: Land the decision publicly: update framework spec, file M-G.1 if needed, close #1463.
**Priority**: P1 — completes the mission's observable effects
**Estimated prompt size**: ~320 lines
**Independent test**: `docs/specs/mcp-endpoint.md` has a "Bimaaji MCP positioning" section; #1463 is closed; M-G.1 exists if decision was Option 2 or 3
**Prompt file**: `tasks/WP06-publish-close-out.md`

### Subtasks

- [ ] T019 Add "Bimaaji MCP positioning" section to `docs/specs/mcp-endpoint.md` (FR-004) (WP06)
- [ ] T020 File M-G.1 follow-up mission + GitHub tracking issue if decision is Option 2 or 3 (WP06)
- [ ] T021 Close #1463 with decision text as close comment (not a `Closes #N` footer) (WP06)

### Implementation sketch

T019: Read decision.md, then write a concise "Bimaaji MCP positioning" section (3-5 sentences + date) into docs/specs/mcp-endpoint.md. T020: If option ≠ 1, run `spec-kitty specify` to file M-G.1, then open a GitHub issue via `gh issue create`. T021: Use `gh issue comment 1463 --body "..."` followed by `gh issue close 1463 --reason "not planned"` (Option 1) or `--reason "completed"` (Options 2/3 after M-G.1 is filed). Commit the docs/specs edit. Commit hash becomes the mission close-out record.

### Dependencies

Depends on WP05 (decision.md must exist before publish)

### Risks

Medium. The close comment on #1463 must quote the decision accurately. If M-G.1 spec filing takes time, it can be done after #1463 is closed (close comment can reference "M-G.1 to be filed"). The docs edit must not accidentally remove unrelated content from mcp-endpoint.md — read the full file before editing.

---

## Execution Order

```
WP01 → WP02 → WP03 → WP04 → WP05 → WP06
```

All sequential. Each phase depends on the prior phase's artifact.

---

## Requirement Coverage

| FR/NFR | Covered by |
|--------|-----------|
| FR-001 | WP05 (decision.md) |
| FR-002 | WP05 (evidence citations), WP03 (gather) |
| FR-003 | WP06 (M-G.1 if applicable) |
| FR-004 | WP06 (docs/specs/mcp-endpoint.md edit) |
| FR-005 | WP06 (close #1463) |
| FR-006 | All WPs (no code — planning_artifact mode) |
| NFR-001 | ~4h total across all WPs |
| NFR-002 | WP05 (≤2 page cap) |
| NFR-003 | WP03 + WP05 (three evidence categories) |
