---
work_package_id: WP07
title: McpClientToolSource (remote MCP consumption)
dependencies:
- WP03
requirement_refs:
- FR-021
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T038
- T039
- T040
- T041
history:
- date: '2026-05-18T14:55:10Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/ai-agent/src/Mcp/
execution_mode: code_change
owned_files:
- packages/ai-agent/src/Mcp/**
- packages/config/src/Schema/Ai/McpServersConfig.php
- tests/Integration/PhaseN/AgentRuntime/McpClientToolSourceTest.php
tags: []
---

# WP-07 — `McpClientToolSource` (remote MCP consumption)

## Objective

Adapt remote MCP servers — Streamable HTTP only per **C-008** — into
the local `AgentTool` catalogue. Tools from a remote server are
prefixed with a configured `capability_prefix` (e.g.
`tool.mcp.github.create_issue`) so per-tool capability gating works
uniformly with framework-shipped tools.

## Context

- Spec FRs in scope: **FR-021**.
- Constraints applied: **C-008 (no stdio MCP), C-010 (auth headers via env vars)**.
- Data-model authoritative: [data-model.md](../data-model.md) §"`config.ai.mcp_servers`" + §"Capabilities".
- Doctrine spec sections: §"Remote MCP consumption" in `docs/specs/agent-executor.md`.
- Spec edge case: remote server unavailable at boot → tool drops from catalogue; the agent run proceeds without those tools.

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T038 — Streamable-HTTP MCP client

**Purpose:** Implement a minimal Streamable-HTTP MCP client capable of `initialize`, `tools/list`, and `tools/call`.

**Steps:**
1. Create `packages/ai-agent/src/Mcp/StreamableHttpMcpClient.php`. Inject `HttpClientInterface` (from `packages/http-client`), `?LoggerInterface = null`.
2. Public API:
   - `initialize(string $url, ?string $authHeader): McpServerInfo`
   - `listTools(string $url, ?string $authHeader): array<McpRemoteToolDescriptor>` (the descriptor includes name + inputSchema + description; this becomes one `AgentTool` per descriptor)
   - `callTool(string $url, ?string $authHeader, string $toolName, array $arguments): McpRemoteToolResult`
3. Timeouts: read from `config.ai.providers`-style per-server `timeout_ms` if needed; default 30 s.
4. Implement MCP JSON-RPC envelope correctly (id, jsonrpc, method, params; result vs error).
5. Define the descriptor + result as `final readonly class` DTOs alongside the client.

**Files:**
- `packages/ai-agent/src/Mcp/StreamableHttpMcpClient.php`
- `packages/ai-agent/src/Mcp/McpServerInfo.php`
- `packages/ai-agent/src/Mcp/McpRemoteToolDescriptor.php`
- `packages/ai-agent/src/Mcp/McpRemoteToolResult.php`
- `packages/ai-agent/tests/Unit/Mcp/StreamableHttpMcpClientTest.php` — mock HTTP client.

**Validation:**
- [ ] Initialize round-trip parses correctly.
- [ ] `tools/list` produces descriptors.
- [ ] Error path: server returns 5xx → client throws specific exception (`McpServerUnavailableException`).

---

## Subtask T039 — `config.ai.mcp_servers` config entity

**Purpose:** First-class CMI entity for remote MCP server configuration.

**Steps:**
1. Create `packages/config/src/Schema/Ai/McpServersConfig.php` defining the list-shape per data-model § `config.ai.mcp_servers`:
   - `alias: string`, `url: string`, `auth_header_env_var: string` (or empty), `enabled: bool`, `capability_prefix: string`.
2. Wire the schema into `packages/config`'s config-entity registry (mirror `ProvidersConfig` registered in WP-04).
3. Add an empty default to `defaults/ai.yaml` (a `mcp_servers: []` entry under the `ai` namespace).
4. Auth-header secret indirection per **C-010**: `auth_header_env_var` carries a name; the client reads `getenv($name)` at call time.

**Files:**
- `packages/config/src/Schema/Ai/McpServersConfig.php`
- `defaults/ai.yaml` — extend (this file was introduced in WP-04; this WP appends a `mcp_servers: []` entry).

**Note on cross-WP file edit:** `defaults/ai.yaml` was introduced by WP-04. This WP appends a new top-level key. Reviewers should confirm the edit is additive only.

**Validation:**
- [ ] `bin/check-no-secrets` exits 0.
- [ ] `bin/check-ingestion-defaults` exits 0.
- [ ] Reading `config.ai.mcp_servers` returns an empty list by default.

---

## Subtask T040 — Capability-prefix mapping + tool registration

**Purpose:** Bind remote tools into the local `AgentTool` registry.

**Steps:**
1. Create `packages/ai-agent/src/Mcp/McpClientToolSource.php`:
   - Constructor injects `StreamableHttpMcpClient`, `ToolRegistryInterface` (the `AttributeToolRegistry` from WP-01), `McpServersConfig` (resolved via the config service), `?LoggerInterface = null`.
   - Public method `bootstrap(): void`:
     - For each enabled server: call `client->initialize()` + `client->listTools()`.
     - Wrap each remote tool descriptor as an `AgentTool` with:
       - `name = "{$alias}.{$descriptor->name}"` (e.g. `github.create_issue`)
       - `capability = "{$capabilityPrefix}.{$descriptor->name}"` (e.g. `tool.mcp.github.create_issue`)
       - `destructive` defaults to `true` for any remote tool unless the descriptor exposes a `destructive: false` hint (conservative default — remote authors must opt in to non-destructive).
       - `dryRunSupported = false` (remote dry-run is not specced for v1).
       - `category = "mcp.{$alias}"`.
       - `inputSchema` = descriptor's schema as-is.
       - `impl` = an inline `AgentToolInterface` implementation that calls `client->callTool($url, $authHeader, $descriptor->name, $arguments)` and wraps the result.
     - Register each via `ToolRegistryInterface::register(AgentTool $tool)`.
   - On `McpServerUnavailableException` for a given server: log a warning and skip (graceful degrade — spec edge case).
2. Register the per-server `tool.mcp.<alias>.<name>` capabilities so they're grantable. Hook into `AgentCapabilities::seed()` (WP-02) — but **WP-02 owns that file**; here we extend via a separate `McpCapabilitiesSource` class that the access subsystem picks up at boot.
3. Wire `McpClientToolSource::bootstrap()` to run on kernel boot (service provider's `boot()`), inside a try-catch so failures don't break the rest of the kernel.

**Files:**
- `packages/ai-agent/src/Mcp/McpClientToolSource.php`
- `packages/ai-agent/src/Mcp/McpCapabilitiesSource.php`
- `packages/ai-agent/src/Mcp/McpServiceProvider.php` (NEW, bound to `extra.waaseyaa.providers`)
- `packages/ai-agent/tests/Unit/Mcp/McpClientToolSourceTest.php`

**Validation:**
- [ ] Configured server → tools registered under the prefix.
- [ ] Unavailable server → registry unchanged (graceful degrade).
- [ ] Capability collision between servers (same alias) is impossible because alias-prefix uniqueness is enforced.

---

## Subtask T041 — Stub-server integration test

**Purpose:** End-to-end verification with a real (stub) MCP server.

**Steps:**
1. Implement a stub MCP server inside the test fixture: a PHP class that responds to JSON-RPC over an in-process HTTP transport (use a `MockHttpClient` or similar from `packages/http-client/testing`).
2. Test `tests/Integration/PhaseN/AgentRuntime/McpClientToolSourceTest.php`:
   - Register a fake server with `alias='stub'`, two tools (`echo`, `add`), `capability_prefix='tool.mcp.stub'`.
   - Boot the kernel.
   - Assert `AttributeToolRegistry::all()` contains `stub.echo` and `stub.add`.
   - Call `stub.echo` via an `AgentRunService::runInline()` ad-hoc bundle and assert the response.

**Files:**
- `tests/Integration/PhaseN/AgentRuntime/McpClientToolSourceTest.php`
- (test fixture stub server lives inside the test file or under `tests/Integration/PhaseN/AgentRuntime/Fixture/`)

**Validation:**
- [ ] End-to-end test green.

---

## Definition of Done

- [ ] T038..T041 checkboxes flipped.
- [ ] Streamable-HTTP MCP client speaks `initialize`, `tools/list`, `tools/call`.
- [ ] Remote tools appear in registry under the configured prefix.
- [ ] Unavailable server: graceful degrade.
- [ ] Stub-server integration test green.
- [ ] All gates green.

## Risks & mitigations

1. **Remote tool destructive flag drift.** *Mitigation:* default to destructive=true; remote authors must opt out.
2. **Boot-time failures cascade.** *Mitigation:* try-catch around `bootstrap()`; log + continue.
3. **Cross-WP additive edit to `defaults/ai.yaml`.** *Mitigation:* keep diff to a single new top-level `mcp_servers: []` key.

## Reviewer guidance

- Verify the prefix → capability mapping at registration time.
- Inspect the stub fixture and confirm it speaks proper MCP JSON-RPC.
- Ensure no stdio transport is introduced (C-008).

## Implementation command

```
spec-kitty agent action implement WP07 --agent <name>
```
