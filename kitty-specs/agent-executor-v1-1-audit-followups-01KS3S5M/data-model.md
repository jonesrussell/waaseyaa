# Data Model: Agent Executor v1.1 — Audit Follow-ups

**Mission**: `agent-executor-v1-1-audit-followups-01KS3S5M`
**Date**: 2026-05-20

## Exception Hierarchy

```
\RuntimeException
└── Waaseyaa\AI\Agent\Provider\ProviderException (abstract)
    ├── Waaseyaa\AI\Agent\Provider\RateLimitException   (existing — edit to extend ProviderException)
    ├── Waaseyaa\AI\Agent\Provider\TransportException   (new — 5xx / network / connection)
    └── Waaseyaa\AI\Agent\Provider\ClientErrorException (new — 4xx non-429)
```

### ProviderException (abstract)
```php
namespace Waaseyaa\AI\Agent\Provider;

abstract class ProviderException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
```

### TransportException
```php
/** Transient provider error (5xx, network, connection). Retryable. */
final class TransportException extends ProviderException {}
```

### ClientErrorException
```php
/** Client error (4xx non-429). Non-retryable — programmer error, not transient. */
final class ClientErrorException extends ProviderException {}
```

### RateLimitException (edit)
```php
/** 429 rate limit. Retryable with backoff. */
final class RateLimitException extends ProviderException {}
```

---

## Event Domain Objects (existing — no model change)

These five classes already exist (dispatched to `AgentRunTelemetryListener`). This mission adds the **producer-side dispatch** only.

| Event class | Dispatch owner | Lifecycle point |
|---|---|---|
| `AgentRunStarted` | `RunAgentHandler` | Before executor starts |
| `AgentRunIterationCompleted` | `AgentExecutor` | End of each iteration |
| `AgentRunProviderCallCompleted` | `AgentExecutor` | After each provider response |
| `AgentRunToolCallObserved` | `AgentExecutor` | After each tool call |
| `AgentRunTerminated` | `AgentExecutor` (normal) / `RunAgentHandler` (abnormal) | Run ends |

---

## AgentRun `pending_approval` Broadcast Payload Shape

Emitted by `AgentRunBroadcaster::push()` on the `agent.run.{$runId}` channel.

```
{
  "run_id":     string (UUID),
  "state":      "pending_approval",
  "iteration":  integer (≥1),
  "tool_calls": array of { "name": string, "input": object },
  "requires":   { "approval_kind": string, "prompt": string },
  "timestamp":  string (ISO 8601 UTC)
}
```

**Field alignment with OpenAPI (FR-007)**:
- `run_id`: string, format uuid — previously drifted as `id` in some drafts; canonical name is `run_id`.
- `state`: enum `["pending_approval"]` — only this state triggers the pending_approval payload.
- `tool_calls[].name`: string — previously nullable in draft shape; non-nullable in broadcaster emission.
- `requires.approval_kind`: string enum `["tool_call", "escalation"]` — was missing from draft shape.
- `timestamp`: ISO 8601 UTC string — previously missing from draft shape entirely.

---

## Retry Decision Matrix

| Exception type | Retried? | Reasoning |
|---|---|---|
| `RateLimitException` | Yes (with backoff) | 429 — transient quota, recoverable |
| `TransportException` | Yes (with backoff) | 5xx / network — transient infrastructure |
| `ClientErrorException` | No — rethrow immediately | 4xx non-429 — programmer error, not recoverable by retry |
| `\Throwable` (other) | No — rethrow immediately | Unknown; conservative default |

---

## AiRunCommand --watch State Machine

```
[start] → dispatch RunAgentHandler → open SSE stream to /broadcast?channels=agent.run.<id>
            ↓
         [reading events]
            ├── data: {..., "event": "iteration.started"}  → print to stdout
            ├── data: {..., "event": "provider.call.started"} → print
            ├── data: {..., "event": "tool.call.observed"} → print
            ├── data: {..., "event": "iteration.completed"} → print
            └── data: {..., "event": "terminated", "exit_code": N} → close stream, exit N
            ↑
         [SIGINT received at any point] → set $watching = false → close stream, exit 0
```

---

## Files Created / Edited

| Action | Path |
|---|---|
| NEW | `packages/ai-agent/src/Provider/ProviderException.php` |
| NEW | `packages/ai-agent/src/Provider/TransportException.php` |
| NEW | `packages/ai-agent/src/Provider/ClientErrorException.php` |
| EDIT | `packages/ai-agent/src/Provider/RateLimitException.php` (extend ProviderException) |
| EDIT | `packages/ai-agent/src/Provider/AnthropicProvider.php` |
| EDIT | `packages/ai-agent/src/Provider/OpenAiCompatibleProvider.php` (if applicable) |
| EDIT | `packages/ai-agent/src/AgentExecutor.php` |
| EDIT | `packages/ai-agent/src/RunAgentHandler.php` |
| DELETE | `packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php` |
| EDIT | `packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php` |
| EDIT | `packages/ai-agent/src/Broadcast/AgentRunBroadcasterServiceProvider.php` |
| EDIT | `packages/ai-agent/src/MessagingServiceProvider.php` |
| NEW | `packages/api/openapi.yaml` |
| NEW | `bin/check-openapi` |
| EDIT | `packages/cli/src/Command/Ai/AiRunCommand.php` |
| EDIT | `docs/specs/ai-pipeline.md` (or canonical AI spec) |
| EDIT | `CHANGELOG.md` |
| NEW | `packages/ai-agent/tests/Unit/Provider/AgentExecutorRetryTest.php` |
| NEW | `packages/ai-agent/tests/Unit/AgentExecutorEventDispatchTest.php` |
| NEW/EDIT | `packages/ai-agent/tests/Unit/Broadcast/AgentRunBroadcasterTest.php` |
| NEW | `packages/cli/tests/Unit/Command/Ai/AiRunCommandWatchTest.php` |
| NEW | `tests/Integration/Phase11/AgentRunObservabilityTest.php` |
| NEW | `.spectral.yaml` |
