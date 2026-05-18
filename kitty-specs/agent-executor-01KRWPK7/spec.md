<!--
  Spec Kitty mission spec. Canonical doctrine spec lives at
  docs/specs/agent-executor.md and is the source of truth for architecture,
  schemas, lifecycle, file paths, and breaking changes. This mission spec
  expresses the same scope as numbered FRs / NFRs / Cs for spec-kitty's
  workflow gates.
-->

# Mission: Agent Executor v1

**Status:** Ready for /spec-kitty.plan
**Change mode:** `bulk_edit` (see Bulk-Edit section below)
**Doctrine spec:** [../../docs/specs/agent-executor.md](../../docs/specs/agent-executor.md)
**Refs:** issue #1496 (agent consumer decision), PR #1508 (companion orphan deletion), archived mission `ai-agent-end-to-end-01KRW91P`

## Why this mission exists

`packages/ai-agent/src/` ships a coherent scaffold (`AgentInterface`,
`AgentExecutor`, `AgentContext`, `ToolRegistry`, `Provider/*`) with zero
consumers. Specs reference these symbols, but no Waaseyaa application can
actually run an agent — there is no CLI command, no HTTP route, no worker,
no run-state entity, no tool catalogue, no auth surface. The orphan
`Waaseyaa\AI\Agent\McpServer` was already deleted in PR #1508. The
remaining gap is genuine and requires consumer design, which is now
locked in `docs/specs/agent-executor.md`.

This mission implements that design. Outcome: every part of the Waaseyaa
framework — CLI, HTTP, worker, MCP-as-server-and-client, observability,
governance — supports running an agent end-to-end against a Waaseyaa app's
own data, with enterprise-grade safety and audit guarantees from day one.

## User scenarios & testing

### Primary flow — admin runs an agent through the SPA

1. Admin Daisy is logged into the admin SPA. She holds `agent.run` and the
   per-tool capabilities for her authoring workflow.
2. She submits a prompt through an agent UI, which `POST`s to
   `/api/ai/agent/run` with `{ agent_id: "authoring_assist", params: {...},
   destructive_approval: "interactive" }`.
3. The API returns HTTP 202 with `run_id`, `stream_url`, `status_url`,
   `approve_url`. The SPA opens an `EventSource` on `stream_url`.
4. The worker picks up the `RunAgent` Messenger message, runs
   `AgentExecutor::executeWithProvider()`, and emits SSE events
   (`run_started`, `iteration`, `tool_call_*`, `provider_chunk`, ...).
5. When a destructive tool is invoked, the worker pauses; the SPA
   receives `approval_required`, shows Daisy the tool name + arguments,
   and POSTs her decision to `/approve`.
6. The SPA shows the final response on `run_completed` and writes the
   transcript to the admin's history view.

### Operator flow — CLI inline run

1. Operator Jones runs `bin/waaseyaa ai:run "list nodes published this
   week" --inline` on a server with `WAASEYAA_DB`, `ANTHROPIC_API_KEY`,
   and `config.ai.providers` configured.
2. The CLI bypasses Messenger, instantiates `RunAgentHandler` in-process,
   streams `StreamChunk`s to stdout as the provider produces them, and
   prints a final summary on completion.
3. Run state is still persisted in `AgentRun` / `AgentAuditLog`.

### Automation flow — programmatic destructive run

1. External script holds a session token for a service user with
   `agent.run` + `tool.entity.create` capabilities.
2. The script POSTs an ad-hoc bundle with `destructive_approval: "all"`
   to permit blanket destructive tool execution.
3. The script polls `GET /api/ai/agent/run/{id}` until `status` is
   terminal, then exports the audit log via `AgentAuditLogRepository`.

### Reliability flow — worker crash recovery

1. A worker process crashes while a run is in `status='running'`.
2. The reaper command (`bin/waaseyaa ai:reap-stalled-runs`, scheduled
   every 5 minutes) finds runs older than
   `config.ai.max_runtime_seconds` (default 600 s) still at `running`.
3. Each stuck row is transitioned to `failed` with
   `error_code='worker_crashed'`. SSE `run_failed` is emitted.
4. Operators decide whether to re-issue the run; the framework does
   not auto-retry partially-executed runs.

### Compliance flow — audit query

1. An auditor needs every tool call any user made through any agent in
   the last 30 days.
