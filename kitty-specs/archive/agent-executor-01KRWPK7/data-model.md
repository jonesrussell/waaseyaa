# Phase 1 Data Model

This document captures the entity schemas, value-object shapes, config
entity shapes, indexes, state transitions, and column-type decisions
for the Agent Executor v1 mission. Field-level specifications here
are authoritative for `EntityRepository` wiring and migration scripts
in WP-02.

---

## Entities

### `AgentRun`

**Aggregate root** for `AgentAuditLog` rows.

**Entity type ID:** `agent_run`
**Class:** `Waaseyaa\AI\Agent\Entity\AgentRun`
**Storage:** SQL via `SqlEntityStorage`. `EntityRepository` pattern.
**Access policy:** `Waaseyaa\AI\Agent\Access\AgentRunAccessPolicy`
(initiator owns row; `agent.run.bypass_ownership` capability holders
see all rows).

| Column | Type | DBAL Type | Nullable | Default | Notes |
|---|---|---|---|---|---|
| `id` | uuid | `Types::GUID` | NO | — | Primary key. |
| `account_id` | bigint | `Types::BIGINT` | NO | — | Initiator account. |
| `agent_definition_id` | text | `Types::STRING(255)` | YES | NULL | `AgentDefinition.id` if a named agent; NULL for ad-hoc bundles. |
| `bundle_json` | text | `Types::TEXT` | NO | — | Frozen snapshot of resolved bundle (prompt, tools, model, system, max_iterations). |
| `status` | enum | `Types::STRING(32)` | NO | `queued` | Values per state machine below. |
| `destructive_approval` | enum | `Types::STRING(16)` | NO | `none` | `none` / `all` / `interactive`. |
| `pending_approval_call_id` | text | `Types::STRING(64)` | YES | NULL | Set when `status='awaiting_approval'`. |
| `prompt` | text | `Types::TEXT` | NO | — | Resolved user prompt. |
| `response` | text | `Types::TEXT` | YES | NULL | Final LLM response, populated on `completed`. |
| `transcript_json` | text | `Types::TEXT` | NO | `'[]'` | Conversation snapshot truncated at `config.ai.transcript_max_bytes`. Overflow marker `[truncated]` inserted by application layer. |
| `token_usage_in` | int | `Types::INTEGER` | NO | `0` | Sum across provider calls. |
| `token_usage_out` | int | `Types::INTEGER` | NO | `0` | Sum across provider calls. |
| `cost_cents` | int | `Types::INTEGER` | YES | NULL | Derived from `ModelPriceTable`. NULL when model unknown. |
| `tool_call_count` | int | `Types::INTEGER` | NO | `0` | Total tool invocations. |
| `queued_at` | datetime | `Types::DATETIMETZ_IMMUTABLE` | NO | — | Persisted at enqueue. |
| `started_at` | datetime | `Types::DATETIMETZ_IMMUTABLE` | YES | NULL | Worker pickup time. |
| `finished_at` | datetime | `Types::DATETIMETZ_IMMUTABLE` | YES | NULL | Terminal-status timestamp. |
| `error_code` | text | `Types::STRING(64)` | YES | NULL | Per error taxonomy. |
| `error_message` | text | `Types::TEXT` | YES | NULL | Human-readable detail. |

**Indexes:**
- Primary: `id`
- `idx_agent_run_status_queued_at` on `(status, queued_at)` — reaper + queue inspection.
- `idx_agent_run_account_queued_at` on `(account_id, queued_at DESC)` — user history.
- `idx_agent_run_status_started_at` on `(status, started_at)` — reaper filter when scanning `status='running'`.

**State machine:**

```
queued ──► running ──► completed
   │          │
   │          ├─► failed
   │          ├─► awaiting_approval ──► running  (approved)
   │          │                    ╰─► failed   (denied/timeout)
   │          ╰─► cancelling ──► cancelled
   ╰─► cancelled  (cancel before worker pickup; DELETE writes
                    `cancelling` and the handler short-circuits)
```

**Invariants:**
- Terminal statuses (`completed`, `failed`, `cancelled`) cannot regress.
- The reaper SHALL NOT transition a terminal row.
- `finished_at` is non-NULL iff status is terminal.
- `pending_approval_call_id` is non-NULL iff status is `awaiting_approval`.

---

### `AgentAuditLog`

**Append-only event log.** Replaces the in-memory list inside
`AgentExecutor`.

**Entity type ID:** `agent_audit_log`
**Class:** `Waaseyaa\AI\Agent\Entity\AgentAuditLog`
**Storage:** SQL via `SqlEntityStorage`. `EntityRepository` pattern.
**Access policy:** Same ownership semantics as `AgentRun`: visible to
the run's initiator and to `agent.run.bypass_ownership` holders.

