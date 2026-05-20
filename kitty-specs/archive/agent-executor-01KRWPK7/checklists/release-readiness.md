# Agent Executor v1 — Release Readiness Checklist

Mission: `agent-executor-01KRWPK7`
Mission lead: `jonesrussell`
Date: **2026-05-18**

## Success Criteria

| ID | Criterion | Evidence |
|---|---|---|
| SC-001 | Submit an agent prompt via CLI or HTTP, receive a structured result with token / cost / tool-call audit data. | `tests/Integration/PhaseN/AgentRuntime/CliInlineRunTest.php` + `AsyncHttpRunTest.php`. `AgentRunController::create()` + `AiRunCommand`. |
| SC-002 | Watch an agent's progress in real time without polling. | `tests/Integration/PhaseN/AgentRuntime/AsyncHttpRunTest.php` asserts SSE event vocabulary on `agent.run.<id>` via `BroadcastStorage`. |
| SC-003 | Cancel an in-progress run and have the cancellation honoured within seconds. | `tests/Integration/PhaseN/AgentRuntime/CancellationTest.php` (NFR-003 threshold). |
| SC-004 | Prevent agents from performing destructive actions without explicit per-run approval. | `tests/Integration/PhaseN/AgentRuntime/InteractiveHitlTest.php`; HITL state machine `none` / `all` / `interactive` in `AgentExecutor`. |
| SC-005 | Reconstruct any past agent run for compliance review. | `AgentAuditLog` entity persists every `provider_call` / `tool_call` / `tool_result` / `approval` / `error`. Append-only outside `AiPurgeRunsCommand` (C-014). `tests/Integration/PhaseN/AgentRuntime/EntityPersistenceTest.php`. |
| SC-006 | Configure remote tool sources, grant per-tool capabilities, revoke access without code changes. | `config.ai.mcp_servers` config entity + `McpClientToolSource`. Capability prefix `tool.mcp.<server>.<name>`. `tests/Integration/PhaseN/AgentRuntime/McpClientToolSourceTest.php`. |
| SC-007 | Satisfy a retention policy by purging old runs through a scheduled job. | `AiPurgeRunsCommand` (CLI) + `tests/Integration/PhaseN/AgentRuntime/PurgeJobTest.php`. **Known deferral:** `AgentScheduleEntries` is defined but boot-time `register()` is not wired — see **#1512**. The command works today; operators can run it from their own cron until the wiring lands. |
| SC-008 | Recover from worker crashes without manual database surgery. | `StalledRunReaper` + `AiReapStalledRunsCommand`. `tests/Integration/PhaseN/AgentRuntime/ReaperTest.php`. Per NFR-004: 5-minute scheduler interval, gated by **#1512**. |
| SC-009 | Register a new agent or tool via attribute discovery, without modifying framework code. | `#[AsAgentDefinition]` + `#[AsAgentTool]` attributes. `packages/foundation/src/Discovery/PackageManifestCompiler.php` scans both. Unit tests in `packages/ai-tools/tests/Unit/Catalogue/AttributeToolRegistryTest.php` and `packages/ai-agent/tests/Unit/`. |
| SC-010 | Framework health gates all pass on the mission's final PR. | See **Quality gates** below. |

## NFR thresholds

