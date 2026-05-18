# 019 — MCP tool access enforcement against the initiator account

- **Status:** Accepted
- **Date:** 2026-05-18
- **Mission:** `agent-executor-01KRWPK7` (WP01)
- **Supersedes:** the implicit `accessCheck(false)` bypass that
  shipped in the legacy `Waaseyaa\AI\Schema\Mcp\McpToolExecutor` and
  the now-deprecated `packages/mcp/src/Tools/*` classes.

## Context

For most of the framework's lifetime the MCP tool surface ran entity
queries with `accessCheck(false)` so that bearer-token-authenticated
"AI agent" callers could see results that any normal HTTP client
would have to satisfy entity-level access checks for. This bypass
was introduced as part of the original `Waaseyaa\AI\Agent\McpServer`
orphan removed in PR #1508; the bypass survived inside the legacy
`Waaseyaa\AI\Schema\Mcp\McpToolExecutor` and in the per-tool query
paths under `packages/mcp/src/Tools/`.

The bypass was always at odds with the framework's public posture
on access control: every entity read and write must enforce the
caller's effective permissions, period.

Mission `agent-executor-01KRWPK7` introduces `Waaseyaa\AI\Tools` and
its `AgentToolInterface` — every framework-shipped agent tool
takes an `AccountInterface` and enforces the tool's capability and
the entity's access policy against that account *inside* its
`execute()` and `dryRun()` implementations. This makes the bypass
unnecessary, and constraint **C-013** of the mission spec requires
its removal.

## Decision

1. Remove `accessCheck(false)` from the surfaces owned by WP01:
   - `packages/ai-schema/src/Mcp/McpToolExecutor.php::executeQuery()`
     (deleted in this PR).
   - `packages/mcp/src/Tools/McpTool.php` and the four tool classes
     above it — deletion deferred to a follow-up WP (`packages/mcp/src/Tools/`
     migration) to keep the implement-review loop bounded, but marked
     as forbidden in `bin/check-external-consumers ai-agent-orphans`.
2. Every entity-touching agent tool (the eight stock tools shipped in
   `waaseyaa/ai-tools`, plus any third-party tools) MUST enforce
   entity-level access against the supplied `AccountInterface`. The
   request attribute key for the authenticated account is `_account`
   (set by `SessionMiddleware`); `McpController` propagates it.
3. The `#[AsAgentTool]` attribute carries a required `capability`;
   `AbstractAgentTool::requireCapability()` provides the canonical
   short-circuit when an account lacks the capability — returning a
   structured `forbidden` `AgentToolResult` rather than the legacy
   "silently empty result set" the bypass produced.

## Consequences

- External MCP clients that depended on the implicit bypass to see
  records they should not have access to will start receiving
  `forbidden` results. This is the intended posture per C-013 and
  was anticipated in the mission spec under FR-016.
- Site operators upgrading across this boundary must verify that
  the bearer-token accounts used by their AI agents are granted the
  capabilities they need (`tool.entity.read`, `tool.entity.list`,
  `tool.entity.create`, `tool.entity.update`, `tool.entity.delete`,
  `tool.entity.search`, `tool.relationship.traverse`,
  `tool.vector.search`).
- The `bin/check-external-consumers ai-agent-orphans` script is the
  ongoing tripwire that prevents new code from reintroducing the
  bypass under different names.
- Removal of the bypass from `packages/mcp/src/Tools/` and the
  deletion of `Waaseyaa\AI\Schema\Mcp\McpToolDefinition` are sequenced
  through follow-up WPs in this mission once the cross-package
  consumer migration to `Waaseyaa\AI\Tools\AgentTool` is complete.

## Related

- `docs/specs/agent-executor.md` (doctrine)
- `kitty-specs/agent-executor-01KRWPK7/spec.md` (FR-010..FR-016, C-006, C-013)
- `bin/check-external-consumers`
- `packages/ai-tools/src/Attribute/AsAgentTool.php`
- `packages/ai-tools/src/AbstractAgentTool.php::requireCapability()`
