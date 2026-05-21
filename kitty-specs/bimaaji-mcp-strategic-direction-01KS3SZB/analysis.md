# Bimaaji MCP — Option Analysis

**Date**: 2026-05-20
**Mission**: `bimaaji-mcp-strategic-direction-01KS3SZB`
**WP**: WP04 (Phase 4 — Analyze)
**Purpose**: Evidence-backed pros/cons for WP05 (Decide). This WP does not make the decision.

---

## Pros/Cons Table: 3 Options × 5 Criteria

| Criterion | Option 1: PHP-only, close #1463 | Option 2: Extend `packages/mcp/` | Option 3: Restore Node sidecar |
|-----------|----------------------------------|-----------------------------------|-------------------------------|
| **Consumer signal** (High) | **PRO**: Strongest fit — zero consumer demand observed. Minoo removed the broken `mcpServers.bimaaji` entry and has not re-requested it. Closing #1463 disappoints no active consumer. (consumer-signal.md) | **CON**: No consumer is blocked on this. Could be built proactively, but no demand signal justifies the work order now. (consumer-signal.md) | **CON**: Minoo explicitly removed the sidecar config after exit-254 failure and has not re-requested it. Zero positive signal; the most recent consumer action was removal. (consumer-signal.md, sidecar-cost.md) |
| **Framework readiness** (High) | **NEUTRAL**: No framework work required — immediately actionable. Existing `DiscoveryTools` and `TraversalTools` in `packages/mcp/` partially cover bimaaji scope already. (mcp-capability.md) | **PRO**: `ToolRegistryInterface` and `ToolExecutorInterface` are both `@api` and designed for extension. `AgentToolRegistryBridge` is the adapter path. No new framework APIs needed — this is an implementation gap, not an API gap. Bimaaji surface has 5 well-defined tool candidates. (mcp-capability.md, bimaaji-surface.md) | **CON**: Node sidecar adds a parallel runtime to a 100% PHP MCP host. The existing PHP tool registration path makes the Node approach architecturally redundant and inconsistent with the production stance. (mcp-capability.md) |
| **Maintenance cost** (Medium) | **PRO**: Zero maintenance cost. No new artifact to track, no distribution pipeline to maintain. Revisitable at any time via a new mission. (sidecar-cost.md) | **PRO**: Adds PHP-only code to an existing PHP-only package. No distribution pipeline complexity. PHP artifacts are reliably included in Packagist splits — the class of failure that hit Option 3 does not apply. (sidecar-cost.md, mcp-capability.md) | **CON**: Sidecar was never reliable for ~1 month. Root cause is a Packagist distribution pipeline gap for non-PHP files. Fixing properly requires 4–8 hours. The `bin/install-mcp-npm.php` graceful-skip creates a silent-failure footgun that masked breakage from `composer install`. (sidecar-cost.md) |
| **Security surface** (Medium) | **PRO**: No change to attack surface. PHP-only runtime unchanged. | **PRO**: Adds PHP code only within an already-running PHP process. No new process, no new npm dependency chain. (mcp-capability.md) | **CON**: Introduces Node.js process + npm dependency chain into a PHP-only production environment. Adds npm supply chain as a new attack surface on every consumer install. (decision-frame.md, sidecar-cost.md) |
| **Reversibility** (Low) | **PRO**: Maximally reversible — closing #1463 as `not-planned` does not preclude a new mission if consumer signal arrives. No committed code to maintain or remove. (decision-frame.md) | **NEUTRAL**: Produces committed PHP code that must be maintained or later removed. However, PHP-only additions to `packages/mcp/` are bounded and straightforward to remove. Less reversible than Option 1, more reversible than Option 3. (decision-frame.md) | **CON**: Least reversible in practice — requires solving the distribution pipeline gap that was not solved in the original alpha range. If chosen without fixing that gap, consumers will silently receive a broken artifact again. (sidecar-cost.md) |

---

## Synthesis

### Which option does the evidence most strongly support?

**Option 1 (PHP-only, close #1463)** is the strongest evidence-backed position.

The three most decisive evidence items:

1. **Zero consumer signal** (consumer-signal.md): Minoo — the only known consumer — does not depend on `waaseyaa/bimaaji` at all (`composer.json`: 0 results), has no PHP source referencing bimaaji (grep: 0 matches), and actively removed the broken MCP config. No consumer-authored issue exists requesting bimaaji-via-MCP. Consumer Signal is rated **High weight** in the decision frame — this is the primary criterion, and it points squarely to Option 1.

2. **Concrete sidecar failure history** (sidecar-cost.md): The Node sidecar existed for ~1 month, was never functional from a consumer perspective, and required a dedicated Minoo WP (WP06 in alpha.171 upgrade mission) to diagnose and work around. The root cause is a Packagist distribution pipeline gap for non-PHP files — unresolved to this day. Option 1 eliminates this cost permanently.

3. **Criterion alignment**: On every High-weight criterion, Option 1 is neutral-to-positive with zero cost. On Maintenance cost and Security surface (Medium weight), it is strictly better than Options 2 and 3. Option 1 is dominated by no other option across any criterion.

### Which is the "sleeper" option (technically viable but lacks demand-side signal)?

**Option 2 (extend `packages/mcp/`)** is the sleeper. The framework is genuinely ready — `ToolRegistryInterface` and `ToolExecutorInterface` are `@api`, `AgentToolRegistryBridge` is the adapter, and bimaaji exposes 5 clean graph operation candidates (`bimaaji_graph_section`, `bimaaji_graph_dump`, `bimaaji_mutation_validate`, `bimaaji_dsl_run`, `bimaaji_patch_generate`). The implementation gap is real but bounded. If even one consumer requested bimaaji graph introspection via MCP, Option 2 would become the right answer immediately. The only reason it is not the lead option today is that no such request exists.

### Which is the weakest option?

**Option 3 (restore Node sidecar)** is weakest across every criterion. It has no consumer signal, contradicts the PHP-only production stance, carries a known unfixed distribution pipeline failure, adds an npm supply chain to a PHP-first framework, and is the least reversible. There is no criterion on which Option 3 is preferable to Option 2 — Option 2 achieves the same functional goal (bimaaji tools in MCP) with strictly lower cost, lower risk, and better architectural fit.