| ID | Threshold | Measured / evidence |
|---|---|---|
| NFR-001 | Inline-mode CLI runs return within 10 s on a no-tool prompt. | `tests/Integration/PhaseN/AgentRuntime/CliInlineRunTest.php`. Measured: **35 ms** against `NullLlmProvider`. |
| NFR-002 | Async HTTP-enqueued runs complete end-to-end within 30 s on a no-tool prompt. | `tests/Integration/PhaseN/AgentRuntime/EnqueueAndConsumeTest.php`, `AsyncHttpRunTest.php`. Measured: **< 1 s** with a single worker. |
| NFR-003 | Cancellation latency ≤ 3 iteration boundaries + 1 in-flight tool call. | `tests/Integration/PhaseN/AgentRuntime/CancellationTest.php` — explicit assertion. |
| NFR-004 | Reaper detects stuck runs within 5 minutes (scheduler interval). | `tests/Integration/PhaseN/AgentRuntime/ReaperTest.php` exercises the reaper directly. **Known deferral:** the 5-minute scheduler binding is contingent on **#1512** (boot wiring). CLI invocation works today; the SLA depends on operator-driven cron until #1512 lands. |
| NFR-005 | Interactive HITL approvals time out within `hitl_timeout_seconds + 1 s` (default 301 s). | `tests/Integration/PhaseN/AgentRuntime/InteractiveHitlTest.php`. |
| NFR-006 | SSE delivery durable and replayable (at-least-once). | Every `BroadcastStorage::push()` is synchronous on the worker. `BroadcastRouter` supports `Last-Event-ID` resume. Verified by `AsyncHttpRunTest` and the broadcasting spec at `docs/specs/broadcasting.md`. |
| NFR-007 | Transcript persistence truncated at 262144 bytes (configurable); overflow marked `[truncated]`. | Test `transcriptTruncationMarkerAppendedOnceAtCap` in `packages/ai-agent/tests/Unit/`. |
| NFR-008 | Layer enforcement passes. | `bin/check-package-layers` — exit 0. |
| NFR-009 | Dead-code gate passes (no new findings). | `bin/check-dead-code` — exit 0; baseline unchanged. |
| NFR-010 | PHPStan level 5 passes. | `composer phpstan` — exit 0. |
| NFR-011 | Code style passes. | `composer cs-check` — exit 0. |
| NFR-012 | Test suite passes. | `composer test` — exit 0 across unit, contract, and integration. |
| NFR-013 | Composer policy gate passes. | `composer check-composer-policy` — exit 0. `packages/ai-tools` complies with CP002, CP003, CP-NEW. |
| NFR-014 | Bulk-edit gate passes; zero `BLOCK` rows. | `occurrence_map.yaml` validates against the schema. WP review reports show zero `BLOCK` rows in the diff-compliance report. |
| NFR-015 | Worker concurrency safe; two workers never run the same `AgentRun.id` twice. | `RunAgentHandler` CAS-guard test in `packages/ai-agent/tests/Unit/Message/RunAgentHandlerTest.php`; Messenger transport-level locking documented in spec § Worker contract. |

## Quality gates

All gates green on the aggregate mission diff (lanes a..h merged into `main` via WP-09 wrap-up):

| Gate | Command | Status |
|---|---|---|
| Layers | `bin/check-package-layers` | Pass |
| Dead-code | `bin/check-dead-code` | Pass (no new findings beyond `phpstan-dead-code-baseline.neon`) |
| Composer policy | `composer check-composer-policy` | Pass |
| PHPStan | `composer phpstan` (level 5) | Pass |
| CS-fix dry-run | `composer cs-check` | Pass |
| Tests | `composer test` (unit + contract + integration) | Pass |
| No secrets | `bin/check-no-secrets` | Pass |
| External consumers | `bin/check-external-consumers` | Pass — verified no downstream `Waaseyaa\AI\Agent\AgentInterface` / `McpToolDefinition` imports (per C-006). |
| Bulk-edit | Diff-compliance report against `occurrence_map.yaml` | Pass (zero `BLOCK` rows) |

`composer verify` (the umbrella) is the entry point operators run; tail of the final run should be re-pasted into this checklist by the merging engineer before tagging.

## Known deferrals

These follow-up issues are filed; none block v1 merge.

| Issue | Summary |
|---|---|
| **#1509** | Provider exception hierarchy — refine HTTP-provider exception classes so retry / circuit-breaker logic can discriminate by category. Current generic exception works correctly with the retry loop but is less expressive than ideal. |
| **#1510** | EventDispatcher wiring for `AgentRunTelemetryListener` — listener is implemented and unit-tested via direct invocation; the global dispatcher registration is the remaining piece. No security impact; telemetry is informational. |
| **#1511** | SSE broadcaster dedup keys + OpenAPI for `pending_approval` payload — incremental refinements to the SSE surface; the current API is functional. |
| **#1512** | Scheduler boot wiring — `AgentScheduleEntries` is defined and tested; the kernel-level `register()` call is not yet executed at boot. Affects SC-007 (purge) and NFR-004 (reaper interval). CLI commands work today; operators can run cron themselves until the wiring lands. |
| **#1513** | `--watch` SSE consumer for the CLI — quality-of-life enhancement; the HTTP SSE surface is fully functional today. |

## Sign-off

- **Mission lead:** `jonesrussell` — 2026-05-18
- **Per-WP reviewers:** `claude:opus:reviewer` approved WP-01..WP-08 (status: `approved` per `kitty-specs/agent-executor-01KRWPK7/status.json`).
- **WP-09 (this checklist + docs + security review):** filed by `claude:sonnet:implementer:implementer`; awaiting review.
- **Security review:** `docs/security/agent-executor-review.md` — all findings Pass; five non-blocking follow-ups filed.
