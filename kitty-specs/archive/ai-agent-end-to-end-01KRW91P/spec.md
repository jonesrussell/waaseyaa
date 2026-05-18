# AI Agent End-to-End

**Mission:** `ai-agent-end-to-end-01KRW91P`
**Status:** Spec
**Target branch:** `main`
**Closes:** #1496, #1498

## Why this mission exists

`packages/ai-agent/src/` is a coherent scaffold with no consumer:

- `AgentExecutor` — multi-turn agent orchestrator (provider + tool registry + context). 6 unused methods in the dead-code baseline.
- `McpServer` — Waaseyaa-side MCP bridge that would let external MCP clients (Claude Code, etc.) call into the framework. 2 unused methods.
- Supporting cast (AgentContext, AgentAction, AgentResult, AgentInterface, ToolRegistry, Provider/Anthropic, Provider/Ollama) — all uncalled outside the package's own tests.

Specs reference these classes (`docs/specs/ai-integration.md`, `docs/specs/mcp-endpoint.md`) but the framework ships no path for a user to actually run an agent. Owner directive: complete the wiring with a real consumer and a working MCP endpoint. No deletion.

This is the largest of the three subsystem-completion missions because the consumer's shape is not predetermined — we must DESIGN what running an agent looks like in Waaseyaa, not just wire existing parts.

## User scenarios

### Primary flow: a developer runs an agent from the CLI

1. User runs `bin/waaseyaa ai:run "summarize the most recent 5 articles"`.
2. The command boots the kernel, builds an `AgentContext` with: the user prompt as the initial message, the default system prompt, the default tool registry (entity read-only tools), the configured LLM provider (Anthropic from `ANTHROPIC_API_KEY` env).
3. `AgentExecutor::execute($context)` runs the multi-turn loop: send to provider → if response includes tool calls → execute tools → send results back → repeat until response is text-only OR max iterations hit.
4. The command prints the final assistant text to stdout. Exits 0 on success, non-zero on `MaxIterationsException` or provider error.

### Secondary flow: an external MCP client calls into Waaseyaa

1. External client (Claude Code, Claude Desktop) opens an MCP connection to `POST /mcp/agent`.
2. The MCP server announces the agent's tools (entity read, entity list, etc.) via the MCP `tools/list` method.
3. Client calls `tools/call` with `name: "entity.read"` + arguments.
4. `McpServer` dispatches to the matching tool in `ToolRegistry`, returns the result via MCP response envelope.

### Edge cases

