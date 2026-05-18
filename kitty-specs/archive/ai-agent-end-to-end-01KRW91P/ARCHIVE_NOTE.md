# Archived: ai-agent-end-to-end-01KRW91P

**Archived:** 2026-05-18
**Outcome:** Split — `McpServer` half shipped via PR #1508 (closes #1498);
`AgentExecutor` half deferred to a future design-led mission.
**Spec status:** WP outline obsolete at filing.

## Why split, not run as written

The original spec bundled two surfaces under one "completion" mission:

- **`Waaseyaa\AI\Agent\McpServer`** — `tools/list` + `tools/call` JSON-RPC
  adapter with no transport. The production MCP path is
  `Waaseyaa\Mcp\McpServerCard` in `packages/mcp/`. Straight orphan cleanup,
  shaped exactly like #1497 broadcasting. Shipped in PR #1508.

- **`Waaseyaa\AI\Agent\AgentExecutor`** — multi-turn agent orchestrator
  with no consumer. There is no parallel production path to redirect to;
  the gap is genuine. The spec itself acknowledged this: "we must DESIGN
  what running an agent looks like in Waaseyaa, not just wire existing
  parts."

That second half is design work, not wiring. WPs that assume the consumer
shape are putting answers in the wrong place. The mission re-shapes as a
brainstorming pass producing a one-page consumer proposal — CLI vs HTTP,
tool permissions, LLM provider config, sandboxed agent identity — and a
follow-up implementation mission scoped to whichever shape is chosen.

## What shipped (PR #1508)

- Deleted `packages/ai-agent/src/McpServer.php` + its unit test.
- Dropped 2 `McpServer` entries from `phpstan-dead-code-baseline.neon`.
- Stripped McpServer references from
  `tests/Integration/Phase8/AIFullStackIntegrationTest.php` and
  `tests/Integration/Phase8/AgentExecutionIntegrationTest.php`.
- Updated `docs/specs/ai-integration.md`,
  `skills/waaseyaa/ai-integration/SKILL.md`, and `packages/mcp/README.md`.

## What's still open

Issue #1496 (the AgentExecutor decision issue) has been reopened with a
redirect comment outlining the design-question shape. When a consumer
proposal exists, file a fresh "agent consumer v1" mission targeting that
shape. If none emerges, delete `AgentExecutor` + supporting cast
(`AgentContext`, `AgentAction`, `AgentResult`, `AgentInterface`,
`ToolRegistry`, `Provider/Anthropic`, `Provider/Ollama`) as orphans.

## Lessons (for the audit-staleness memory)

The mission spec acknowledged the design gap in prose but still committed
to a six-WP wiring outline. The pre-filing grep recommended by
[[feedback_completion_mission_pre_grep]] would have surfaced that
`McpServer` was a small, clean delete and `AgentExecutor` required
design first — and the mission would have been filed as two narrower
work items from the start.
