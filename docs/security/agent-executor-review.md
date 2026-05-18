# Agent Executor v1 — Security Review

> Manual review against `docs/specs/agent-executor.md` § Security posture, aggregating the diff for mission `agent-executor-01KRWPK7` (WP-01..WP-09). The host's `security-review` slash-skill is a Claude Code surface and could not be invoked directly inside this implementer subagent; this document is the equivalent manual pass against the same checklist.

## Scope

External-facing and security-relevant surfaces introduced by this mission:

| Surface | Files |
|---|---|
| HTTP controller | `packages/ai-agent/src/Controller/AgentRunController.php`, `packages/ai-agent/src/Controller/AgentRunRequestValidator.php` |
| Routes + capability gates | `packages/ai-agent/src/Routing/AgentRouteServiceProvider.php` |
| Access policy | `packages/ai-agent/src/AccessPolicy/AgentRunAccessPolicy.php` |
| MCP server endpoint (consumer of new registry) | `packages/mcp/src/Controller/McpController.php` |
| CLI commands | `packages/cli/src/Command/Ai/{AiRunCommand,AiPurgeRunsCommand,AiReapStalledRunsCommand}.php` |
| Config schema (secrets indirection) | `packages/config/src/Schema/Ai/{ProvidersConfig,McpServersConfig,ScalarsConfig}.php` |
| Defaults | `defaults/ai.yaml` (env-var key references only — verified by `bin/check-no-secrets`) |
| Tool catalogue | `packages/ai-tools/src/` (entity ACL-enforced) |
| Remote MCP client | `packages/ai-agent/src/Mcp/{McpClientToolSource,StreamableHttpMcpClient}.php` |
| Persisted entities | `packages/ai-agent/src/Entity/{AgentRun,AgentAuditLog}.php` |
| Worker | `packages/ai-agent/src/Message/{RunAgent,RunAgentHandler}.php` |
| Observability | `packages/ai-observability/src/Listener/AgentRunTelemetryListener.php` |

Out of scope (no new surface introduced): admin SPA UX, OIDC, session cookie handling, CORS.

## Findings

### Authentication (C-011)

- **Status: Pass.** No new auth surface is introduced. `AgentRunController` reads the authenticated account exclusively from `$request->attributes->get('_account')`. `SessionMiddleware → AuthorizationMiddleware` runs unchanged. No bearer-token parsing, no custom session handling, no anonymous-allowed paths.
- Confirmed: every route in `AgentRouteServiceProvider` has a `_permission` or `_gate` option; none are `_public`.

### Authorization

- **Status: Pass.** Two layers of authorization apply:
  1. **Per-route capability gates** in `AgentRouteServiceProvider` — `POST /api/ai/agent/run` requires `agent.run.create`; `GET /api/ai/agent/run/{id}`, `DELETE`, and `approve` require the corresponding `agent.run.{view,cancel,approve}` capability. Enforcement happens in `AccessChecker` via the route's `_permission` option before the controller runs.
  2. **Entity-level `AgentRunAccessPolicy`** — implements `AccessPolicyInterface`. `view` / `cancel` / `approve` operations return `AccessResult::allowed()` only when `$run->initiatorId === $account->id()` OR the account holds the `agent.run.bypass_ownership` capability.
- Bypass-capability scope (`agent.run.bypass_ownership`): grep'd across `packages/` and `tests/`; only referenced by the policy itself and explicit administrator role definitions. No admin-SPA UI references it (no SPA work in this mission, verified). No leak.

### Input validation (FR-019)

- **Status: Pass.** `AgentRunRequestValidator` is invoked from `AgentRunController::create()` before the run is persisted. Validates:
  - `agent_id` is a non-empty string and matches a registered `AgentDefinition`.
  - `prompt` is a non-empty string under the configured max length.
  - `parameters` decodes to a JSON object (rejects arrays / scalars at the top level).
  - HITL `approve_choices`, where supplied, match the enumerable set declared on the bundle.
- Tool argument validation: every tool's `inputSchema()` is enforced at dispatch time inside `AgentExecutor`; non-conforming arguments fail the call without invoking the executor.

### Secret handling (C-010)

- **Status: Pass.** `defaults/ai.yaml` carries `api_key_env_var` / `auth_header_env_var` **names**, not values. `ProvidersConfig::resolveApiKey()` and `McpServersConfig::resolveAuthHeader()` read the named environment variable at request time. `bin/check-no-secrets` (CI gate) sweeps `defaults/`, config files, and committed YAML for accidental literals.
- Confirmed: no `OPENAI_API_KEY=` literals or any `sk-…` patterns in the mission diff.

### Bypass-capability scope (`agent.run.bypass_ownership`)

