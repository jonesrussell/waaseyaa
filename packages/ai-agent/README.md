# waaseyaa/ai-agent

**Layer 5 — AI**

Waaseyaa agent runtime: the executor, the run service, the Messenger handler, and the in-process audit-log persistence. This package turns a registered `AgentDefinition` and an initiator account into a persisted `AgentRun` with real-time SSE progress, token / cost accounting, HITL approvals, and an append-only audit trail.

## Where to start

The canonical doctrine spec is **[`docs/specs/agent-executor.md`](../../docs/specs/agent-executor.md)**. It owns the success criteria, NFR thresholds, audit invariants, SSE event vocabulary, HITL state machine, security posture, and the `Waaseyaa\AI\Tools\ToolRegistryInterface` contract.

For the tool catalogue (8 stock tools + remote MCP source) see **[`packages/ai-tools/README.md`](../ai-tools/README.md)**.

## Surfaces

| Surface | Entry point | Notes |
|---|---|---|
| **CLI** | `packages/cli/src/Command/Ai/{AiRunCommand,AiPurgeRunsCommand,AiReapStalledRunsCommand}.php` | `bin/waaseyaa ai:run "<prompt>" --inline` for sync; omit `--inline` for async enqueue. |
| **HTTP** | `packages/ai-agent/src/Controller/AgentRunController.php` (routes: `packages/ai-agent/src/Routing/AgentRouteServiceProvider.php`) | `POST /api/ai/agent/run`, `GET /api/ai/agent/run/{id}`, `DELETE /api/ai/agent/run/{id}`, `POST /api/ai/agent/run/{id}/approve`. Per-route capability checks + initiator ownership via `AgentRunAccessPolicy`. |
| **SSE** | `BroadcastStorage` push on `agent.run.<id>` | Consume via `GET /broadcast?channels=agent.run.<id>`. Events: `run_started`, `iteration`, `tool_call`, `tool_result`, `approval_required`, `run_completed`, `run_failed`, `run_cancelled`. |
| **Messenger worker** | `packages/ai-agent/src/Message/{RunAgent,RunAgentHandler}.php` | Consumes the `RunAgent` message; CAS-guarded against duplicate delivery (NFR-015). |
| **Scheduler** | `packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php` | Daily purge + 5-minute stalled-run reaper. |

## Extension points

Register agent bundles and tools via attribute discovery — the package-manifest compiler scans both.

```php
use Waaseyaa\AI\Agent\Attribute\AsAgentDefinition;
#[AsAgentDefinition(id: 'my_agent', model: 'gpt-4o-mini')]
final class MyAgent { /* prompt, tools, system, max_iterations, destructive_default, requires_capability */ }

use Waaseyaa\AI\Tools\Attribute\AsAgentTool;
#[AsAgentTool(name: 'my_tool', capability: 'my.feature', destructive: false)]
final class MyTool extends \Waaseyaa\AI\Tools\AbstractAgentTool { /* execute() */ }
```

After adding new attributes run `bin/waaseyaa optimize:manifest` (or restart the dev server).

## Persisted entities

| Entity | Purpose |
|---|---|
| `Waaseyaa\AI\Agent\Entity\AgentRun` | Run record: initiator, bundle snapshot, status, transcript (truncated at the configured cap), token/cost totals, approval state, timestamps. |
| `Waaseyaa\AI\Agent\Entity\AgentAuditLog` | Append-only event log: one row per provider call / tool call / tool result / approval. Purged only by `AiPurgeRunsCommand`. |

Repositories live in `packages/ai-agent/src/Repository/`. The legacy in-memory `Waaseyaa\AI\Agent\AgentAuditLog` value-object list inside `AgentExecutor` is removed.

## Services

| Class | Role |
|---|---|
| `AgentRunService` | `enqueue()` (async path) and `runInline()` (CLI/dev sync path). |
| `AgentExecutor` | Per-iteration loop: provider call, tool dispatch via `ToolRegistryInterface`, HITL gating, cancellation poll. |
| `AgentDefinitionRegistry` | Resolves `AgentDefinition` VOs by id. |
| `StalledRunReaper` | Transitions runs stuck past `max_runtime_seconds` to `failed`. |
| `Mcp\McpClientToolSource` + `StreamableHttpMcpClient` | Adapts remote MCP servers into the local tool registry; capability prefix `tool.mcp.<server>.<name>`. |

## Quality gates

Every change to this package MUST keep the following green:

- `bin/check-package-layers` — ai-agent imports only from layer ≤ 5 (entity, entity-storage, access, queue, ai-schema, ai-tools).
- `bin/check-dead-code` — no new findings beyond `phpstan-dead-code-baseline.neon`.
- `composer check-composer-policy` — CP002 / CP003 / CP-NEW.
- `composer phpstan` — level 5.
- `composer cs-check` — PHP-CS-Fixer dry-run.
- Bulk-edit gate — `occurrence_map.yaml` validates; zero `BLOCK` rows in the diff-compliance report.

Tests:

```bash
./vendor/bin/phpunit packages/ai-agent/tests/
./vendor/bin/phpunit tests/Integration/PhaseN/AgentRuntime/
```