2. They query `AgentAuditLog` rows joined to `AgentRun.account_id` over
   the window and export the result.

### Edge cases

- Run cancelled before worker pickup: handler loads the row, observes
  `status='cancelling'`, transitions to `cancelled` without invoking
  `AgentExecutor`.
- Provider rate-limit storm: 3 retries with exponential backoff; if
  still 429, run fails `provider_rate_limited`.
- HITL approve timeout: `awaiting_approval` row passes
  `config.ai.hitl_timeout_seconds`; reaper logic inside the worker (not
  the cron reaper) transitions to `failed` with `approval_timeout`.
- Remote MCP server unavailable at boot: tool drops from catalogue;
  agent runs without those tools.
- Transcript exceeds `config.ai.transcript_max_bytes` (default 256 KB):
  truncation marker inserted in `transcript_json`; full history remains
  reconstructable from `AgentAuditLog` rows.
- `--inline` mode with `destructive_approval: interactive`: rejected at
  CLI parse time (no human present to approve).

## Requirements

### Functional (FR)

| ID | Requirement | Status |
|---|---|---|
| FR-001 | The framework SHALL expose `POST /api/ai/agent/run` that accepts either `{agent_id, params?}` or `{bundle: {prompt, tools, model, system?, max_iterations?}}` and enqueues a `RunAgent` Messenger message. | Active |
| FR-002 | The framework SHALL expose `GET /api/ai/agent/run/{id}` returning status, transcript snapshot, token usage, cost, and error code. | Active |
| FR-003 | The framework SHALL expose `DELETE /api/ai/agent/run/{id}` that sets `status='cancelling'` (or `cancelled` if pre-pickup). | Active |
| FR-004 | The framework SHALL expose `POST /api/ai/agent/run/{id}/approve` accepting `{call_id, decision}` to resolve interactive HITL gates. | Active |
| FR-005 | The framework SHALL expose `bin/waaseyaa ai:run "<prompt>"` with flags `--inline`, `--agent=<id>`, `--dry-run`, `--watch`, `--destructive-approval=<mode>`. | Active |
| FR-006 | The framework SHALL expose `bin/waaseyaa ai:purge-runs` deleting AgentRun and AgentAuditLog rows older than `config.ai.run_retention_days`. | Active |
| FR-007 | The framework SHALL expose `bin/waaseyaa ai:reap-stalled-runs` flipping stuck `running` rows to `failed` with `worker_crashed`. | Active |
| FR-008 | A Messenger handler (`RunAgentHandler`) SHALL be the only path that executes `AgentExecutor` in production. CLI inline mode SHALL invoke the same handler in-process. | Active |
| FR-009 | The framework SHALL persist `AgentRun` and `AgentAuditLog` as entity types via `EntityRepository`, with access policies enforcing initiator ownership. | Active |
| FR-010 | The framework SHALL ship a new `packages/ai-tools` package at Layer 5 containing `AgentTool` VO, `AgentToolInterface`, `#[AsAgentTool]` attribute, and a manifest-compiler-discovered tool registry. | Active |
| FR-011 | The framework SHALL ship 8 stock tool implementations in `packages/ai-tools`: `EntityReadTool`, `EntityListTool`, `EntityCreateTool` (destructive), `EntityUpdateTool` (destructive), `EntityDeleteTool` (destructive), `EntitySearchTool`, `RelationshipTraverseTool`, `VectorSearchTool`. | Active |
| FR-012 | `packages/mcp/src/Tools/{Entity,Discovery,Traversal,Editorial}Tools.php` SHALL be deleted. `McpController` SHALL consume the new `packages/ai-tools` registry for `tools/list` and `tools/call`. | Active |
| FR-013 | `Waaseyaa\AI\Schema\Mcp\McpToolDefinition` SHALL be replaced by `Waaseyaa\AI\Tools\AgentTool` VO. Generators in `ai-schema/Mcp/*` SHALL be updated in lockstep. | Active |
| FR-014 | `Waaseyaa\AI\Agent\AgentInterface` SHALL be deleted. Procedural composition SHALL use `AgentRunService::runInline()` from a wrapping service instead. | Active |
| FR-015 | `Waaseyaa\AI\Agent\ToolRegistry::register()` SHALL change signature from `(McpToolDefinition, callable)` to `(AgentTool)`. The callable moves onto the tool class. | Active |
| FR-016 | `McpToolExecutor::accessCheck(false)` SHALL be removed. All entity-touching tool calls SHALL enforce entity-level access against the initiator's account. | Active |
| FR-017 | The framework SHALL provide `AgentDefinition` value object and `AgentDefinitionRegistry`, with `#[AsAgentDefinition]` attribute discovery via `PackageManifestCompiler`. | Active |
| FR-018 | The framework SHALL accept ad-hoc inline bundles in `POST /api/ai/agent/run` body without requiring a registered `AgentDefinition`. | Active |
| FR-019 | The framework SHALL enforce a per-tool capability check (`tool.<name>` or `tool.mcp.<server>.<name>`) at request-validation time and defensively at tool-execution time. | Active |
| FR-020 | The framework SHALL implement three HITL modes: `none` (deny-default, errors on destructive call), `all` (blanket approval, audit-logged), `interactive` (pause/resume via SSE + approve endpoint + timeout). | Active |
| FR-021 | The framework SHALL ship `McpClientToolSource` consuming remote MCP servers declared in `config.ai.mcp_servers`. Remote tools SHALL be prefixed by `capability_prefix` (e.g. `github.create_issue`). | Active |
| FR-022 | The framework SHALL persist run progress via `BroadcastStorage::push` on channel `agent.run.<id>` using the SSE event vocabulary defined in the doctrine spec (run_started, iteration, tool_call_started, tool_call_completed, approval_required, approval_resolved, provider_chunk, run_completed, run_failed, run_cancelled). | Active |
| FR-023 | The framework SHALL implement a status state machine: `queued → running → {completed, failed, awaiting_approval, cancelling}`, with idempotent transitions and a reaper that cannot regress terminal status. | Active |
| FR-024 | `RunAgentHandler` SHALL poll `AgentRun.status` between every iteration and every tool call; on `cancelling`, it SHALL break the loop and transition to `cancelled`. | Active |
| FR-025 | Provider call failures SHALL be retried up to 3 times with exponential backoff (cap 30 s) on `RateLimitException` (429) and on transport / 5xx errors. Exhausted retries SHALL terminate the run with `provider_rate_limited` or `provider_unavailable`. | Active |
| FR-026 | Tool callable exceptions SHALL NOT crash the run. They SHALL be caught, logged as `tool_call` audit rows with `success=false`, and returned to the LLM as `ToolResultBlock(isError: true)`. | Active |
| FR-027 | `AgentAuditLog` SHALL be append-only outside the purge job. Every provider call, tool call, tool result, and approval interaction SHALL produce exactly one audit row. | Active |
| FR-028 | The framework SHALL ship config entities: `config.ai.providers` (list of provider configurations with env-var key references), `config.ai.mcp_servers` (list of remote MCP server configurations), `config.ai.run_retention_days` (default 30), `config.ai.hitl_timeout_seconds` (default 300), `config.ai.max_runtime_seconds` (default 600), `config.ai.transcript_max_bytes` (default 262144), `config.ai.hitl_poll_interval_ms` (default 1000). | Active |
| FR-029 | `packages/ai-observability` SHALL subscribe to AgentRun lifecycle domain events and capture: input/output tokens per provider call, USD cost from a static per-model price table, per-tool invocation count, wall-clock and per-iteration latency. Persistence via `packages/telescope`. | Active |
| FR-030 | The framework SHALL register scheduler entries: daily `ai:purge-runs` (03:00 UTC) and every-5-minute `ai:reap-stalled-runs`. | Active |
| FR-031 | Route registration for `/api/ai/agent/run*` SHALL live as `AgentRouteServiceProvider` in `packages/ai-agent/src/Routing/` (Layer 5). The controller (`AgentRunController`) lives in `packages/ai-agent/src/Controller/`. *(Revised post-WP05 review: the original wording asked for `packages/routing` (L4), but L4 cannot import the L5 controller and `AccessChecker`; placing both controller and route provider at L5 satisfies the layer rules while keeping route registration in a single, discoverable provider.)* | Active |
| FR-032 | A new permission set SHALL be defined: `agent.run`, `agent.run.approve`, `agent.run.bypass_ownership`, and per-tool `tool.<name>` capabilities. | Active |
| FR-033 | `AgentRunController` SHALL enforce that the requesting account owns the run (initiator match) unless the account holds `agent.run.bypass_ownership`. | Active |

