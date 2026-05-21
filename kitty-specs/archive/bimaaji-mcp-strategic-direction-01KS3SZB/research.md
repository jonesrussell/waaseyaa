# Research: Bimaaji MCP — Strategic Direction

**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Phase**: 0 (methodology) + 3 (evidence index)
**Date**: 2026-05-20

This file documents (a) the research methodology (what evidence to gather and
where) and (b) serves as the index into the `research/` subdirectory once
evidence is gathered in WP03.

---

## Methodology (WP02 pre-work)

The decision between the three options requires four categories of evidence:

### Evidence Category A — Bimaaji's current PHP surface

**What to gather**: A FQCN inventory of `packages/bimaaji/src/` — classes,
interfaces, public methods. Focus on operations that would be candidates for
MCP tool exposure (graph queries, introspection, traversal).

**Where to find it**:
- `packages/bimaaji/src/` — source tree
- `packages/bimaaji/composer.json` — namespace, dependencies
- `packages/bimaaji/README.md` — stated public surface

**Output**: `research/bimaaji-surface.md`

**Why it matters**: Options 2 and 3 both require knowing what PHP operations
would become MCP tools. If the surface is thin or already reachable via the
standard HTTP API, Option 1 becomes more defensible.

---

### Evidence Category B — `packages/mcp/` capability snapshot

**What to gather**: Does `packages/mcp/` support PHP-tool registration today?
What shape does it expose? Can a sibling package register tools with it without
`packages/mcp/` depending on that sibling (layer rule: mcp is Layer 6,
bimaaji is Layer 2 — safe upward dependency)?

**Where to find it**:
- `packages/mcp/src/` — source tree
- `packages/mcp/README.md` — public surface
- `docs/specs/mcp-endpoint.md` — framework MCP spec
- `packages/mcp/composer.json` — layer position check

**Output**: `research/mcp-capability.md`

**Why it matters**: Option 2 requires PHP-tool registration to be either
already supported or cheaply addable. If `packages/mcp/` needs a separate
predecessor framework change, Option 2's cost rises significantly — potentially
making Option 1 the more rational near-term choice.

**Memory note**: `feedback_completion_mission_pre_grep.md` — check if tool
registration behavior already exists before concluding it doesn't.

---

### Evidence Category C — Consumer signal

**What to gather**: Is there a downstream consumer (Minoo specifically) that
has filed an issue or expressed a need for bimaaji-via-MCP? Check GitHub issues
in this repo filtered to `bimaaji` + `mcp`. Note the date of last activity on
#1463 and #1387.

**Where to find it**:
- `gh issue view 1463` — the direct ticket
- `gh issue view 1387` — the parent ticket
- `gh issue list --search "bimaaji mcp"` — any adjacent signals
- Minoo repo (if accessible) — any consumer-side requests

**Output**: `research/consumer-signal.md`

**Why it matters**: NFR-003(b) requires consumer signal evidence. "No signal at
this time" is a valid finding and a strong data point for Option 1. Positive
signal would elevate Option 2 or 3.

---

### Evidence Category D — Node sidecar maintenance-cost history

**What to gather**: What was the Node sidecar (`packages/bimaaji/mcp/server.js`)?
When was it added and removed? What was the failure mode (`composer
bimaaji-mcp-install` exits 254)? What git commits and issues track this history?

**Where to find it**:
- `git log --all --oneline -- packages/bimaaji/mcp/` — commit history for the
  removed directory
- `git log --all --oneline --grep="bimaaji-mcp"` — commit messages mentioning it
- Issue #1463 and #1387 bodies — failure context already summarized
- `packages/bimaaji/composer.json` — check for any remaining `scripts` hooks

**Output**: `research/sidecar-cost.md`

**Why it matters**: NFR-003(c) requires maintenance-cost history. Option 3
(restore Node sidecar) requires knowing what went wrong before and why this
attempt would be different. If the failure root cause is not diagnosed, Option 3
cannot be chosen responsibly.

---

## Evidence Index (populated in WP03)

| File | Status | Summary |
|------|--------|---------|
| `research/bimaaji-surface.md` | Not yet gathered | Bimaaji PHP public API inventory |
| `research/mcp-capability.md` | Not yet gathered | packages/mcp/ tool-registration capability |
| `research/consumer-signal.md` | Not yet gathered | Downstream consumer signal log |
| `research/sidecar-cost.md` | Not yet gathered | Node sidecar history + failure diagnosis |

---

## Decisions Made at Planning Time

| Decision | Rationale |
|----------|-----------|
| FR-004 target: `docs/specs/mcp-endpoint.md` | Decision context belongs with the framework's MCP surface spec; `packages/bimaaji/README.md` is the implementation doc, not the strategic doc. |
| No data-model.md, no contracts/ | Research mission — no API, no entities, no contracts. |
| research.md serves as combined Phase 0 + Phase 3 index | Avoids orphan files; WP03 populates the `research/` subdirectory; this file indexes them. |

---

## Alternatives Considered (pre-research)

These are the three options from the spec. This section is a placeholder;
`analysis.md` (WP04) will evaluate them properly.

| Option | Initial lean | Reason |
|--------|-------------|--------|
| 1 — PHP-only, close #1463 | Likely default | No consumer signal yet; Node sidecar already failed once |
| 2 — Extend packages/mcp/ | Viable if mcp supports PHP tools | Low blast radius; uses existing framework surface |
| 3 — Node sidecar | Risky without failure diagnosis | Previously failed; highest maintenance cost |

*These leans are hypotheses, not conclusions. WP03–WP05 will resolve them.*