| Column | Type | DBAL Type | Nullable | Default | Notes |
|---|---|---|---|---|---|
| `id` | uuid | `Types::GUID` | NO | — | Primary key (event id). |
| `run_id` | uuid | `Types::GUID` | NO | — | FK to `agent_run.id`. |
| `iteration` | int | `Types::INTEGER` | NO | — | Loop iteration number (1-based; `0` for pre-loop setup). |
| `event_type` | enum | `Types::STRING(32)` | NO | — | Values: `iteration_start`, `tool_call`, `tool_result`, `provider_call`, `approval_required`, `approval_granted`, `approval_denied`, `error`. |
| `tool_name` | text | `Types::STRING(128)` | YES | NULL | For tool events. |
| `tool_arguments_json` | text | `Types::TEXT` | YES | NULL | Output of `AgentToolInterface::argumentsForAudit()`. |
| `tool_result_summary` | text | `Types::TEXT` | YES | NULL | Short summary; full result lives in `AgentRun.transcript_json`. |
| `success` | bool | `Types::BOOLEAN` | NO | — | Event outcome. |
| `duration_ms` | int | `Types::INTEGER` | YES | NULL | For timed events. |
| `occurred_at` | datetime | `Types::DATETIMETZ_IMMUTABLE` | NO | — | Event time. |

**Indexes:**
- Primary: `id`
- `idx_agent_audit_run_occurred_at` on `(run_id, occurred_at)` — replay log per run.

**Invariants (per spec Audit Invariants):**
1. Every provider call → exactly one `provider_call` row.
2. Every tool call → exactly one `tool_call` row AND one of (`tool_result`, `error`).
3. Every interactive approval → exactly one `approval_required` and exactly one of (`approval_granted`, `approval_denied`).
4. Append-only outside `ai:purge-runs`.

---

## Value objects

### `AgentDefinition` (in `packages/ai-agent`)

`final readonly class`. Discovered by `PackageManifestCompiler` via
`#[AsAgentDefinition]` attribute.

| Field | Type | Notes |
|---|---|---|
| `id` | `string` | Unique identifier, e.g. `authoring_assist`. |
| `label` | `string` | Short display label. |
| `description` | `string` | One-paragraph description. |
| `prompt` | `string` | User-facing prompt template. |
| `system` | `string` | System message. |
| `tools` | `string[]` | Whitelist of tool names. |
| `model` | `string` | Fully-qualified ref, e.g. `anthropic:claude-sonnet-4-6`. |
| `maxIterations` | `int` | Default 10. |
| `destructiveDefault` | `?HitlMode` | Per-agent default mode. NULL means inherit request. |
| `requiresCapability` | `?string` | Optional capability gate beyond `agent.run`. |

### `AgentTool` (in `packages/ai-tools`)

`final readonly class`. Runtime view of a tool class registered via
`#[AsAgentTool]`.

| Field | Type | Notes |
|---|---|---|
| `name` | `string` | Unique within registry, e.g. `entity.read`. |
| `capability` | `string` | Required capability on initiator account. |
| `destructive` | `bool` | Triggers HITL gate when true. |
| `dryRunSupported` | `bool` | Whether `dryRun()` is implemented. |
| `category` | `string` | Grouping (`entity`, `relationship`, `vector`, `mcp.<server>`). |
| `inputSchema` | `array` | JSON Schema draft 2020-12. |
| `impl` | `AgentToolInterface` | Injected concrete implementation. |

### `AgentToolResult` (in `packages/ai-tools`)

| Field | Type | Notes |
|---|---|---|
| `isError` | `bool` | True for failure or refusal. |
| `content` | `array` | List of `{type: string, text: string}` items per MCP convention. |
| `summary` | `?string` | One-line summary for audit log. |

### `HitlMode` (in `packages/ai-agent`)

PHP 8.1+ enum.

| Case | String value |
|---|---|
| `None` | `none` |
| `All` | `all` |
| `Interactive` | `interactive` |

### `RunStatus` (in `packages/ai-agent`)

PHP 8.1+ enum (`int`-backed for SQL ordering, or `string`-backed for
readability — pick `string` per Waaseyaa convention).

| Case | String value |
|---|---|
| `Queued` | `queued` |
| `Running` | `running` |
| `AwaitingApproval` | `awaiting_approval` |
| `Cancelling` | `cancelling` |
| `Cancelled` | `cancelled` |
| `Completed` | `completed` |
| `Failed` | `failed` |

### `EventType` (in `packages/ai-agent`)

| Case | String value |
|---|---|
| `IterationStart` | `iteration_start` |
| `ToolCall` | `tool_call` |
| `ToolResult` | `tool_result` |
| `ProviderCall` | `provider_call` |
| `ApprovalRequired` | `approval_required` |
| `ApprovalGranted` | `approval_granted` |
| `ApprovalDenied` | `approval_denied` |
| `Error` | `error` |

---

## Config entities

All live under the `config.ai.*` namespace, managed by
`packages/config`. Secrets are env-only; entity rows carry env-var
*names* via the `*_env_var` indirection.

### `config.ai.providers`

**Cardinality:** list. Each item:

| Field | Type | Notes |
|---|---|---|
| `id` | `string` | Stable handle for `model` refs (e.g. `anthropic`). |
| `type` | `string` | `anthropic`, `openai`, `null` (NullLlmProvider). |
| `model_default` | `string` | Default model for this provider. |
| `timeout_ms` | `int` | Per-call HTTP timeout. |
| `rate_limit_per_min` | `int` | App-side rate limit (in addition to provider's). |
| `api_key_env_var` | `string` | Env var name carrying the API key. |

### `config.ai.mcp_servers`

**Cardinality:** list. Each item:

| Field | Type | Notes |
|---|---|---|
| `alias` | `string` | Stable handle used in tool name prefix. |
| `url` | `string` | Streamable-HTTP MCP server URL. |
| `auth_header_env_var` | `string` | Env var name carrying the `Authorization` header value (or empty for unauth). |
| `enabled` | `bool` | Allows toggling without removing the row. |
| `capability_prefix` | `string` | Prefix for derived capabilities, e.g. `tool.mcp.github` → tool `github.create_issue` requires `tool.mcp.github.create_issue`. |

### Scalar config keys

| Key | Type | Default | Notes |
|---|---|---|---|
| `config.ai.run_retention_days` | `int` | `30` | TTL for `AgentRun` + `AgentAuditLog`. |
| `config.ai.hitl_timeout_seconds` | `int` | `300` | Interactive HITL auto-deny window. |
| `config.ai.max_runtime_seconds` | `int` | `600` | Reaper threshold for stuck runs. |
| `config.ai.transcript_max_bytes` | `int` | `262144` | Application-layer cap on `AgentRun.transcript_json`. |
| `config.ai.hitl_poll_interval_ms` | `int` | `1000` | Worker poll interval during `awaiting_approval`. |

---

## Capabilities (seed)

Defined in `packages/access` capability seed. Granted to roles per app.

| Capability | Default holders | Notes |
|---|---|---|
| `agent.run` | authenticated users | Required for all `/api/ai/agent/run*` endpoints. |
| `agent.run.approve` | same as `agent.run` | Required for `POST /approve`. (See R-002.) |
| `agent.run.bypass_ownership` | admins | Required to view / cancel another user's run. |
| `tool.entity.read` | per-app | Entity read. |
| `tool.entity.list` | per-app | Entity list. |
| `tool.entity.create` | admins / authors | Destructive. |
| `tool.entity.update` | admins / authors | Destructive. |
| `tool.entity.delete` | admins | Destructive. |
| `tool.entity.search` | per-app | Full-text search. |
| `tool.relationship.traverse` | per-app | Graph traversal. |
| `tool.vector.search` | per-app | Semantic search. |
| `tool.mcp.<server>.<name>` | per-app | Per-remote-tool. Created at boot when remote servers are discovered. |

---

## Routes

See `contracts/agent-run-api.yaml` for the OpenAPI 3.1 contract.

| Method | Path | Capability | Notes |
|---|---|---|---|
| POST | `/api/ai/agent/run` | `agent.run` | Enqueue. |
| GET | `/api/ai/agent/run/{id}` | `agent.run` (+ ownership) | Status + transcript snapshot. |
| DELETE | `/api/ai/agent/run/{id}` | `agent.run` (+ ownership) | Cancel. |
| POST | `/api/ai/agent/run/{id}/approve` | `agent.run.approve` (+ ownership) | Approve / deny pending tool call. |
| GET | `/broadcast?channels=agent.run.<id>` | existing route | SSE stream (existing endpoint, no new wiring). |

---

## Messages

### `RunAgent` (Symfony Messenger)

| Field | Type | Notes |
|---|---|---|
| `runId` | `Symfony\Component\Uid\Uuid` | The `AgentRun.id` to execute. |

No other fields. The handler loads the row from the repository.

---

## SSE event vocabulary

Channel: `agent.run.<id>`. All events carry `run_id` plus the
event-specific payload below.

| Event | Payload |
|---|---|
| `run_started` | `{ run_id, agent_id?, started_at }` |
| `iteration` | `{ run_id, iteration, tokens_used_so_far }` |
| `tool_call_started` | `{ run_id, call_id, tool_name, arguments_redacted }` |
| `tool_call_completed` | `{ run_id, call_id, success, duration_ms }` |
| `approval_required` | `{ run_id, call_id, tool_name, arguments, expires_at }` |
| `approval_resolved` | `{ run_id, call_id, decision }` |
| `provider_chunk` | `{ run_id, chunk }` (forwarded `StreamChunk` shape) |
| `run_completed` | `{ run_id, response, token_usage, cost_cents, summary }` |
| `run_failed` | `{ run_id, error_code, error_message }` |
| `run_cancelled` | `{ run_id, cancelled_at }` |

---

## Migrations

WP-02 ships one migration:

```
packages/ai-agent/migrations/2026_05_18_000001_create_agent_run.php
```

Creates `agent_run` and `agent_audit_log` tables with the schemas
above. Idempotent (`CREATE TABLE IF NOT EXISTS` via DBAL schema
manager). Indexes created in the same migration.

No data migration required (the previous `AgentAuditLog` was in-memory
only; no historical data to preserve).
