# Tasks: Agent Executor v1

**Mission:** `agent-executor-01KRWPK7`
**Spec:** [spec.md](spec.md) · **Plan:** [plan.md](plan.md) · **Doctrine:** [../../docs/specs/agent-executor.md](../../docs/specs/agent-executor.md)
**Branch:** `main` (planning + merge target). `branch_matches_target: true`.
**Change mode:** `bulk_edit` — `occurrence_map.yaml` validated; primary symbol `McpToolDefinition → AgentTool`.

## Overview

49 subtasks rolled into 9 work packages. The work follows the
doctrine spec's layering: a new Layer-5 package `packages/ai-tools`
unblocks everything else; persisted run state and access policy land
next; the executor is rewired against the new registry and entities;
the Messenger handler + run service make production execution
possible; HTTP and CLI surfaces enqueue runs; observability and
remote-MCP consumption fan out in parallel; documentation and
security review wrap up.

## Execution lanes

```
            ┌─► WP06 (cli + scheduler) ─┐
            │                           │
WP01 ─► WP02 ─► WP03 ─► WP04 ┼─► WP05 (http + sse) ─┼─► WP09 (docs + sec review)
            │       │       │                           │
            │       └─► WP07 (mcp client) ──────────────┤
            │                                           │
            └────────► WP08 (observability) ────────────┘
```

- **Critical path:** WP-01 → WP-02 → WP-03 → WP-04 → WP-05.
- **Parallelizable after WP-04:** WP-05, WP-06, WP-08 (and WP-07 from WP-03).
- **Wrap-up:** WP-09 depends on all.

## Subtask index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | External-consumer verification grep (`bin/check-external-consumers ai-agent-orphans`) | WP01 | |
| T002 | Scaffold `packages/ai-tools` (composer.json, ServiceProvider, autoload) | WP01 | |
| T003 | `AgentTool` VO + `AgentToolInterface` + `AgentToolResult` + `AbstractAgentTool` + `#[AsAgentTool]` | WP01 | |
| T004 | `AttributeToolRegistry` + `PackageManifestCompiler` discovery | WP01 | |
| T005 | Eight stock tools (`EntityRead/List/Create/Update/Delete/Search`, `RelationshipTraverse`, `VectorSearch`) | WP01 | [P] |
| T006 | Delete `packages/mcp/src/Tools/*` and rewire `McpController` against the new registry | WP01 | |
| T007 | Update `ai-schema/Mcp/*` generators; remove `McpToolDefinition` | WP01 | |
| T008 | Remove `McpToolExecutor::accessCheck(false)`; file ADR `0XX-mcp-tool-access-enforcement.md` | WP01 | |
| T009 | Gates: composer-policy, layers, dead-code, bulk-edit `occurrence_map.yaml` diff-compliance | WP01 | |
| T010 | `AgentRun` entity + entity type + enums (`RunStatus`, `HitlMode`, `EventType`) | WP02 | |
| T011 | `AgentAuditLog` entity + entity type | WP02 | |
| T012 | Migration `2026_05_18_000001_create_agent_run.php` with indexes | WP02 | |
| T013 | `AgentRunRepository` + `AgentAuditLogRepository` | WP02 | |
| T014 | `AgentRunAccessPolicy` (initiator ownership + bypass capability) | WP02 | |
| T015 | Capability seed updates (`agent.run`, `agent.run.approve`, `agent.run.bypass_ownership`, `tool.*`) | WP02 | |
| T016 | `AiAgentEntityServiceProvider` wiring + `composer.json` bumps | WP02 | |
| T017 | `AgentDefinition` VO + `#[AsAgentDefinition]` + `AgentDefinitionRegistry` | WP03 | |
| T018 | Extend `AgentResult` with `tokenUsageIn/Out`, `costCents` | WP03 | |
| T019 | Rewire `AgentExecutor` against new `ToolRegistry::register(AgentTool)` | WP03 | |
| T020 | HITL state machine (`none` / `all` / `interactive`) in `AgentExecutor` | WP03 | |
| T021 | Per-iteration + per-tool-call cancellation polling | WP03 | |
| T022 | Provider retry logic (3 retries, exp backoff cap 30 s) | WP03 | |
| T023 | Delete `AgentInterface`; sweep remaining references | WP03 | |
| T024 | `RunAgent` Messenger message + serialization | WP04 | |
| T025 | `RunAgentHandler` (status state machine, worker-concurrency guard) | WP04 | |
| T026 | `AgentRunService::enqueue()` + `runInline()` | WP04 | |
| T027 | `StalledRunReaper` service | WP04 | |
| T028 | Messenger transport wiring (`sync` for tests, real for prod) + provider/scalar config entities | WP04 | |
| T029 | `AgentRunController` (POST `/run`, GET, DELETE, POST `.../approve`) | WP05 | |
| T030 | `AgentRouteServiceProvider` in `packages/routing` | WP05 | |
| T031 | `AgentRunBroadcaster`: `BroadcastStorage::push` per SSE event vocabulary | WP05 | |
| T032 | Per-route capability checks + initiator-ownership enforcement | WP05 | |
| T033 | Request validator: bundle vs `agent_id`, destructive_approval normalization | WP05 | |
| T034 | `AiRunCommand` (`ai:run` with `--inline`, `--agent`, `--dry-run`, `--watch`, `--destructive-approval`) | WP06 | |
| T035 | `AiPurgeRunsCommand` (`ai:purge-runs`) | WP06 | [P] |
| T036 | `AiReapStalledRunsCommand` (`ai:reap-stalled-runs`) | WP06 | [P] |
| T037 | Scheduler entries: daily purge 03:00 UTC + 5-minute reaper | WP06 | |
| T038 | Streamable-HTTP MCP client implementation | WP07 | |
| T039 | `config.ai.mcp_servers` config entity wiring | WP07 | |
| T040 | Capability-prefix mapping + tool registration via `McpClientToolSource` | WP07 | |
| T041 | Stub-server integration test | WP07 | |
| T042 | `ModelPriceTable` (static per-provider/per-model pricing) | WP08 | [P] |
| T043 | `AgentRunTelemetryListener` (subscribes to AgentRun lifecycle events) | WP08 | |
| T044 | Token/cost/tool-count/latency capture + Telescope persistence | WP08 | |
| T045 | Update `skills/waaseyaa/ai-integration/SKILL.md` | WP09 | [P] |
| T046 | Rewrite `packages/ai-agent/README.md` | WP09 | [P] |
| T047 | CHANGELOG `[Unreleased]` bullet | WP09 | [P] |
| T048 | Run `security-review` skill on the aggregate PR; record output in `docs/security/agent-executor-review.md` | WP09 | |
| T049 | v1 release-readiness checklist (gates summary + SC-001..010 verification) | WP09 | |

