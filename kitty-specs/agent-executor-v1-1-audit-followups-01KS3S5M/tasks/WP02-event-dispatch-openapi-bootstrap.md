---
work_package_id: WP02
title: Event Dispatch + OpenAPI Bootstrap
dependencies:
- WP01
requirement_refs:
- FR-004
- FR-005
- FR-007
- FR-011
- FR-014
- NFR-002
- NFR-004
- C-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T009
- T010
- T011
- T012
- T013
- T014
- T015
- T016
- T017
- T018
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "767088"
history:
- date: '2026-05-20T23:57:13Z'
  event: created
authoritative_surface: packages/ai-agent/src/Message/
execution_mode: code_change
owned_files:
- packages/ai-agent/src/Message/RunAgentHandler.php
- packages/ai-agent/tests/Unit/AgentExecutorEventDispatchTest.php
- packages/api/openapi.yaml
- bin/check-openapi
- composer.json
- tests/Integration/AgentRun/AgentRunObservabilityTest.php
tags: []
---

# WP02 — Event Dispatch + OpenAPI Bootstrap

**Closes**: #1510
**Depends on**: WP01 (exception types must be stable before adding new imports to `AgentExecutor`)
**Implement command**: `spec-kitty agent action implement WP02 --agent <name>`

## Objective

Wire the five `AgentRunTelemetryListener` domain events into `AgentExecutor` and `RunAgentHandler` so the observability listener actually receives events. Additionally, bootstrap the canonical `packages/api/openapi.yaml` document and add a `bin/check-openapi` linting gate to `composer verify`.

## Context

`AgentRunTelemetryListener` (in `packages/ai-observability/`) subscribes to five events:
- `AgentRunStarted`
- `AgentRunIterationCompleted`
- `AgentRunProviderCallCompleted`
- `AgentRunToolCallObserved`
- `AgentRunTerminated`

These classes live in `packages/ai-observability/src/Event/`. Neither `AgentExecutor` nor `RunAgentHandler` currently dispatch any of them — the listener is wired at the consumer end but has no producer. This WP lights up the producer side.

**Ownership split** (Decision 2 from plan.md):
- `AgentExecutor` owns: `AgentRunStarted`, `AgentRunIterationCompleted`, `AgentRunProviderCallCompleted`, `AgentRunToolCallObserved`, and `AgentRunTerminated` (normal completion).
- `RunAgentHandler` owns: `AgentRunTerminated` for its paths — supervisor kill, run cancelled before executor starts, abnormal handler-level failure.
- A single-dispatch invariant is enforced by code comments + PHPDoc, not a runtime lock (NFR-002: no latency-adding locks).

**Best-effort dispatch** (Decision 3 from plan.md):
Wrap each `$dispatcher->dispatch($event)` in a try-catch. Log the exception via injected `?LoggerInterface` (nullable, defaults to `NullLogger`). Do NOT let a listener exception abort the run.

**OpenAPI gap** (plan.md §OpenAPI gap):
No `openapi.yaml` exists outside vendor. This WP creates `packages/api/openapi.yaml` as an OpenAPI 3.1.0 minimum viable document covering the `pending_approval` AgentRun shape. `bin/check-openapi` validates it; the script is added to `composer verify`.

## Subtasks

### T009 — Inject `EventDispatcherInterface` into `AgentExecutor`

**File**: `packages/ai-agent/src/AgentExecutor.php`

**Purpose**: `AgentExecutor` needs access to the foundation `EventDispatcherInterface` (L0) to dispatch domain events. It must be injected via constructor DI, not pulled from a container.

**Steps**:
1. Add `use` import: `use Waaseyaa\Foundation\EventDispatcher\EventDispatcherInterface;`
2. Also add: `use Waaseyaa\Foundation\Log\LoggerInterface;` and `use Waaseyaa\Foundation\Log\NullLogger;`
3. In `__construct`, add two nullable parameters:
   ```php
   private readonly ?EventDispatcherInterface $eventDispatcher = null,
   private readonly ?LoggerInterface $dispatchLogger = null,
   ```
   (Place after existing required parameters to maintain backward compatibility.)
4. No service provider change needed in this WP — the dispatcher injection is wired by the existing `AiAgentServiceProvider` or by WP03/WP05 if it touches DI. For now, the constructor accepts `null` and dispatches are no-ops when null (safe default).

**Note**: If `AgentExecutor` already has a `LoggerInterface` parameter, reuse it for dispatch logging rather than adding a second.

**Validation**:
- [ ] Constructor compiles with new nullable parameters
- [ ] Existing `AgentExecutorTest` still passes (null defaults keep backward compat)

