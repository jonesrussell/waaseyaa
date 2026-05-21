# Bimaaji MCP — Decision

**Date**: 2026-05-20
**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**Decision-maker**: Maintainer (Russell Jones)
**Decided by agent**: claude:sonnet:researcher:implementer, surfacing evidence — final ratification by maintainer

## Decision: Option 1 — PHP-only, close #1463 as `not-planned`

No MCP server will be added for bimaaji at this time. Issue #1463 closes as `not-planned`. The existing `DiscoveryTools` and `TraversalTools` in `packages/mcp/` already partially cover bimaaji's graph introspection scope. No M-G.1 mission is required.

## Evidence Summary

| Category | Evidence | Source |
|----------|----------|--------|
| (a) Framework code | As of 2026-05-20, `packages/mcp/` is 100% PHP with no Node runtime. `ToolRegistryInterface` and `ToolExecutorInterface` are both `@api` and support PHP-tool extension; `TraversalTools` and `DiscoveryTools` already partially cover bimaaji's graph-introspection scope. No bimaaji-specific wiring exists, but the registration path is ready. | research/mcp-capability.md |
| (b) Consumer signal | No consumer signal for bimaaji-via-MCP was found as of 2026-05-20. Minoo (`/home/jones/dev/waaseyaa.org/`) has zero references to `waaseyaa/bimaaji` in `composer.json` or PHP source. GitHub issue search returned only maintainer-filed issues #1463 and #1387 — zero consumer-authored requests, zero comments on #1463. | research/consumer-signal.md |
| (c) Maintenance cost | The Node sidecar (`packages/bimaaji/mcp/`) existed for ~1 month (approx April–May 13 2026), was never functional from a consumer perspective, and required a dedicated Minoo WP (WP06 in `upgrade-waaseyaa-alpha-171-01KQTDC2`) to diagnose and work around. Root cause is a Packagist distribution pipeline gap for non-PHP files — unresolved at removal. Removed in commit `46f4c41af` on 2026-05-13. | research/sidecar-cost.md |

## Rationale

Consumer signal is the highest-weight criterion in the decision frame, and it is definitively absent. Minoo — the only known consumer — does not depend on `waaseyaa/bimaaji` at all and actively removed the broken MCP config after #1387. No consumer-authored issue exists requesting bimaaji-via-MCP. Closing #1463 disappoints no active consumer.

The maintenance-cost history of the Node sidecar is concrete and disqualifying for Option 3: one month of silent failure, a consumer-side workaround WP, and an unresolved Packagist distribution pipeline gap. Re-adding the sidecar without fixing that pipeline reproduces the same failure. The estimated cost to fix properly (4–8 hours) is disproportionate to zero consumer demand.

Option 1 dominates across every criterion: zero maintenance cost, no new attack surface, maximally reversible. Option 2 (extend `packages/mcp/`) is architecturally sound and technically ready — `ToolRegistryInterface` and `ToolExecutorInterface` are `@api`, `AgentToolRegistryBridge` is the adapter path, and bimaaji exposes five clean tool candidates — but without consumer demand, the work order is not justified today.

## Options Considered (summary)

| Option | Verdict | Key reason |
|--------|---------|------------|
| 1 — PHP-only, close | **Chosen** | Zero consumer demand; maximally reversible; zero maintenance cost |
| 2 — Extend `packages/mcp/` | Rejected (sleeper) | Architecturally ready but no demand signal to justify the work order now |
| 3 — Restore Node sidecar | Rejected | Known unresolved distribution pipeline failure; no consumer signal; incompatible with PHP-only production stance |

## Follow-up

None. Issue #1463 closes as `not-planned`. No M-G.1 mission required.

WP06 executes: `gh issue close 1463 --reason "not planned" --comment "Decision from mission bimaaji-mcp-strategic-direction-01KS3SZB (WP05): Option 1 — PHP-only, close as not-planned. Zero consumer signal observed as of 2026-05-20; the Node sidecar had an unresolved Packagist distribution pipeline failure; the framework MCP host is PHP-only and architecturally sound for future extension if demand arises. See kitty-specs/bimaaji-mcp-strategic-direction-01KS3SZB/decision.md for full rationale."`

## Notes

- This decision is point-in-time (2026-05-20). If a consumer requests bimaaji graph introspection via MCP, a new mission can re-open the question immediately — Option 2 is technically ready (`ToolRegistryInterface` + `AgentToolRegistryBridge` are `@api`, five tool candidates are identified in `research/bimaaji-surface.md`).
- Option 1 chosen as `not-planned` is a valid, complete mission outcome — not failure. The research mission fulfilled its purpose: the decision is now evidence-backed and documented.
