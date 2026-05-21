# Agent Executor v1.1 — Audit Follow-ups

**Mission:** `agent-executor-v1-1-audit-followups-01KS3S5M`
**Status:** Spec
**Target branch:** `main`
**Predecessor:** `agent-executor-01KRWPK7` (9/9 WPs done, awaiting close-out)
**Closes:** #1509, #1510, #1511, #1513

## Why this mission exists

The agent-executor mission landed the framework's first-class agent runtime: `AgentExecutor`, `RunAgentHandler`, broadcaster wiring, CLI commands, scheduler entries, OpenAPI shape, and the `AgentRunTelemetryListener` observability hook. Each WP's review cycle surfaced one or more loose threads — none blocked merge, all matter for v1.

The shared question across the four follow-ups: **does the agent runtime's externally observable contract match what the spec promised?** Today:

- **Retry semantics (#1509, WP03):** `AgentExecutor::callProviderWithRetry` (line 582) catches `RateLimitException` and retries; everything else falls through. FR-025 specified retry on 429 + 5xx + transport errors. The framework's `AnthropicProvider` throws bare `\RuntimeException` for both 4xx (non-429) and 5xx, so the executor cannot distinguish — it either over-retries client errors (bug class: 401s burning quota on retries) or under-retries server errors (depending on the catch chain). The fix is a typed exception hierarchy.
- **Observability events (#1510, WP08):** `AgentRunTelemetryListener` subscribes to five domain events (`AgentRunStarted`, `AgentRunIterationCompleted`, `AgentRunProviderCallCompleted`, `AgentRunToolCallObserved`, `AgentRunTerminated`). Neither `AgentExecutor` nor `RunAgentHandler` dispatch them. The listener has nothing to consume. Observability is wired at the listener end but unwired at the producer end — vaporware.
- **Broadcaster duplication (#1511, WP05):** `packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php` (WP04 stub) and `AgentRunBroadcaster.php` (WP05 canonical) are functionally identical wrappers around `BroadcastStorage::push("agent.run.{$runId}", ...)`. The adapter exists only as a WP04-era artifact that WP05 then overrides via `AgentRunBroadcasterServiceProvider`. Two classes, one job, one configuration race waiting to happen. Plus the `pending_approval` OpenAPI shape is half-aligned with the event payload it emits.
- **CLI --watch is a stub (#1513, WP06):** `AiRunCommand::runAsync` (line 159) prints `"SSE consumer would attach to /broadcast?channels=agent.run.<id> (--watch is informational here)"` and exits. Operators using `bin/waaseyaa ai:run "..." --watch` see no live progress — they have to `curl /broadcast` separately. FR-005 specified `--watch` as an actual SSE consumer. The current implementation is documentation, not behavior.

The mission's contract: after merge, `AgentExecutor` retries are spec-faithful and bounded; observability dashboards see live event flow from agent runs; one broadcaster class exists with one configuration path; `--watch` actually watches.

## User scenarios

### Primary flow: an operator runs an agent and watches it live

1. Operator runs `bin/waaseyaa ai:run "summarize this issue" --watch` from a terminal.
2. The command creates the agent-run record, enqueues `RunAgentHandler`, and **attaches an SSE consumer** to `/broadcast?channels=agent.run.<id>`.
3. As `AgentExecutor` progresses through iterations, the terminal prints event names + concise payloads: `iteration.started`, `provider.call.started`, `tool.call.observed`, `iteration.completed`, `terminated`.
4. When the run terminates (success or failure), the consumer disconnects cleanly and the command exits with an appropriate code.
5. Ctrl-C cleanly tears down the SSE consumer; the agent run continues server-side.

### Primary flow: an observability dashboard shows real agent telemetry

1. Operator opens the AI observability dashboard (out-of-mission UI, but the API must serve it).
2. They start an agent run.
3. The dashboard's metrics — tokens per provider call, latency per iteration, tool-call counts — update because `AgentRunTelemetryListener` is now receiving events from `AgentExecutor` and `RunAgentHandler` dispatching them.
4. Without this mission, those tiles stay at zero forever (the listener subscribes; nothing publishes).

### Recovery flow: a 4xx provider error does not burn retries

1. `AgentExecutor` is mid-iteration; the provider rejects the prompt with 400 Bad Request (e.g., malformed tool schema).
2. `AnthropicProvider` (or any provider) throws `ClientErrorException` (the new typed exception for 4xx non-429).
3. `callProviderWithRetry` catches it, sees it is **not** a transient class, and re-throws **without retry** — burning zero retries on a non-recoverable error.
4. The agent run terminates with a clear error category in the audit trail, not after exhausting the retry budget.

### Recovery flow: a 5xx provider error retries until budget exhausts

1. Provider returns 503 Service Unavailable during a call.
2. `AnthropicProvider` throws `TransportException` (or a 5xx-categorized class).
3. `callProviderWithRetry` catches it, classifies it as transient, retries per FR-025 (exponential backoff bounded by the configured budget).
4. If the provider recovers within budget, the run continues; if not, it terminates with the transient-error category recorded.

### Edge cases

- **Concurrent `--watch` runs.** Multiple terminals attached to the same channel each get the full stream; no consumer locks the channel.
- **Network drop mid-watch.** The SSE consumer reconnects (per existing `BroadcastRouter` cursor semantics) without losing events the broadcaster has not yet pruned.
- **Listener exception.** If `AgentRunTelemetryListener` throws while consuming an event, the dispatch path catches and logs via `LoggerInterface` — `AgentExecutor` does not crash because a listener failed (best-effort side effect per CLAUDE.md gotcha).
- **Double-dispatch.** A regression where two listeners both record `AgentRunStarted` produces idempotent metrics (the recorder dedupes by `runId + event_kind + sequence`) — covered by the existing `AgentRunMetricsRecorderInterface` contract; not a new failure mode this mission introduces.
- **`BroadcastStorageAdapter` removal coordination.** If any consumer outside `packages/ai-agent/` references `BroadcastStorageAdapter`, the deletion is staged via `@deprecated` first. Grep confirms scope before WP03 deletes the file.

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | A typed exception hierarchy for AI agent provider errors exists: `Waaseyaa\AI\Agent\Provider\TransportException` (network / 5xx / connection class — transient, retryable), `Waaseyaa\AI\Agent\Provider\ClientErrorException` (4xx non-429 — non-retryable), and the existing `RateLimitException` (429 — retryable with backoff). All three extend a common abstract `ProviderException` (or implement a marker interface — planner picks). |
| FR-002 | Mandatory | `AnthropicProvider` throws `TransportException` for 5xx and connection errors, `ClientErrorException` for 4xx non-429, `RateLimitException` for 429. No bare `\RuntimeException` for HTTP outcomes. |
| FR-003 | Mandatory | `AgentExecutor::callProviderWithRetry` catches `TransportException` and `RateLimitException` for retry; re-throws `ClientErrorException` and any other exception immediately. The retry budget is unchanged from FR-025 of the predecessor mission. |
| FR-004 | Mandatory | `AgentExecutor` dispatches `AgentRunStarted`, `AgentRunIterationCompleted`, `AgentRunProviderCallCompleted`, `AgentRunToolCallObserved`, `AgentRunTerminated` via the framework's `EventDispatcherInterface` at the lifecycle points named by each event. Dispatch is best-effort: a listener exception is logged but does not abort the run. |
| FR-005 | Mandatory | `RunAgentHandler` dispatches the same events when it owns the lifecycle point (e.g. terminated-by-supervisor, run-cancelled). Coordination between `AgentExecutor` and `RunAgentHandler` does not double-dispatch — exactly one dispatch per logical lifecycle event. |
| FR-006 | Mandatory | `Waaseyaa\AI\Agent\Broadcast\BroadcastStorageAdapter` is removed. `Waaseyaa\AI\Agent\Broadcast\AgentRunBroadcaster` is the canonical implementation. `AgentRunBroadcasterServiceProvider` binds `AgentRunBroadcasterInterface` to it. `MessagingServiceProvider` (or wherever the L0 messaging binding lives) no longer references the adapter. |
| FR-007 | Mandatory | The OpenAPI document (`packages/api/openapi.yaml` or equivalent path) for `pending_approval` state reflects what `AgentRunBroadcaster` actually emits: same field names, same enum values, same nullability. The pre-mission shape's drift items (named in #1511) are corrected. |
| FR-008 | Mandatory | `AiRunCommand::runAsync` attaches a working SSE consumer when `--watch` is set. The consumer connects to `/broadcast?channels=agent.run.<id>`, prints each event's name + payload (or a configurable compact form) to stdout as the run progresses, and exits cleanly when the run terminates. |
| FR-009 | Mandatory | Pressing Ctrl-C (SIGINT) during `--watch` tears down the SSE consumer without leaving HTTP connections open. The server-side agent run continues. |
| FR-010 | Mandatory | Unit test: `AgentExecutorRetryTest` covers each retry decision — `RateLimitException` retries, `TransportException` retries, `ClientErrorException` immediately re-thrown, generic exception immediately re-thrown. |
| FR-011 | Mandatory | Unit test: `AgentExecutorEventDispatchTest` covers each of the five domain events — happy path dispatches once, listener exception logs without aborting, sequence counter increments. |
| FR-012 | Mandatory | Unit test: `AgentRunBroadcasterTest` covers the canonical implementation; the deleted adapter's test file (if any) is removed in the same commit. |
| FR-013 | Mandatory | Unit test: `AiRunCommandWatchTest` covers the `--watch` path — successful connect + event consumption + clean termination; SIGINT handling (where testable). |
| FR-014 | Mandatory | Integration test under `tests/Integration/Phase??/AgentRunObservabilityTest.php` boots the kernel, executes a fake agent run that emits each event class once, and asserts the `AgentRunTelemetryListener`'s recorder receives all five events. |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | The new exception hierarchy adds no measurable p95 latency regression to provider calls (≤1% overhead — the change is type-narrowing in catch clauses, not behavioral). |
| NFR-002 | Mandatory | Event dispatch adds ≤2 ms median per lifecycle event to `AgentExecutor` iteration timing, measured against an existing or new agent-executor benchmark. |
| NFR-003 | Mandatory | `--watch` SSE consumer adds no server-side memory pressure beyond what `/broadcast` already imposes — the consumer is an HTTP client, not a new broadcaster. |
| NFR-004 | Mandatory | OpenAPI document changes are validated by the framework's existing OpenAPI lint / schema-validation step (whatever runs in `composer verify`). If no such step exists today, WP02's wrap-up adds a `bin/check-openapi` minimum (linting against a JSON schema). |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | No changes to the predecessor mission's public agent-run contract (`AgentRunInterface`, `AgentRunRepository`, the `RunAgentHandler` message shape). This mission tightens retry + observability + broadcaster + CLI surfaces — it does not refactor the entity model. |
| C-002 | Mandatory | The merge commit closes #1509, #1510, #1511, #1513 via `Closes #N` footer. |
| C-003 | Mandatory | The mission preserves the L0→L6 layer architecture. New exception classes live in `packages/ai-agent/src/Provider/` (L5). Event dispatch uses the foundation `EventDispatcherInterface` (L0). The CLI consumer lives in `packages/cli/src/Command/Ai/` (L6) and connects via HTTP — no upward import. |
| C-004 | Mandatory | `composer verify` is green on the merge commit. |
| C-005 | Mandatory | The predecessor mission `agent-executor-01KRWPK7` is archived as part of mission close-out (separate from this mission's PR — done at the end of the triage session). This mission's spec references the predecessor by archived path. |
| C-006 | Mandatory | No CI hooks bypassed during this mission's PRs. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | An operator's `--watch` invocation prints live events and terminates cleanly. | Integration test or manual smoke test recorded in WP04 (FR-008/009). |
| SC-002 | The AI observability listener receives all five event classes when an agent run executes. | Integration test `AgentRunObservabilityTest::dispatchesAllFiveLifecycleEvents` passes (FR-014). |
| SC-003 | A 4xx non-429 provider response does not consume retry budget. | `AgentExecutorRetryTest::clientErrorRethrownImmediately` passes (FR-010). |
| SC-004 | A 5xx provider response is retried until the configured budget exhausts. | `AgentExecutorRetryTest::transportErrorRetriedToBudget` passes. |
| SC-005 | `Waaseyaa\AI\Agent\Broadcast\BroadcastStorageAdapter` no longer exists. | `! [ -f packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php ]` and `grep -r BroadcastStorageAdapter packages/` returns no matches. |
| SC-006 | The OpenAPI document's `pending_approval` shape matches the broadcaster's emitted payload. | Schema validation step passes (NFR-004). |
| SC-007 | `composer verify` is green on the merge commit. | CI status check `verify` passes on the merging PR. |
| SC-008 | Issues #1509, #1510, #1511, #1513 close on merge. | GitHub auto-closes via `Closes #N` footer on the merge commit. |

## Key entities

| Entity | Role | Net change in this mission |
|---|---|---|
| `Waaseyaa\AI\Agent\Provider\TransportException` (new) | Transient provider error — retryable. | +1 file |
| `Waaseyaa\AI\Agent\Provider\ClientErrorException` (new) | 4xx non-429 — non-retryable. | +1 file |
| Abstract `ProviderException` or marker interface (planner picks) | Common ancestor for typed exception catch. | +1 file |
| `AnthropicProvider` | Throws the typed exceptions instead of bare `\RuntimeException`. | Edit. |
| `AgentExecutor` | Refines `callProviderWithRetry` to catch typed exceptions; dispatches lifecycle events. | Edit. |
| `RunAgentHandler` | Dispatches lifecycle events at its owned points. | Edit. |
| `BroadcastStorageAdapter` | Deleted. | -1 file. |
| `AgentRunBroadcaster` / `AgentRunBroadcasterServiceProvider` | Canonical implementation + binding. | Edit (DI cleanup). |
| OpenAPI document for `pending_approval` shape | Reconciled with broadcaster emission. | Edit. |
| `AiRunCommand` | Real SSE consumer for `--watch`. | Edit. |
| Unit tests (4 files) + integration test (1 file) | New regression coverage. | +5 files (or edits, planner picks). |
| `docs/specs/ai-pipeline.md` or equivalent | Document the exception hierarchy and event dispatch contract. | Edit. |
| `CHANGELOG.md` | `[Unreleased]` entry. | Edit. |

## Assumptions

- The framework's `EventDispatcherInterface` (foundation L0) is the right transport for the five domain events. The predecessor mission's `AgentRunTelemetryListener` already subscribes via Symfony EventDispatcher; this mission just lights up the producer side.
- The OpenAPI document path is canonical (`packages/api/openapi.yaml` or per the api package's README). The planner identifies the actual file early in WP02; if no canonical OpenAPI doc exists, WP02 elevates the documentation gap.
- `BroadcastStorageAdapter` is only referenced inside `packages/ai-agent/`. A grep in WP03 confirms before deletion; if external consumers exist (Minoo, other plugins), the planner stages with `@deprecated` first.
- The SSE consumer in WP04 uses the framework's existing HttpClient (`packages/http-client/`). No new transport library is introduced.
- Retry budget tuning is unchanged from FR-025 of the predecessor mission. The mission tightens *categorization*, not *budget*.

## Out of scope

- Provider implementations beyond `AnthropicProvider`. Other providers (OpenAI, etc.) adopt the new exception classes when added; this mission doesn't introduce them.
- Refactoring the agent-run state machine.
- Adding new lifecycle events beyond the five `AgentRunTelemetryListener` already subscribes to.
- Building observability dashboards or UI work in `packages/admin/`.
- New SSE channels or broadcast features.
- The `_broadcast_log` pruning gap (#1536) — covered by M-D, not M-A.

## WP outline (for /spec-kitty.plan)

The planner is free to revise. Indicative shape:

- **WP01 — Provider exception hierarchy + retry semantics.** Introduce `TransportException`, `ClientErrorException`, and the common ancestor. Update `AnthropicProvider`. Update `AgentExecutor::callProviderWithRetry`. Unit tests (FR-010). Closes #1509.
- **WP02 — Event dispatch from AgentExecutor + RunAgentHandler.** Wire the five `EventDispatcherInterface::dispatch()` calls at the FR-004/005 lifecycle points. Coordinate to avoid double-dispatch. Unit + integration tests (FR-011, FR-014). Reconcile the `pending_approval` OpenAPI shape (FR-007, NFR-004). Closes #1510.
- **WP03 — Broadcaster consolidation.** Grep for any external references to `BroadcastStorageAdapter`. If none, delete the file; update `MessagingServiceProvider`. If references exist, `@deprecated` first; deletion lands in a follow-up. Unit tests (FR-012). Closes #1511.
- **WP04 — AiRunCommand --watch SSE consumer.** Build the consumer using `packages/http-client/`. Wire SIGINT handling. Unit test (FR-013). Smoke-test recorded in the WP. Closes #1513.
- **WP05 — Wrap-up.** Update `docs/specs/ai-pipeline.md` (or canonical AI spec the planner identifies) with the exception hierarchy + event dispatch contract. `CHANGELOG.md` entry. Full `composer verify` green.

## References

- Predecessor mission: `kitty-specs/agent-executor-01KRWPK7/` (awaiting close-out at the end of the triage session).
- Per-WP review citations: each issue body names its source WP (WP03 for #1509, WP08 for #1510, WP05 for #1511, WP06 for #1513).
- `packages/ai-observability/src/Listener/AgentRunTelemetryListener.php` — the listener whose events FR-004/005 light up.
- CLAUDE.md gotcha: "Best-effort side effects" (event listeners wrap in try-catch and log via `LoggerInterface` — FR-004 source).
- Memory: `feedback_modern_php_rules.md` — typed interfaces only (FR-001/002 source).
- Memory: `feedback_regression_tests.md` — always write regression tests (FR-010..FR-014).