---

### T010 — Dispatch `AgentRunStarted` + `AgentRunTerminated` from `AgentExecutor`

**File**: `packages/ai-agent/src/AgentExecutor.php`

**Purpose**: The two boundary events bracket the entire agent run.

**Steps**:
1. Add `use` imports for both event classes:
   ```php
   use Waaseyaa\AI\Observability\Event\AgentRunStarted;
   use Waaseyaa\AI\Observability\Event\AgentRunTerminated;
   ```
   (Verify exact namespace by reading `packages/ai-observability/src/Event/AgentRunStarted.php`.)
2. Identify the entry point of the run loop in `AgentExecutor` (likely a `run(string $runId, ...)` or `execute(...)` method).
3. At the start of the run, dispatch `AgentRunStarted`:
   ```php
   $this->dispatchSafely(new AgentRunStarted(runId: $runId, /* other constructor params */));
   ```
4. At the successful end of the run (normal `AgentRunTerminated`), dispatch:
   ```php
   $this->dispatchSafely(new AgentRunTerminated(runId: $runId, outcome: 'success', /* etc */));
   ```
5. Add private helper `dispatchSafely`:
   ```php
   private function dispatchSafely(object $event): void
   {
       if ($this->eventDispatcher === null) {
           return;
       }
       try {
           $this->eventDispatcher->dispatch($event);
       } catch (\Throwable $e) {
           ($this->dispatchLogger ?? new NullLogger())->error(
               'AgentExecutor: event dispatch failed for ' . $event::class . ': ' . $e->getMessage()
           );
       }
   }
   ```
6. Consult `AgentRunStarted` constructor signature to pass correct parameters.

**Validation**:
- [ ] `AgentRunStarted` dispatched at run entry point
- [ ] `AgentRunTerminated` dispatched at normal completion
- [ ] `dispatchSafely` catches listener exceptions and logs without re-throwing

---

### T011 — Dispatch `AgentRunIterationCompleted` from `AgentExecutor`

**File**: `packages/ai-agent/src/AgentExecutor.php`

**Purpose**: Emitted at the end of each iteration (one LLM call + tool-call cycle).

**Steps**:
1. Add `use` import: `use Waaseyaa\AI\Observability\Event\AgentRunIterationCompleted;`
2. Identify the iteration loop in `AgentExecutor` (the outer while/for loop over `$iteration`).
3. At the bottom of each iteration (after tool calls resolved, before next iteration begins):
   ```php
   $this->dispatchSafely(new AgentRunIterationCompleted(
       runId: $runId,
       iteration: $iteration,
       // other params per constructor
   ));
   ```
4. Consult `AgentRunIterationCompleted` constructor for exact parameters.

**Validation**:
- [ ] Event dispatched once per iteration at the bottom of the loop
- [ ] Does not fire on the same path as `AgentRunTerminated` (no double-dispatch)

---

### T012 — Dispatch `AgentRunProviderCallCompleted` + `AgentRunToolCallObserved` from `AgentExecutor`

**File**: `packages/ai-agent/src/AgentExecutor.php`

**Purpose**: Fine-grained events for observability dashboard metrics (tokens per call, tool-call counts).

**Steps**:
1. Add `use` imports:
   ```php
   use Waaseyaa\AI\Observability\Event\AgentRunProviderCallCompleted;
   use Waaseyaa\AI\Observability\Event\AgentRunToolCallObserved;
   ```
2. **`AgentRunProviderCallCompleted`**: Dispatch after the `callProviderWithRetry` call returns a `MessageResponse`:
   ```php
   $response = $this->callProviderWithRetry($provider, $request, $runId, $iteration);
   $this->dispatchSafely(new AgentRunProviderCallCompleted(
       runId: $runId,
       iteration: $iteration,
       // tokens, latency from response if available
   ));
   ```
3. **`AgentRunToolCallObserved`**: Dispatch in the tool-call dispatch block (around line 205 "Tool-call dispatch"):
   ```php
   foreach ($toolCalls as $toolCall) {
       // existing tool dispatch ...
       $this->dispatchSafely(new AgentRunToolCallObserved(
           runId: $runId,
           toolName: $toolCall->name,
           // other params
       ));
   }
   ```
4. Consult each event class constructor for required parameters.

**Validation**:
- [ ] `AgentRunProviderCallCompleted` dispatched once after each provider call returns
- [ ] `AgentRunToolCallObserved` dispatched once per tool call in the loop

---