Note: the `[P]` markers above indicate parallelism within a WP, not status.
Status tracking lives in the per-WP checkbox rows below.

---

## WP01 — `packages/ai-tools` package + tool migration

**Priority:** P0 (foundational; blocks everything).
**Depends on:** —
**Goal:** Create the Layer-5 tool catalogue shared by `mcp` and `ai-agent`. Move the four MCP tool classes into `packages/ai-tools` as eight stock tools, rewire `McpController`, kill `McpToolDefinition` and `McpToolExecutor::accessCheck(false)`, and prove every quality gate (layers, dead-code, composer-policy, bulk-edit) passes.
**Independent test:** `composer test` + `bin/check-package-layers` + `bin/check-dead-code` + `bin/check-composer-policy` + `composer phpstan` + `composer cs-check` + bulk-edit gate all green. McpControllerToolsSharingTest demonstrates the controller serves tools from the new registry.
**Estimated prompt size:** ~520 lines.
**Prompt:** [tasks/WP01-ai-tools-package-and-tool-migration.md](tasks/WP01-ai-tools-package-and-tool-migration.md)

**Subtasks:**
- [ ] T001 External-consumer verification grep (WP01)
- [ ] T002 Scaffold `packages/ai-tools` (WP01)
- [ ] T003 `AgentTool` VO + interfaces + `#[AsAgentTool]` (WP01)
- [ ] T004 `AttributeToolRegistry` + manifest-compiler discovery (WP01)
- [ ] T005 Eight stock tool implementations (WP01)
- [ ] T006 Delete `packages/mcp/src/Tools/*` and rewire `McpController` (WP01)
- [ ] T007 Update `ai-schema/Mcp/*` generators; remove `McpToolDefinition` (WP01)
- [ ] T008 Remove `accessCheck(false)`; file ADR (WP01)
- [ ] T009 Gate verification + bulk-edit diff-compliance (WP01)