- **Status: Pass.** Capability is only granted via role assignment. It does NOT flow into any admin-SPA route or component (no admin-SPA work in this mission). It is reflected in the `agent.run.bypass_ownership` constant in `AgentRunAccessPolicy`; no implicit grants.

### Audit-log tamper resistance (C-014)

- **Status: Pass.** `AgentAuditLog` is persisted via `EntityRepository::save()`. Only two call paths INSERT rows:
  1. `AgentExecutor::recordEvent()` (append-only inside the executor loop).
  2. `AgentRunService::recordInitialEvent()` (initial `run_started` row).
- Only one path deletes rows: `AiPurgeRunsCommand` → `AgentAuditLogRepository::purgeByRunIds()`, called from the scheduled daily purge. Reaper does not delete audit rows; it only flips `AgentRun.status` (and even then refuses to regress a terminal status — `markFailed()` is a no-op on `completed`/`failed`/`cancelled`).
- No raw `DELETE FROM agent_audit_log` / `DBAL\Connection::executeStatement('DELETE...')` paths in the diff. The append-only invariant is enforced by surface contract, not just convention.

### Provider HTTP timeouts

- **Status: Pass.** `config.ai.providers.timeout_ms` (default 30000 ms) is read by the provider HTTP client. `StreamableHttpMcpClient` reads `config.ai.mcp_servers[i].timeout_ms` (default 30000 ms). Neither client honours an "infinite" timeout: missing config falls back to the default, not unbounded.

### SSE broadcast surface

- **Status: Pass.** The mission did NOT introduce a new SSE transport. Events are pushed onto the durable `BroadcastStorage` table via `BroadcastStorage::push()`; clients consume via the existing `GET /broadcast?channels=…` endpoint, which already enforces `_account` filtering. Initiator-only channel naming (`agent.run.<id>`) plus the existing broadcast access check prevents cross-tenant subscription.

### Worker concurrency (NFR-015)

- **Status: Pass.** Two layers prevent double-execution:
  1. Symfony Messenger transport-level locking (existing).
  2. `RunAgentHandler::__invoke()` performs a CAS — `markRunning()` updates `AgentRun.started_at` only if `started_at IS NULL`. If the CAS fails (row already running) the handler returns without invoking the executor.

### Cancellation

- **Status: Pass.** `DELETE /api/ai/agent/run/{id}` updates `AgentRun.status` to `cancellation_requested`. `AgentExecutor` polls this status at each iteration boundary plus after each in-flight tool call returns. Bounded latency: NFR-003 (3 iterations + 1 tool call), verified by `CancellationTest`.

## Mitigations

All findings are Pass — no new mitigations introduced beyond the existing kernel surface (session middleware, access-checker, broadcast access filtering, env-var indirection, `bin/check-no-secrets`, `bin/check-package-layers`, `bin/check-dead-code`, `composer check-composer-policy`).

## Outstanding risks

These are tracked follow-up issues, none of which block v1 merge:

| Issue | One-liner |
|---|---|
| **#1509** | Provider exception hierarchy — refine the exception classes raised by HTTP provider clients so retries / circuit-breaker logic can discriminate by category. Current code raises a generic exception type which the retry loop already handles correctly, but the type does not communicate the distinction explicitly. |
| **#1510** | EventDispatcher wiring for `AgentRunTelemetryListener` — the listener is implemented and tested via direct invocation, but the global event dispatcher registration is filed for a follow-up so the listener can be triggered automatically by the `AgentRun` lifecycle events. No security impact (telemetry is informational; access enforcement is upstream). |
| **#1511** | Broadcaster dedup + OpenAPI for `pending_approval` — the SSE broadcaster currently pushes events idempotent-by-content; explicit dedup keys are a follow-up. OpenAPI spec for the `pending_approval` payload is filed for v1.1. |
| **#1512** | Scheduler boot wiring — `AgentScheduleEntries` is defined and unit-tested but the kernel boot-time `register()` call is not yet wired. SC-007 (purge job) and NFR-004 (5-minute reaper detection) are functionally deliverable via direct CLI invocation (`bin/waaseyaa ai:purge-runs`, `bin/waaseyaa ai:reap-stalled-runs`); the cron-style registration is the deferred piece. Operators can run the commands from their own cron until the wiring lands. **Security implication: no degradation** — the commands themselves are unchanged. |
| **#1513** | `--watch` SSE consumer — CLI flag that subscribes to the durable broadcast channel and streams events to stdout. Quality-of-life follow-up; the HTTP SSE surface itself is fully functional today. |

No deferred blocking findings. Each follow-up is a feature enhancement, not a security defect.

## Sign-off date

**2026-05-18** — Mission lead (`jonesrussell`). Reviewed by `claude:opus:reviewer` on each WP boundary (lanes a..h); aggregate manual pass filed here as part of WP-09.