### Non-functional (NFR)

| ID | Requirement | Measurable threshold | Status |
|---|---|---|---|
| NFR-001 | Inline-mode CLI runs SHALL return within budget on a no-tool prompt. | `bin/waaseyaa ai:run "ping" --inline` returns within **10 s wall-clock** against `NullLlmProvider`. | Active |
| NFR-002 | Async HTTP-enqueued runs SHALL complete end-to-end within budget on a no-tool prompt. | HTTP 202 to terminal SSE `run_completed` within **30 s wall-clock** against `NullLlmProvider` with a single worker. | Active |
| NFR-003 | Cancellation latency SHALL be bounded. | After `DELETE`, the run reaches `status='cancelled'` within **3 iteration boundaries** plus one in-flight tool call. | Active |
| NFR-004 | The reaper SHALL detect stuck runs within bounded time. | A run stuck at `running` past `max_runtime_seconds` is transitioned to `failed` within **5 minutes** (scheduler interval). | Active |
| NFR-005 | Interactive HITL approvals SHALL time out within bounded time. | An `awaiting_approval` row resolves to `failed/approval_timeout` within **`hitl_timeout_seconds` + 1 s** (default 301 s). | Active |
| NFR-006 | SSE delivery SHALL be durable and replayable. | Every `BroadcastStorage::push` is persisted before the worker proceeds; clients can resume via `Last-Event-ID` with **at-least-once** semantics. | Active |
| NFR-007 | Transcript persistence SHALL be bounded. | `AgentRun.transcript_json` is truncated at **262144 bytes** (configurable); overflow marked `[truncated]`; full history recoverable from `AgentAuditLog`. | Active |
| NFR-008 | Layer enforcement SHALL pass on every PR. | `bin/check-package-layers` exits 0 across all WPs. | Active |
| NFR-009 | Dead-code gate SHALL pass on every PR. | `bin/check-dead-code` reports **no new findings** beyond the existing baseline. | Active |
| NFR-010 | Static analysis SHALL pass. | `composer phpstan` exits 0 at level 5. | Active |
| NFR-011 | Code style SHALL pass. | `composer cs-check` exits 0. | Active |
| NFR-012 | Test suite SHALL pass at every WP boundary. | `composer test` exits 0; per-layer coverage: unit (per package) + contract (provider, tool) + integration (`tests/Integration/PhaseN/AgentRuntime/`) + acceptance (CLI/HTTP end-to-end). | Active |
| NFR-013 | Composer policy gate SHALL pass. | `composer check-composer-policy` exits 0; the new package `packages/ai-tools` complies with CP002, CP003, CP-NEW, etc. | Active |
| NFR-014 | Bulk-edit gate SHALL pass at implement and review time. | `occurrence_map.yaml` validates against the schema; no `BLOCK` rows in the diff-compliance report. | Active |
| NFR-015 | Worker concurrency SHALL be safe. | Two workers consuming the same Messenger queue MUST never run the same `AgentRun.id` twice (validated by Messenger's transport-level locking + a `started_at IS NULL` guard at handler entry). | Active |

### Constraints (C)

| ID | Constraint | Status |
|---|---|---|
| C-001 | PHP 8.5+ across all packages. `declare(strict_types=1)` in every file. | Active |
| C-002 | Symfony 7.x components for Messenger, EventDispatcher, HttpFoundation, Routing, DependencyInjection, Uid. | Active |
| C-003 | All entities flow through `EntityRepository` per `.claude/rules/entity-storage-invariant.md`. No raw PDO; no Eloquent; no Illuminate dependencies. | Active |
| C-004 | Logging via `Waaseyaa\Foundation\Log\LoggerInterface` (NOT `psr/log`). Constructors accept `?LoggerInterface = null` and default to `NullLogger`. | Active |
| C-005 | Layer architecture enforced: `packages/ai-tools` and `packages/ai-agent` at Layer 5; routes at Layer 4. `packages/ai-agent` SHALL NOT import from `packages/mcp` (Layer 6). | Active |
| C-006 | All locked breaking changes are internal-only — no external consumers exist for `McpToolDefinition`, `AgentInterface`, `packages/mcp/src/Tools/*`, or `McpToolExecutor::accessCheck(false)`. Implementation SHALL verify by grep across the framework + sample apps before deletion. | Active |
| C-007 | The mission is `change_mode: bulk_edit`. `occurrence_map.yaml` MUST classify all 8 standard categories before implement gate opens. | Active |
| C-008 | Stdio MCP transport is **out of scope**; only Streamable HTTP for remote MCP servers. | Active |
| C-009 | Once a run reaches `status='running'`, it is at-most-once. Worker crashes leave the row for the reaper to mark `worker_crashed`. The framework SHALL NOT auto-retry partially-executed runs. | Active |
| C-010 | Provider secrets (API keys) live in env vars only. `config.ai.providers` rows carry env-var **names**, not values. | Active |
| C-011 | The kernel's authorization pipeline (`SessionMiddleware → AuthorizationMiddleware`) and `_account` request attribute are reused unchanged. No new auth surface. | Active |
| C-012 | Streaming uses existing `BroadcastStorage` SSE on `/broadcast`. No new SSE transport is introduced. | Active |
| C-013 | The `accessCheck(false)` bypass removal changes MCP-controller behaviour: external MCP clients now enforce entity-level access against their bearer-token account. WP-01 SHALL verify this is the intended security posture for `McpController` consumers; if not, an explicit pre-PR design note SHALL document the divergence. | Active |
| C-014 | Audit invariants (per doctrine spec §Audit invariants): append-only `AgentAuditLog`; one row per provider call; one `tool_call` + one `tool_result` (or `error`) per tool invocation; reaper cannot regress a terminal status. | Active |

## Success Criteria

| ID | Criterion |
|---|---|
| SC-001 | A user can submit an agent prompt through either the CLI or the HTTP API and receive a structured result with token / cost / tool-call audit data. |
| SC-002 | A user can watch an agent's progress in real time without polling. |
| SC-003 | A user can cancel an in-progress run at any time and have the cancellation honoured within seconds. |
| SC-004 | An administrator can prevent agents from performing destructive actions without explicit per-run approval. |
| SC-005 | An auditor can reconstruct any past agent run (provider calls, tool calls, errors, approvals) for compliance review. |
| SC-006 | An administrator can configure remote tool sources, grant per-tool capabilities to user groups, and revoke access without code changes. |
| SC-007 | An operator can satisfy a retention policy by purging old runs through a scheduled job. |
| SC-008 | An operator can recover from worker crashes without manual database surgery. |
| SC-009 | An extension author can register a new agent or tool via attribute discovery, without modifying framework code. |
| SC-010 | Framework health gates (layers, dead-code, PHPStan, cs-check, tests, composer-policy, bulk-edit) all pass on the mission's final PR. |

## Key Entities

- **`AgentRun`** — persisted run record. Carries initiator account, resolved bundle snapshot, status, transcript, token/cost totals, approval state, error code, timestamps.
- **`AgentAuditLog`** — persisted event log entity. One row per provider call / tool call / tool result / approval interaction; append-only outside the purge job.
- **`AgentDefinition`** — value object representing a named, attribute-registered agent bundle (prompt, tools, model, system, max_iterations, destructive_default, requires_capability).
- **`AgentTool`** — value object representing a runtime tool: name, capability, destructive flag, dry-run support, category, input schema, executor instance.
- **`AgentRunService`** — orchestration service exposing `enqueue()` (production async path) and `runInline()` (dev / CLI synchronous path).
- **`RunAgent`** — Symfony Messenger message carrying `run_id`.
- **`RunAgentHandler`** — Messenger handler that executes `AgentExecutor::executeWithProvider()`.
- **`McpClientToolSource`** — adapter that exposes remote MCP server tools to the agent's tool registry.
- **`StalledRunReaper`** — service invoked by `ai:reap-stalled-runs`.
- **(Retained)** `AgentContext`, `AgentResult`, `AgentAction`, `Provider\*`.
- **(Deleted)** `AgentInterface`, `Waaseyaa\AI\Schema\Mcp\McpToolDefinition`, `packages/mcp/src/Tools/*`, in-memory `AgentAuditLog` list inside `AgentExecutor`.

## Bulk-Edit Classification

This mission renames / moves **five named cross-file symbols and paths**:

1. `Waaseyaa\AI\Schema\Mcp\McpToolDefinition` → `Waaseyaa\AI\Tools\AgentTool` VO across `ai-schema`, `ai-agent`, `mcp`, and tests.
2. `packages/mcp/src/Tools/{Entity,Discovery,Traversal,Editorial}Tools.php` → `packages/ai-tools/src/*Tool.php` (with per-action splits per FR-011).
3. `Waaseyaa\AI\Agent\AgentInterface` → deleted (all references removed; no replacement symbol).
4. `ToolRegistry::register(McpToolDefinition, callable)` → `register(AgentTool)` signature, with callable moved onto the tool class.
5. `McpToolExecutor::accessCheck(false)` → removed (every call site enforces entity-level access against initiator account).

`change_mode: bulk_edit` is set in `meta.json`. An `occurrence_map.yaml`
classifying all 8 standard categories (`code_symbols`, `import_paths`,
`filesystem_paths`, `serialized_keys`, `cli_commands`,
`user_facing_strings`, `tests_fixtures`, `logs_telemetry`) MUST be filed
during `/spec-kitty.plan`.

## Assumptions

- `waaseyaa/queue` (Symfony Messenger integration) is production-ready and supports both a `sync` transport (for tests) and a real transport (for production workers).
- `BroadcastStorage` SSE is production-ready and handles long-lived `/broadcast` connections without leaks.
- `packages/config` supports CMI config entities with env-var key references via `api_key_env_var` / `auth_header_env_var` indirection (per current Waaseyaa config-management spec).
- `packages/scheduler` is production-ready for cron entries via attribute or service-provider registration.
- `packages/ai-vector` is production-ready for the `VectorSearchTool` to consume.
- `packages/ai-observability` exposes a stable subscription surface for AgentRun lifecycle events.
- No external (non-framework) code depends on the symbols being broken. Verification step ships in WP-01.
- Static price tables for token cost (per-provider, per-model) are sufficient for v1; live pricing-API integration is out of scope.

## Dependencies

**Upstream packages relied on (unchanged):**
- `packages/foundation` (LoggerInterface, BroadcastStorage, kernel)
- `packages/queue` (Symfony Messenger)
- `packages/entity`, `packages/entity-storage` (EntityRepository pattern)
- `packages/access` (AccountInterface, AccessChecker, policies)
- `packages/config` (config entities)
- `packages/scheduler` (cron registration)
- `packages/ai-schema` (`SchemaRegistry`, generators)
- `packages/ai-vector` (VectorStoreInterface, EmbeddingProviderInterface)

**Downstream consumers anticipated (out of mission scope, future):**
- `packages/bimaaji` — likely first registered `AgentDefinition` consumer.
- Authoring-assist contract — long-term consumer.
- External plugins — register agents and tools via attribute.

## Scope

### In scope (v1)

- All FRs, NFRs, and Cs above.
- New package `packages/ai-tools`.
- All breaking changes named in C-006 and the Bulk-Edit section.
- Documentation updates: `docs/specs/agent-executor.md` (already filed), `skills/waaseyaa/ai-integration/SKILL.md`, `packages/ai-tools/README.md`, `packages/ai-agent/README.md` updates.
- Security review pass via the `security-review` skill on final PR.

### Out of scope (v2 or later)

- Separate planner abstraction.
- Semantic prompt cache.
- Continuous-evaluation harness; drift detection.
- Prompt-injection guards.
- Encrypted-at-rest transcripts.
- Stdio MCP transport.
- Pause/resume of failed runs (no auto-retry of partially-executed runs).
- Multi-agent orchestration (one agent per run in v1).
- Per-tenant cost caps (token totals are observed, not enforced).
- Drift-detector integration for prompt templates.
- Live cost API integration (static price table sufficient for v1).
- Admin SPA UI for running agents (this mission delivers the backend; admin-surface lights up in a follow-up).

## WP outline (for `/spec-kitty.tasks-outline`)

| WP | Title | Net change | Depends on |
|---|---|---|---|
| WP-01 | `packages/ai-tools` package + tool migration | New package, `AgentTool` VO, `#[AsAgentTool]` attribute, manifest-compiler discovery, 8 stock tool classes, delete `packages/mcp/src/Tools/`, rewire `McpController`, layer + composer-policy + dead-code gates pass, **bulk-edit gate** (`occurrence_map.yaml` validated). Also: external-consumer verification grep (per C-006). | — |
| WP-02 | `AgentRun` + `AgentAuditLog` entities | New entity types, `EntityRepository` wiring, migration, access policies (initiator ownership + bypass capability), replaces in-memory audit list, indexes per doctrine spec. | WP-01 |
| WP-03 | `AgentDefinition` registry + `AgentExecutor` rewire | `AgentDefinition` VO + `AgentDefinitionRegistry`, `#[AsAgentDefinition]` attribute, HITL state machine (none / all / interactive), per-iteration cancellation poll, provider retry logic, token/cost accounting on `AgentResult`, delete `AgentInterface`. | WP-02 |
| WP-04 | `RunAgent` message + worker + `AgentRunService` | Messenger message, `RunAgentHandler`, `AgentRunService::enqueue()` + `runInline()`, `StalledRunReaper`, worker-concurrency guard (per NFR-015). | WP-03 |
| WP-05 | HTTP endpoints + SSE wiring | `AgentRunController` (POST / GET / DELETE / approve), `AgentRouteServiceProvider` in `packages/routing`, `BroadcastStorage::push` calls for the SSE event vocabulary, per-route capability checks, initiator-ownership enforcement. | WP-04 |
| WP-06 | CLI commands + scheduler entries | `AiRunCommand`, `AiPurgeRunsCommand`, `AiReapStalledRunsCommand`, scheduler entries (daily purge, 5-minute reaper). | WP-04 |
| WP-07 | `McpClientToolSource` (remote MCP consumption) | Streamable-HTTP MCP client, `config.ai.mcp_servers` config entity, capability-prefix mapping (`tool.mcp.<server>.<name>`), stub-server integration test. | WP-03 |
| WP-08 | `ai-observability` listeners | Subscribe to AgentRun lifecycle events; capture token/cost/tool-count/latency metrics; persist via `packages/telescope`; static per-model price table. | WP-04 |
| WP-09 | Spec, docs, security review | `skills/waaseyaa/ai-integration/SKILL.md` updates, `packages/ai-tools/README.md`, `packages/ai-agent/README.md` rewrite, `security-review` skill pass on the final aggregate PR, v1 readiness checklist. | All |

WP-01 → 05 are the critical path. WP-06 / 07 / 08 are parallelizable
after WP-04. WP-09 is the wrap-up.

## Outstanding work for /spec-kitty.plan

- Produce `occurrence_map.yaml` with all 8 standard categories. Expected category dispositions (subject to plan-phase review):
  - `code_symbols`: `rename` (internal PHP class renames are safe).
  - `import_paths`: `rename` (must track symbol renames).
  - `filesystem_paths`: `rename` (`packages/mcp/src/Tools/*` → `packages/ai-tools/src/*`; verified no on-disk references outside the repo).
  - `serialized_keys`: `do_not_change` (API JSON response shapes preserved; if any DB column would have used `mcp_tool_definition_*` it'll be renamed in same migration).
  - `cli_commands`: `do_not_change` (no existing CLI commands carry the names being renamed; new `ai:*` commands are net-new).
  - `user_facing_strings`: `manual_review` (admin SPA strings are TBD this phase; flag any).
  - `tests_fixtures`: `rename`.
  - `logs_telemetry`: `do_not_change` (Prometheus / telescope label strings preserved; new events get new names).
- Confirm `agent.run.approve` capability default equals `agent.run` (currently planned) versus separate seed.
- Confirm static price table location and update cadence (likely `packages/ai-observability/Pricing/ModelPriceTable.php`).
- Confirm `transcript_json` storage type — TEXT vs LONGTEXT vs JSON column depending on backend.

## References

- `docs/specs/agent-executor.md` — canonical doctrine spec (architecture, schemas, lifecycle, file paths, error taxonomy, audit invariants).
- `docs/specs/ai-integration.md` — broader AI subsystem.
- `docs/specs/mcp-endpoint.md` — the MCP server Waaseyaa hosts.
- `docs/specs/broadcasting.md` — SSE delivery via `BroadcastStorage`.
- `docs/specs/authoring-assist-contract.md` — a downstream consumer this runtime will eventually back.
- `docs/specs/infrastructure.md` — `LoggerInterface`, error policy.
- `.claude/rules/entity-storage-invariant.md` — canonical entity persistence pipeline.
- Issue #1496 — agent consumer decision (this mission is the answer).
- PR #1508 — companion orphan deletion (`McpServer` removed).
- `kitty-specs/archive/ai-agent-end-to-end-01KRW91P/` — archived predecessor mission whose split produced this design.