**Risks:**
- Hidden consumer of `McpToolDefinition` outside the repo grep boundary. *Mitigation:* run T001 first; abort + escalate on hits.
- Dead-code baseline regressions from the move. *Mitigation:* regenerate baseline only if every new finding is reflection-discovered (`@api` or attribute-tagged).

**Parallel opportunities:** T005 (eight stock tool files are independent within the WP).

---

## WP02 — `AgentRun` + `AgentAuditLog` entities

**Priority:** P0.
**Depends on:** WP-01 (consumers will pull `AgentTool` types, but no direct file overlap).
**Goal:** Persist run state and audit log via `EntityRepository`. Define enums, repositories, access policy, capability seed, and migration.
**Independent test:** Repository CRUD round-trip works against `DBALDatabase::createSqlite()`; access policy returns `forbidden` for non-owners without `agent.run.bypass_ownership`; migration creates the four declared indexes.
**Estimated prompt size:** ~430 lines.
**Prompt:** [tasks/WP02-agent-run-and-audit-log-entities.md](tasks/WP02-agent-run-and-audit-log-entities.md)

**Subtasks:**
- [x] T010 `AgentRun` entity + enums (WP02)
- [x] T011 `AgentAuditLog` entity (WP02)
- [x] T012 Migration with indexes (WP02)
- [x] T013 `AgentRunRepository` + `AgentAuditLogRepository` (WP02)
- [x] T014 `AgentRunAccessPolicy` (WP02)
- [x] T015 Capability seed updates (WP02)
- [x] T016 `AiAgentEntityServiceProvider` wiring + composer.json bumps (WP02)

**Risks:**
- `transcript_json` column type variance across DBAL backends. *Mitigation:* use `Types::TEXT` (the plan-locked decision) and enforce the 256 KB cap in application layer.
- Bypass capability matrix mistakes. *Mitigation:* unit-test policy with anonymous account, initiator account, and bypass account.

---

## WP03 — `AgentDefinition` registry + `AgentExecutor` rewire

**Priority:** P0.
**Depends on:** WP-02.
**Goal:** Add `AgentDefinition` and registry, rewire `AgentExecutor` against the new `ToolRegistry::register(AgentTool)` signature, implement HITL state machine + cancellation polling + provider retry, and delete `AgentInterface`.
**Independent test:** Contract suite for `AgentExecutor::executeWithProvider()` covers HITL `none` (errors on destructive), HITL `all` (audit-logged, no pause), HITL `interactive` (pauses, resumes on approval, fails on timeout); provider retry exhaustion terminates with `provider_rate_limited`; cancellation flips status within bounded iterations.
**Estimated prompt size:** ~480 lines.
**Prompt:** [tasks/WP03-agent-definition-registry-and-executor-rewire.md](tasks/WP03-agent-definition-registry-and-executor-rewire.md)

**Subtasks:**
- [x] T017 `AgentDefinition` VO + attribute + registry (WP03)
- [x] T018 Extend `AgentResult` with token + cost fields (WP03)
- [x] T019 Rewire `AgentExecutor` for new `ToolRegistry` signature (WP03)
- [x] T020 HITL state machine (WP03)
- [x] T021 Per-iteration + per-tool-call cancellation polling (WP03)
- [x] T022 Provider retry with exponential backoff (WP03)
- [x] T023 Delete `AgentInterface` (WP03)

**Risks:**
- Cancellation polling adds DB load. *Mitigation:* poll only at iteration / tool-call boundaries (not inside provider streaming chunks).
- Retry sleep blocks the worker thread. *Mitigation:* cap at 30 s and document the at-most-once contract (C-009).

---

## WP04 — `RunAgent` message + worker + `AgentRunService` + reaper

**Priority:** P0.
**Depends on:** WP-03.
**Goal:** Make production execution possible. Define the Messenger message and handler, the `AgentRunService` with both `enqueue()` (async) and `runInline()` (sync) entry points, the stalled-run reaper, and the worker-concurrency guard. Register provider + scalar config entities.
**Independent test:** Async test enqueues → consumes via `sync` transport → row reaches `completed` against `NullLlmProvider`. Reaper test sets a row's `started_at` past `max_runtime_seconds` → run terminates `failed/worker_crashed`. Two concurrent handlers on same `runId` → second short-circuits via guard.
**Estimated prompt size:** ~450 lines.
**Prompt:** [tasks/WP04-run-agent-message-and-worker.md](tasks/WP04-run-agent-message-and-worker.md)