### T013 — Dispatch `AgentRunTerminated` from `RunAgentHandler` (owned paths only)

**File**: `packages/ai-agent/src/Message/RunAgentHandler.php`

**Purpose**: `RunAgentHandler` owns two termination paths that don't go through `AgentExecutor`: (a) run cancelled before executor starts, (b) abnormal handler-level failure (executor throws unexpectedly). These must dispatch `AgentRunTerminated` so the telemetry listener always receives a terminal event.

**Steps**:
1. Add `use` imports to `RunAgentHandler`:
   ```php
   use Waaseyaa\Foundation\EventDispatcher\EventDispatcherInterface;
   use Waaseyaa\AI\Observability\Event\AgentRunTerminated;
   use Waaseyaa\Foundation\Log\LoggerInterface;
   use Waaseyaa\Foundation\Log\NullLogger;
   ```
2. Inject `?EventDispatcherInterface $eventDispatcher = null` in `RunAgentHandler::__construct`.
3. Add `dispatchSafely` helper (same pattern as `AgentExecutor`'s private helper — or extract to a trait if both classes use it).
4. Identify paths in `RunAgentHandler` where the run ends without calling `AgentExecutor`:
   - Pre-executor cancellation check (if run is already cancelled)
   - `catch` blocks that handle fatal handler errors after executor has NOT yet been called
5. At each such path, dispatch:
   ```php
   $this->dispatchSafely(new AgentRunTerminated(
       runId: $runId,
       outcome: 'cancelled', // or 'error'
   ));
   ```
6. Add ownership comment in BOTH `AgentExecutor` and `RunAgentHandler`:
   ```php
   // DISPATCH OWNERSHIP: AgentExecutor dispatches AgentRunTerminated for normal-completion paths.
   // RunAgentHandler dispatches AgentRunTerminated only for supervisor-kill and pre-executor cancellation.
   // Exactly one AgentRunTerminated per agent run (FR-005).
   ```
7. C-001 constraint: Do NOT change the `RunAgentHandler` message shape or `AgentRunInterface`. Only add the dispatcher injection and event dispatch calls.

**Validation**:
- [ ] `RunAgentHandler` injects `?EventDispatcherInterface`
- [ ] Dispatch only on handler-owned termination paths (not after `AgentExecutor::execute()` returns normally)
- [ ] Ownership comment present in both files
- [ ] Existing `RunAgentHandler` tests still pass (null default keeps backward compat)

---

### T014 — Write `AgentExecutorEventDispatchTest` (FR-011)

**File**: `packages/ai-agent/tests/Unit/AgentExecutorEventDispatchTest.php`

**Purpose**: Unit regression coverage for the five event dispatches.

**Steps**:
1. Create `packages/ai-agent/tests/Unit/AgentExecutorEventDispatchTest.php`.
2. Use `#[Test]`, `#[CoversClass(AgentExecutor::class)]` attributes.
3. Write these test methods:

   **Test 1 — `dispatchesAllFiveEventsOnHappyPath`**:
   - Create a spy/mock `EventDispatcherInterface` that records dispatched events.
   - Run `AgentExecutor::execute(...)` with a stub provider that returns one tool-call and then a final response (ensuring `AgentRunToolCallObserved` fires).
   - Assert all five event classes were dispatched exactly once (or once per iteration for repeating events).

   **Test 2 — `listenerExceptionDoesNotAbortRun`**:
   - Create a mock `EventDispatcherInterface` that throws `\RuntimeException` on every `dispatch()` call.
   - Run `AgentExecutor::execute(...)` with a stub provider.
   - Assert the run completes normally (no exception propagates from executor).
   - Assert the logger received at least one `error` call (if logger mock is used).

   **Test 3 — `sequenceCounterIncrementsPerIteration`**:
   - Run a multi-iteration scenario (provider returns tool calls on iteration 1, final answer on iteration 2).
   - Assert `AgentRunIterationCompleted` was dispatched twice (once per iteration).

4. For test isolation, use `createStub()` for void-return mocks, `createMock()` for expectations.

**Validation**:
- [ ] Three test methods present and passing
- [ ] `listenerExceptionDoesNotAbortRun` passes without exception propagation
- [ ] `dispatchesAllFiveEventsOnHappyPath` verifies all five event classes

---

### T015 — Write `AgentRunObservabilityTest` integration test (FR-014)

**File**: `tests/Integration/AgentRun/AgentRunObservabilityTest.php`

**Purpose**: Full-stack integration test that boots the kernel, executes a fake agent run, and asserts the `AgentRunTelemetryListener` recorder receives all five events.

**Steps**:
1. Create `tests/Integration/AgentRun/AgentRunObservabilityTest.php`.
2. Use `#[CoversNothing]` (integration test — no specific class coverage).
3. Boot the framework kernel with SQLite in-memory (`DBALDatabase::createSqlite(':memory:')`).
4. Register a test `AgentRunMetricsRecorder` (or use a spy on the existing one) that captures received events.
5. Dispatch a fake agent run through the full stack: create `AgentRunDraft`, dispatch `RunAgentMessage`, let `RunAgentHandler` + `AgentExecutor` execute with a `NullLlmProvider` or stub provider that returns a canned response.
6. After execution, assert:
   - `AgentRunStarted` received once
   - `AgentRunIterationCompleted` received at least once
   - `AgentRunProviderCallCompleted` received at least once
   - `AgentRunTerminated` received exactly once
   - (Optional if triggerable with stub: `AgentRunToolCallObserved` received)
7. Test method name: `dispatchesAllFiveLifecycleEvents` (matches SC-002).

**Note**: If the kernel boot pattern is not obvious, look at `tests/Integration/` for existing Phase tests that boot the kernel. Use `DBALDatabase::createSqlite(':memory:')` for in-memory storage.

**Validation**:
- [ ] Test boots kernel without error
- [ ] All five event classes recorded by the listener
- [ ] Test is deterministic (no network, no filesystem outside `:memory:`)

---

### T016 — Create `packages/api/openapi.yaml` bootstrap document

**File**: `packages/api/openapi.yaml`

**Purpose**: The spec (NFR-004) requires a canonical OpenAPI document. No such file exists. This is the minimum viable bootstrap covering the `pending_approval` AgentRun shape emitted by `AgentRunBroadcaster`.

**Steps**:
1. Confirm `packages/api/` directory exists; create `packages/api/openapi.yaml`.
2. Content structure (OpenAPI 3.1.0):
   ```yaml
   openapi: "3.1.0"
   info:
     title: "Waaseyaa Framework API"
     version: "0.1.0"
     description: "Canonical OpenAPI document for the Waaseyaa Framework public API surface."
   
   paths:
     /api/agent-run/{id}/events:
       get:
         summary: "SSE stream of agent-run lifecycle events"
         description: "Server-Sent Events stream for a given agent run. Clients subscribe via /broadcast?channels=agent.run.{id}."
         parameters:
           - name: id
             in: path
             required: true
             schema:
               type: string
               format: uuid
         responses:
           "200":
             description: "SSE stream"
             content:
               text/event-stream:
                 schema:
                   $ref: "#/components/schemas/AgentRunEvent"
   
   components:
     schemas:
       AgentRunEvent:
         type: object
         required: [event, run_id]
         properties:
           event:
             type: string
             enum:
               - agent.run.started
               - agent.run.iteration.completed
               - agent.run.provider_call.completed
               - agent.run.tool_call.observed
               - agent.run.terminated
           run_id:
             type: string
             format: uuid
           payload:
             type: object
             nullable: true
   
       AgentRunPendingApproval:
         type: object
         description: "Shape emitted by AgentRunBroadcaster for the pending_approval state."
         required: [run_id, state, awaiting_tool]
         properties:
           run_id:
             type: string
             format: uuid
           state:
             type: string
             enum: [pending_approval]
           awaiting_tool:
             type: string
             description: "Name of the tool awaiting human approval."
           requested_at:
             type: string
             format: date-time
             nullable: true
   ```
3. Adjust the `pending_approval` shape fields to exactly match what `AgentRunBroadcaster::push()` emits. Read `packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php` to confirm the actual payload keys.
4. Confirm the `pending_approval` shape drift items named in #1511 are corrected here (field names, enum values, nullability).

**Validation**:
- [ ] File passes `bin/check-openapi` (once written in T017)
- [ ] `pending_approval` shape matches broadcaster emission (SC-006)
- [ ] OpenAPI 3.1.0 format

---

### T017 — Create `bin/check-openapi` lint script

**File**: `bin/check-openapi`

**Purpose**: Executable shell script that validates `packages/api/openapi.yaml` against the OpenAPI 3.1.0 specification. NFR-004 requires this as a hard gate in `composer verify`.

**Steps**:
1. Create `bin/check-openapi` (executable, `chmod +x`).
2. Preferred approach — use `npx @stoplight/spectral-cli` (available in CI via Node):
   ```bash
   #!/usr/bin/env bash
   set -euo pipefail
   
   OPENAPI_FILE="${1:-packages/api/openapi.yaml}"
   
   if [ ! -f "$OPENAPI_FILE" ]; then
       echo "check-openapi: ERROR: $OPENAPI_FILE not found" >&2
       exit 1
   fi
   
   if command -v npx &>/dev/null; then
       npx --yes @stoplight/spectral-cli@^6 lint "$OPENAPI_FILE"
   else
       echo "check-openapi: SKIP: npx not available (install Node.js to enable OpenAPI linting)" >&2
       exit 0
   fi
   ```
3. Alternative — if a PHP-native validator is available in `composer.json` dev dependencies (e.g., `league/openapi-psr7-validator`), use it instead.
4. The script exits 0 on success, non-zero on lint failure.
5. Make the script executable: note in your commit that `git add --chmod=+x bin/check-openapi` is needed.

**Validation**:
- [ ] `bin/check-openapi` exits 0 on `packages/api/openapi.yaml`
- [ ] Script is executable (`chmod +x`)
- [ ] Script exits non-zero on an invalid OpenAPI document

---

### T018 — Wire `bin/check-openapi` into `composer verify`

**File**: `composer.json` (root)

**Purpose**: The `composer verify` gate must include the OpenAPI lint so it fails CI on a malformed document.

**Steps**:
1. Open root `composer.json`.
2. Find the `scripts.verify` (or `scripts.check`) entry.
3. Add `"bin/check-openapi"` to the verify script array (alongside existing checks like `bin/check-package-layers`, `bin/check-composer-policy`, `bin/check-dead-code`).
4. Verify the `scripts` block uses the array form, not a single string.
5. Run `composer verify` locally to confirm it passes with the new step.

**Validation**:
- [ ] `composer verify` includes `bin/check-openapi`
- [ ] `composer verify` passes locally (or CI passes)
- [ ] `sort-packages: true` preserved per Composer policy

---

## Branch Strategy

**Planning/base branch**: `main`
**Merge target**: `main`
**Execution**: Worktree allocated per `lanes.json`. Depends on WP01 lane completing first (exception types must be importable).

## Definition of Done

- [ ] `EventDispatcherInterface` injected into `AgentExecutor` (nullable, backward-compat)
- [ ] All five events dispatched from `AgentExecutor` at correct lifecycle points
- [ ] `RunAgentHandler` dispatches `AgentRunTerminated` on its owned paths (cancelled / pre-executor failure)
- [ ] Ownership comments in both classes
- [ ] `AgentExecutorEventDispatchTest` passes (3 methods)
- [ ] `AgentRunObservabilityTest` passes (full-stack integration)
- [ ] `packages/api/openapi.yaml` created and valid
- [ ] `bin/check-openapi` created, executable, passing
- [ ] `composer verify` includes `bin/check-openapi` and passes
- [ ] PHPStan clean, CS-Fixer clean
- [ ] No double-dispatch (one `AgentRunTerminated` per run)

## Risks

| Risk | Mitigation |
|---|---|
| Event class namespace mismatch | Read each event class file before adding `use` import |
| Double-dispatch: both executor and handler dispatch `AgentRunTerminated` on same path | Trace all execution paths; add ownership comment as the guard |
| `npx` not available in CI | Script exits 0 with SKIP notice (NFR-004 minimum) |
| Layer violation: ai-agent (L5) importing from ai-observability (L5) | Both are L5; peer-layer imports within L5 are permitted. Verify with `bin/check-package-layers` |

## Reviewer Guidance

1. Trace all `AgentRunTerminated` dispatch sites — there should be exactly one path per run.
2. Verify `dispatchSafely` wraps every dispatch call (not just some).
3. Check `AgentRunObservabilityTest` boots without hitting network or real filesystem.
4. Confirm `packages/api/openapi.yaml`'s `pending_approval` shape field names match `AgentRunBroadcaster::push()` payload keys exactly.

## Activity Log

- 2026-05-21T00:42:25Z – claude:sonnet:implementer:implementer – shell_pid=726372 – Started implementation via action command
- 2026-05-21T00:54:07Z – claude:sonnet:implementer:implementer – shell_pid=726372 – 5 lifecycle events dispatched from AgentExecutor (Started, IterationCompleted, ProviderCallCompleted, ToolCallObserved, Terminated); RunAgentHandler dispatches Terminated for handler-owned failure path only; openapi.yaml bootstrapped + spectral lint wired into composer verify; 141 tests pass, PHPStan clean, cs-check clean
- 2026-05-21T00:55:03Z – claude:opus-4-7:reviewer:reviewer – shell_pid=767088 – Started review via action command
