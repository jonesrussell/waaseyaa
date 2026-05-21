# Research: Agent Executor v1.1 — Audit Follow-ups

**Mission**: `agent-executor-v1-1-audit-followups-01KS3S5M`
**Date**: 2026-05-20

## Finding 1 — Exception hierarchy shape

**Decision**: Abstract class `ProviderException extends \RuntimeException` as the common ancestor. `TransportException` and `ClientErrorException` extend it. `RateLimitException` (existing) is updated to also extend `ProviderException`.

**Rationale**: Abstract class allows a single `catch (ProviderException $e)` clause in defensive catch-all scenarios. Extending `\RuntimeException` keeps exceptions unchecked (PHP convention) and maintains backward compatibility for any caller already catching `\RuntimeException`. The three-class shape maps 1:1 to FR-001.

**Alternatives considered**:
- Marker interface — rejected; requires two catch clauses or `instanceof` union checks; cannot be caught as a base type in a single `catch` clause without `|` union syntax.
- Named constructor pattern instead of inheritance — rejected; PHP's exception mechanism requires class hierarchy for catch semantics.

**Code location**: `packages/ai-agent/src/Provider/`

---

## Finding 2 — Retry decision matrix

**Decision**: `callProviderWithRetry` catches `TransportException` and `RateLimitException` for retry (with exponential backoff, budget unchanged from FR-025 of predecessor). `ClientErrorException` and any other exception are re-thrown immediately without consuming retry budget.

**Rationale**: FR-003 is explicit. The key insight: 4xx non-429 errors are programmer errors (malformed request, invalid tool schema, bad auth) — retrying them wastes quota and provides no recovery path. 5xx / network errors are transient by definition.

**Catch order in `callProviderWithRetry`**:
```
catch (ClientErrorException $e) → rethrow immediately
catch (TransportException | RateLimitException $e) → retry with backoff
catch (\Throwable $e) → rethrow immediately (unknown exceptions not retried)
```

---

## Finding 3 — Event dispatch ownership and double-dispatch prevention

**Decision**: Ownership split by class responsibility:
- `AgentExecutor` dispatches: `AgentRunStarted`, `AgentRunIterationCompleted`, `AgentRunProviderCallCompleted`, `AgentRunToolCallObserved`, `AgentRunTerminated` (normal termination paths — max iterations reached, task complete).
- `RunAgentHandler` dispatches: `AgentRunTerminated` **only** for its owned paths (supervisor kill, job cancelled before executor starts, job timeout).

**Coordination invariant**: A PHPDoc `@dispatches` annotation on each dispatch site names the owning class. A unit test in `AgentExecutorEventDispatchTest` asserts that a full happy-path run dispatches `AgentRunTerminated` exactly once.

**Best-effort pattern** (CLAUDE.md gotcha compliance):
```php
try {
    $this->dispatcher->dispatch($event);
} catch (\Throwable $e) {
    $this->logger?->error('Event dispatch failed', ['event' => $event::class, 'error' => $e->getMessage()]);
}
```
Applied at every dispatch call site inside `AgentExecutor`. `RunAgentHandler` follows the same pattern.

---

## Finding 4 — OpenAPI document bootstrap

**Decision**: Create `packages/api/openapi.yaml` as OpenAPI 3.1.0. Minimum viable content: the `AgentRun` schema component with `pending_approval` state fields matching what `AgentRunBroadcaster` actually emits.

**`bin/check-openapi` implementation**: Use `npx @stoplight/spectral-cli@6 lint packages/api/openapi.yaml --ruleset .spectral.yaml` (Spectral is the de-facto OpenAPI linter, no Composer equivalent). Add `.spectral.yaml` at repo root with `extends: spectral:oas` ruleset. Add `npx` call to `composer verify` via a `@check-openapi` script entry.

**Rationale**: NFR-004 explicitly requires this gate be added if absent. Spectral is the industry-standard OpenAPI linter, available without a new Composer dependency (npx is already available in CI via Node.js). The `packages/api/` directory is the canonical home per the spec's assumption.

**Alternatives considered**:
- PHP-native OpenAPI validator — no maintained Composer package that covers OAS 3.1; rejected.
- Swagger 2.0 — rejected; OpenAPI 3.1.0 is current standard and aligns with JSON Schema for type assertions.
- Generate from PHP attributes — deferred; not in scope (C-001 prohibits touching entity model shape).

---

## Finding 5 — BroadcastStorageAdapter removal scope

**Decision**: Delete outright. No `@deprecated` staging.

**Grep result** (authoritative, run 2026-05-20):
```
packages/ai-agent/src/Broadcast/AgentRunBroadcasterInterface.php
packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php
packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php
packages/ai-agent/src/Broadcast/AgentRunBroadcasterServiceProvider.php
packages/ai-agent/src/MessagingServiceProvider.php
```
All five references are inside `packages/ai-agent/`. Zero external consumers.

**Rationale**: Charter DIR-003 (Greenfield Removal Policy) — during alpha, the old pattern is removed outright. No deprecation window. No `Legacy*` namespace. This is a WP04-era stub that WP05 (of the predecessor) then overrode via `AgentRunBroadcasterServiceProvider`. The canonical class is `AgentRunBroadcaster`.

---

## Finding 6 — SSE consumer in AiRunCommand

**Decision**: Use `packages/http-client/` L0 abstraction for streaming GET to `/broadcast?channels=agent.run.<id>`. Parse SSE `data:` lines. Print `[{event}] {payload}` to stdout. Block until `terminated` event or SIGINT. Use `pcntl_signal(SIGINT, ...)` for clean teardown.

**SSE parsing**: Standard SSE format — lines starting with `data:` contain the payload; blank line signals end of event. PHP `fgets()` / `readline()` on the streaming response body. The existing `BroadcastRouter` cursor semantics handle reconnection; `--watch` does not need to implement cursor logic (the HTTP client reconnects automatically on transport error).

**SIGINT handling**: Register `pcntl_async_signals(true)` + `pcntl_signal(SIGINT, function() { $this->watching = false; })`. The main loop checks `$this->watching`. On false, close the stream cleanly, print "Watch terminated." and exit 0.

**Rationale**: `packages/http-client/` is the framework's own HTTP abstraction (layer 0). No new transport library (C-003: CLI package is L6, connects via HTTP). `pcntl_signal` is available in PHP CLI; not available in FPM but this command only runs from CLI.

**Alternatives considered**:
- Symfony HttpClient directly in `packages/cli/` — rejected; adds a Symfony dependency to CLI if not already present, bypasses the framework's own abstraction.
- ReactPHP event loop — rejected; new dependency, over-engineering for a CLI consumer.