**Subtasks:**
- [x] T024 `RunAgent` Messenger message (WP04)
- [x] T025 `RunAgentHandler` + worker-concurrency guard (WP04)
- [x] T026 `AgentRunService::enqueue()` + `runInline()` (WP04)
- [x] T027 `StalledRunReaper` service (WP04)
- [x] T028 Messenger transport wiring + `config.ai.providers` / scalar config entities (WP04)

**Risks:**
- Two workers race to start the same `AgentRun`. *Mitigation:* transport-level Messenger locking + `started_at IS NULL` precondition at handler entry (NFR-015).
- Reaper races a finishing handler. *Mitigation:* compare-and-swap on terminal-status check; reaper SHALL NOT regress a terminal row (C-014).

---

## WP05 — HTTP endpoints + SSE wiring

**Priority:** P0.
**Depends on:** WP-04.
**Goal:** Expose the four HTTP endpoints from `contracts/agent-run-api.yaml`, route them through `AgentRouteServiceProvider` in `packages/routing` (Layer 4), and push every SSE event from the doctrine vocabulary onto channel `agent.run.<id>` via `BroadcastStorage::push`.
**Independent test:** Acceptance test: HTTP 202 → `EventSource('/broadcast?channels=agent.run.<id>')` receives `run_started`, then `run_completed` within 30 s against `NullLlmProvider`. DELETE flips to `cancelled` within 3 iteration boundaries. POST `/approve` resolves an `awaiting_approval` row.
**Estimated prompt size:** ~470 lines.
**Prompt:** [tasks/WP05-http-endpoints-and-sse-wiring.md](tasks/WP05-http-endpoints-and-sse-wiring.md)

**Subtasks:**
- [x] T029 `AgentRunController` (4 endpoints) (WP05)
- [x] T030 `AgentRouteServiceProvider` (WP05)
- [x] T031 `AgentRunBroadcaster` + SSE event vocabulary (WP05)
- [x] T032 Per-route capability checks + initiator-ownership enforcement (WP05)
- [x] T033 Request validator: bundle vs `agent_id`, destructive_approval normalization (WP05)

**Risks:**
- Route layering violation if a Layer-5 file references router types. *Mitigation:* mirror `AuthOidcRouteServiceProvider` pattern (FR-031).
- `_account` request attribute misread as `account`. *Mitigation:* gotcha covered in CLAUDE.md — controller SHALL use `_account`.

---

## WP06 — CLI commands + scheduler entries

**Priority:** P1.
**Depends on:** WP-04 (uses `AgentRunService::runInline()` and the reaper / purge services).
**Goal:** Ship the three `ai:*` CLI commands and the scheduler entries that drive purge + reaper.
**Independent test:** `CommandTester` covers `ai:run "<prompt>" --inline` (returns within 10 s against `NullLlmProvider`), `ai:purge-runs` (deletes rows past retention), `ai:reap-stalled-runs` (flips stuck rows). Scheduler registration unit test asserts both entries are discoverable.
**Estimated prompt size:** ~360 lines.
**Prompt:** [tasks/WP06-cli-commands-and-scheduler.md](tasks/WP06-cli-commands-and-scheduler.md)

**Subtasks:**
- [x] T034 `AiRunCommand` (WP06)
- [x] T035 `AiPurgeRunsCommand` (WP06)
- [x] T036 `AiReapStalledRunsCommand` (WP06)
- [x] T037 Scheduler entries (daily purge + 5-min reaper) (WP06)

**Risks:**
- `--inline` + `interactive` HITL combo is meaningless. *Mitigation:* reject at parse time per spec edge case.
- Inline mode bypasses Messenger but MUST go through `RunAgentHandler`. *Mitigation:* `runInline()` invokes the handler in-process (FR-008).

**Parallel opportunities:** T035 and T036 are independent commands and can be implemented side-by-side.

---

## WP07 — `McpClientToolSource` (remote MCP consumption)

**Priority:** P1.
**Depends on:** WP-03 (needs `AgentTool` VO + executor wiring).
**Goal:** Adapt remote MCP servers (Streamable HTTP only, per C-008) into the local tool catalogue. Add `config.ai.mcp_servers` config entity, register tools with `tool.mcp.<server>.<name>` capabilities, and exercise via a stub MCP server.
**Independent test:** Stub Streamable-HTTP MCP server returns `tools/list` with two tools → `AgentTool` instances appear in `AttributeToolRegistry` under the configured `capability_prefix` → `tools/call` routes back to the stub.
**Estimated prompt size:** ~340 lines.
**Prompt:** [tasks/WP07-mcp-client-tool-source.md](tasks/WP07-mcp-client-tool-source.md)

