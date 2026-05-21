# Research Methodology — Bimaaji MCP Strategic Direction

**Date**: 2026-05-20
**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Based on**: `decision-frame.md`

---

## Evidence Requirements

This phase maps what concrete evidence WP03 must gather to evaluate Options 1–3 against the five decision criteria. Evidence is organized into four categories (NFR-003). WP03 executes the gather; this document is the gather plan.

---

## Evidence Map

| Evidence item | Option(s) | Source | Gather method | Research target |
|---|---|---|---|---|
| Bimaaji public PHP surface (classes, interfaces, entry points) | 2 | `packages/bimaaji/src/` | `find packages/bimaaji/src -name "*.php"` + read key files | `research/bimaaji-surface.md` |
| `packages/mcp/` tool-registration capability today | 2 | `packages/mcp/src/`, `docs/specs/mcp-endpoint.md` | `find packages/mcp/src -name "*.php"` + grep `registerTool\|ToolInterface\|ToolRegistrar` + read spec | `research/mcp-capability.md` |
| Consumer signal — Minoo or downstream request for bimaaji-via-MCP | 1, 2, 3 | `/home/jones/dev/waaseyaa.org/` (local check first); fallback: `gh issue list --search "bimaaji mcp"` on Minoo repo; prior bimaaji missions in `kitty-specs/archive/` | `ls /home/jones/dev/waaseyaa.org/`; grep for `bimaaji` in any local consumer composer.json; gh CLI search | `research/consumer-signal.md` |
| Node sidecar failure history (exit 254, missing server.js, removal rationale) | 3 | `git log --all --oneline -- packages/bimaaji/mcp/`; `gh issue view 1387`; `gh issue view 1463` | git log with path filter; gh issue view; grep commit messages for `exit 254\|bimaaji-mcp-install` | `research/sidecar-cost.md` |
| Framework PHP-first stance (no Node in core policy) | 1, 2, 3 | `CLAUDE.md` ("PHP-first" and "no Node sidecar in core"); layer architecture table | Read `CLAUDE.md` §Architecture and §Layer Architecture; confirm `packages/mcp/` is PHP-only | `research/mcp-capability.md` |

---

## NFR-003 Coverage

- **(a) Framework code**: "Bimaaji public PHP surface" + "`packages/mcp/` tool-registration capability" + "Framework PHP-first stance" → `research/bimaaji-surface.md`, `research/mcp-capability.md`
- **(b) Consumer signal**: "Consumer signal — Minoo or downstream request" → `research/consumer-signal.md`
- **(c) Maintenance cost**: "Node sidecar failure history" → `research/sidecar-cost.md`

---

## Investigation Order for WP03

1. **Code state first** — `packages/bimaaji/src/` and `packages/mcp/src/` are local, cheap, and definitive for Option 2 readiness.
2. **Consumer signal second** — absence or presence is the highest-weight criterion; a confirmed downstream request changes the analysis materially.
3. **Sidecar cost history third** — git log + issue bodies are contextual; relevant only if Option 3 remains live after steps 1–2.
4. **Framework alignment last** — `CLAUDE.md` confirms stated policy; rarely surprising but needed to cite in the decision document.

---

## Decision Instrument (Phase 4–5 Preview)

WP04 (Analyze) will produce a pros/cons table: **option × criterion**, populated from the four `research/*.md` files. WP05 (Decide) names the selected option and cites the two strongest evidence items. The four `research/*.md` files are the sole inputs to that table — this methodology defines their scope.
