# Bimaaji MCP — Decision Frame

**Date**: 2026-05-20
**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Decision-maker**: Maintainer (Russell Jones) — sole decision-maker; this mission surfaces evidence, not mandates.

---

## Decision Space

| ID | Option | Summary |
|----|--------|---------|
| 1 | **PHP-only, close #1463** | No MCP server for bimaaji. Agents reach graph operations via the HTTP API or via `packages/mcp/` registering bimaaji tools if/when ready. Close #1463 as `not-planned`. |
| 2 | **Extend `packages/mcp/`** | Add bimaaji-specific tool registrations to the existing PHP-side MCP host. No Node sidecar. Lowest blast radius; requires `packages/mcp/` to already support PHP-tool registration. If chosen, file M-G.1. |
| 3 | **Restore Node sidecar** | Re-add `packages/bimaaji/mcp/server.js`, fix the install path that caused exit-254 in Minoo, document the consumer install flow. Highest cost; previously failed. If chosen, file M-G.1 with prior-failure diagnosis. |

**Options are exhaustive.** A scan of #1463 and #1387 confirms no 4th candidate:

- "Publish bimaaji as a standalone npm MCP server" is a variant of Option 3 (same failure surface, same Node dependency, decoupled packaging only).
- "Document how to register bimaaji tools in `packages/mcp/` without shipping the registration code" is a documentation-only sub-variant of Option 2 — indistinct enough to subsume.
- "Do nothing + defer indefinitely" is not distinct from Option 1 in practice; closing #1463 as `not-planned` is the decision, deferral without closure is not.

---

## Decision Criteria

| Criterion | Weight | Rationale |
|-----------|--------|-----------|
| Consumer signal | **High** | No downstream ask = strong lean toward Option 1. A confirmed consumer request (e.g. Minoo ticket) would be the primary reason to choose Option 2 or 3. |
| Framework readiness | **High** | Does `packages/mcp/` support PHP-tool registration today? If not, Option 2 requires predecessor framework work before bimaaji tools can be wired — raising its effective cost to near Option 3. |
| Maintenance cost | **Medium** | The Node sidecar history is concrete: exit-254, absent artifact at runtime. Option 3 repeats that surface. Option 2 avoids it. Option 1 eliminates it entirely. |
| Security surface | **Medium** | Option 3 adds a Node process + npm dependency chain as a new attack surface. Relevant for a framework that is otherwise PHP-only in production. |
| Reversibility | **Low** | Option 1 can be revisited with a new mission if consumer signal arrives. Options 2 and 3 produce committed code that must be maintained or later removed. Option 1 is the most reversible. |

---

## Framing Notes

- **Option 1 (PHP-only, close) is a valid and complete outcome.** "We considered it carefully and decided not to ship it" is a ship-worthy result of a research mission — not failure.
- The decision is point-in-time. If a downstream consumer requests bimaaji-via-MCP after this mission closes, a new strategic mission can re-open the question with fresh evidence.
- No production code is produced by this mission. All implementation, if any, is M-G.1's territory.
- Decision deadline: end of M-G mission (~4 hours bounded effort per NFR-001).