**Subtasks:**
- [x] T038 Streamable-HTTP MCP client (WP07)
- [x] T039 `config.ai.mcp_servers` config entity (WP07)
- [x] T040 Capability-prefix mapping + tool registration (WP07)
- [x] T041 Stub-server integration test (WP07)

**Risks:**
- Remote server unavailable at boot. *Mitigation:* graceful degrade — tool drops from catalogue (spec edge case).
- Remote tool capability collisions. *Mitigation:* enforced prefix per server alias.

---

## WP08 — `ai-observability` listeners

**Priority:** P1.
**Depends on:** WP-04 (subscribes to events emitted by the handler).
**Goal:** Subscribe to AgentRun lifecycle events; capture per-provider-call token usage, USD cost via `ModelPriceTable`, per-tool invocation counts, wall-clock + per-iteration latency. Persist via `packages/telescope`.
**Independent test:** End-to-end run produces a Telescope record with input/output tokens, cost cents, tool invocation count, total wall-clock ms, per-iteration ms list.
**Estimated prompt size:** ~290 lines.
**Prompt:** [tasks/WP08-ai-observability-listeners.md](tasks/WP08-ai-observability-listeners.md)

**Subtasks:**
- [x] T042 `ModelPriceTable` (WP08)
- [x] T043 `AgentRunTelemetryListener` (WP08)
- [x] T044 Token/cost/tool-count/latency capture + Telescope persistence (WP08)

**Risks:**
- Unknown model: cost calculation returns NULL (per data-model). *Mitigation:* documented behaviour; downstream nil-safe.
- Listener crashes affect primary run. *Mitigation:* wrap in try-catch and log via `LoggerInterface` (CLAUDE.md gotcha on best-effort side effects).

---

## WP09 — Spec, docs, security review

**Priority:** P2 (wrap-up).
**Depends on:** WP-01 ... WP-08.
**Goal:** Update the docs that describe the new runtime (AI integration skill, `ai-agent` README), bullet the CHANGELOG `[Unreleased]` section, run the `security-review` skill on the aggregate PR, and file a v1 release-readiness checklist.
**Independent test:** Skill content renders; CHANGELOG has an `[Unreleased]` bullet referencing this mission; `docs/security/agent-executor-review.md` exists with at-least-one section; release-readiness checklist confirms every SC-001..010 + every NFR / gate.
**Estimated prompt size:** ~310 lines.
**Prompt:** [tasks/WP09-spec-docs-and-security-review.md](tasks/WP09-spec-docs-and-security-review.md)

**Subtasks:**
- [ ] T045 Update `skills/waaseyaa/ai-integration/SKILL.md` (WP09)
- [ ] T046 Rewrite `packages/ai-agent/README.md` (WP09)
- [ ] T047 CHANGELOG `[Unreleased]` bullet (WP09)
- [ ] T048 Run `security-review` skill + record output (WP09)
- [ ] T049 v1 release-readiness checklist (WP09)

**Risks:**
- Stale docs. *Mitigation:* every README must reference the doctrine spec as the source of truth.
- Security review finds a real issue. *Mitigation:* WP-09 may need to re-open earlier WPs; not all findings are deferrable.

**Parallel opportunities:** T045/T046/T047 are independent docs.

---

## MVP scope

**Minimum viable agent executor:** WP-01 + WP-02 + WP-03 + WP-04 + WP-06's `AiRunCommand` (`ai:run --inline`).
This proves the runtime end-to-end on the CLI without the HTTP surface, observability, remote MCP, or scheduled jobs.
For a fuller MVP that demonstrates the primary user flow, add WP-05 (HTTP + SSE).
WP-07 / WP-08 / WP-09 are valuable but not on the smallest path to a runnable agent.

## Quality gates (every WP)

`composer test` · `composer cs-check` · `composer phpstan` · `bin/check-package-layers` · `bin/check-dead-code` · `composer check-composer-policy` · bulk-edit gate (`occurrence_map.yaml` diff-compliance).

## Next step

Run `spec-kitty agent mission finalize-tasks --mission agent-executor-01KRWPK7 --json` to parse dependencies into WP frontmatter and commit the manifest. After that, the implement / review loop opens.