- No `ANTHROPIC_API_KEY` set: `bin/waaseyaa ai:run` returns exit 2 with a clear error message ("set ANTHROPIC_API_KEY or pass --provider=ollama").
- Provider rate limit hit: `RateLimitException` raised; CLI prints the retry-after message; exit 3.
- Max iterations (default 10): `MaxIterationsException` raised; CLI prints partial transcript; exit 4.
- Empty prompt: `bin/waaseyaa ai:run ""` returns exit 1 with usage.
- MCP endpoint without authentication: 401. (Same auth gate as other API routes.)
- Tool call with invalid arguments: tool returns error result; agent receives it and may retry or stop.

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | A CLI command (`bin/waaseyaa ai:run <prompt>`) executes a single agent run using the default provider and tool registry. |
| FR-002 | Mandatory | `AgentExecutor::execute(AgentContext)` runs a multi-turn loop, calling the configured `ProviderInterface` and any `tools/use` calls until the response is text-only OR max iterations (configurable, default 10) is hit. |
| FR-003 | Mandatory | The default tool registry MUST include at least one read-only entity tool (`entity.read` or `entity.list`) wired through `EntityTypeManager`. |
| FR-004 | Mandatory | An MCP HTTP endpoint (`POST /mcp/agent` or similar — exact path decided in plan) MUST expose `tools/list` and `tools/call` per the MCP protocol. |
| FR-005 | Mandatory | `McpServer::handleRequest()` MUST validate the MCP envelope, dispatch to the matching tool, and return a JSON-RPC-shaped response. |
| FR-006 | Mandatory | At least one provider implementation MUST be fully wired (Anthropic via API key OR Ollama via local URL — choose in plan; recommend Anthropic since it's the existing Provider/AnthropicProvider). |
| FR-007 | Mandatory | The CLI command MUST stream assistant tokens to stdout as they arrive (use existing `StreamChunk` infrastructure from `Provider/`), not buffer the whole response. |
| FR-008 | Mandatory | The agent loop MUST raise `MaxIterationsException` after the configured limit; partial transcripts MUST be accessible from the exception. |
| FR-009 | Mandatory | All currently-baselined `AgentExecutor` and `McpServer` methods MUST have at least one production caller after this mission lands. |
| FR-010 | Mandatory | Audit logging: each agent run MUST emit one `AgentAuditLog` entry via the existing event/log pipeline (consumed by ai-observability). |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | First-token latency (CLI: time from command invocation to first byte on stdout) MUST be under 3 seconds when the provider is Anthropic with a warm connection. (Best-effort; depends on upstream.) |
| NFR-002 | Mandatory | The agent loop MUST respect a configurable wall-clock timeout (default 60 seconds). |
| NFR-003 | Mandatory | The MCP endpoint MUST respond to `tools/list` in under 100 ms p95 (in-process, no external calls). |
| NFR-004 | Mandatory | The CLI command MUST refuse to run if `APP_ENV=production` AND `WAASEYAA_AI_ALLOW_PRODUCTION` is not set (safeguard against accidental LLM spend in prod). |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | `packages/ai-agent/` cannot import from any layer higher than L5 (it is L5 itself per the layer table). |
| C-002 | Mandatory | The MCP endpoint route MUST register via `RouteProviderInterface` to keep auth/middleware composition working. |
| C-003 | Mandatory | LLM provider configuration MUST come from environment variables OR the `config.ai` config entity — never hard-coded. |
| C-004 | Mandatory | The dead-code baseline must drop by exactly the 6 `AgentExecutor` entries + 2 `McpServer` entries (= 8 total) currently in `phpstan-dead-code-baseline.neon`. No new entries may appear. |
| C-005 | Mandatory | The CLI command MUST handle missing API keys gracefully (exit 2 with actionable message), never throw an unhandled exception. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | A developer can run `bin/waaseyaa ai:run "list 3 recent articles"` and receive a coherent response (in a test using a fake provider). | `tests/Integration/Phase??/AiAgentCliE2ETest.php` passes. |
| SC-002 | An MCP `tools/list` request returns the registered tools as JSON-RPC. | `tests/Integration/Phase??/AiAgentMcpE2ETest.php` passes with `tools/list` + `tools/call` round-trip. |
| SC-003 | `composer check-dead-code` reports zero `AgentExecutor` and `McpServer` baseline entries after merge. | `grep -c -E 'AgentExecutor\|McpServer' phpstan-dead-code-baseline.neon` returns 0 for both. |
| SC-004 | `composer verify` is green on the merge commit. | CI status check `verify` passes. |
| SC-005 | Specs document the agent contract. | `docs/specs/ai-integration.md` updated; `docs/specs/mcp-endpoint.md` updated; new `docs/cookbook/running-an-agent.md` documents the CLI usage. |
| SC-006 | Issues #1496 and #1498 close via `Closes` footer in the merge commit. | GitHub auto-closes both on merge. |

## Key entities

| Entity | Role | Net change |
|---|---|---|
| `AgentExecutor` | Existing. Implementation completed where stubbed; loop logic wired to ProviderInterface + ToolRegistry. | edit |
| `McpServer` (in ai-agent) | Existing. Implementation completed; handles `tools/list` and `tools/call`. | edit |
| `AgentContext` | Existing value object. Possibly extended with a `timeout_seconds` field. | edit |
| `AgentResult` | Existing value object. Carries final response + transcript + token usage. | edit (maybe) |
| `ToolRegistry` | Existing. Default-tool seed populated (entity.read, entity.list). | edit |
| `AiAgentRouteProvider` (new) | Route provider implementing `RouteProviderInterface`, registers the MCP endpoint. | +1 file |
| `AiRunCommand` (new) | CLI command. Reads prompt arg, builds context, runs executor, streams stdout. | +1 file |
| `AiAgentServiceProvider` | Existing. Binds default tool registry, default provider (from env), exposes McpServer. | edit |
| Entity tools (new) | `EntityReadTool`, `EntityListTool` implementing the tool interface. | +2 files |
| `AgentAuditLog` | Existing entity. Now actually written to during agent runs. | (consumer; no class change) |

## Assumptions

- `Provider\AnthropicProvider` exists and works (verified — landed in 2026-03-23).
- `ToolRegistry` already has a registration interface; we add tools to it rather than redesigning.
- The MCP protocol JSON-RPC envelope can be implemented in PHP without an external SDK (the protocol is straightforward).
- The CLI command can be added via the existing `CommandRegistry` discovery (`#[AsCommand]` attribute pattern, used by other ai-agent CLI commands like `MakeJobHandler`).
- For tests, a `FakeProvider` test double is acceptable (does not call the real Anthropic API).
- `ANTHROPIC_API_KEY` env var is the conventional name.

## Out of scope

- A polished UI for agent interactions (admin SPA integration is a follow-up).
- Multi-agent coordination, parallel tool calls beyond single-turn.
- Long-running background agent jobs (synchronous CLI only in v1.0).
- Cost tracking beyond the existing `AgentAuditLog` entry (ai-observability mission territory).
- Per-tool authorization (anyone authenticated to call the MCP endpoint can call any registered tool).
- Tool error retry policies beyond the loop returning the error to the LLM.
- Local-model providers (Ollama implementation is deferred unless trivially included).

## WP outline (for /spec-kitty.plan)

- **WP01 — AgentExecutor loop:** Complete the multi-turn loop. ProviderInterface call → tool dispatch → result feed → iterate. Unit test with FakeProvider.
- **WP02 — Default tools:** `EntityReadTool` + `EntityListTool` implementing the tool contract. Register into ToolRegistry via service provider.
- **WP03 — CLI command:** `AiRunCommand` discoverable via CommandRegistry. Wires AgentContext from CLI args. Streams stdout. Handles env-var checks.
- **WP04 — McpServer impl:** Implement `handleRequest()` parsing JSON-RPC, dispatching to ToolRegistry. Unit tests for `tools/list` + `tools/call`.
- **WP05 — Routing:** `AiAgentRouteProvider` implementing `RouteProviderInterface`. Wires MCP endpoint. DI binding.
- **WP06 — Integration tests:** CLI E2E with FakeProvider; MCP HTTP E2E with `tools/list` + `tools/call` round-trip.
- **WP07 — Wrap-up:** Spec updates (ai-integration.md, mcp-endpoint.md, new running-an-agent.md cookbook). Baseline regen confirming 8 entries dropped. CHANGELOG entry. Full `composer verify`.

## References

- Issues: https://github.com/waaseyaa/framework/issues/1496 and https://github.com/waaseyaa/framework/issues/1498
- Existing scaffolding: `packages/ai-agent/src/AgentExecutor.php`, `packages/ai-agent/src/McpServer.php`
- Existing Anthropic provider: `packages/ai-agent/src/Provider/AnthropicProvider.php` (verify path)
- ToolRegistry: `packages/ai-agent/src/ToolRegistry.php`
- Distinct LIVE MCP infrastructure (not to be confused): `packages/mcp/` — McpServerCard, McpEndpoint
- Dead-code audit: `docs/audits/2026-05-17-dead-code-baseline-audit.md`
