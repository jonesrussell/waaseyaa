# Tasks: Agent Executor v1.1 — Audit Follow-ups

**Mission**: `agent-executor-v1-1-audit-followups-01KS3S5M`
**Generated**: 2026-05-20T23:57:13Z
**Branch**: `main` → `main`
**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Create `ProviderException` abstract base class | WP01 | N | [D] |
| T002 | Create `TransportException` (5xx/network, retryable) | WP01 | [D] |
| T003 | Create `ClientErrorException` (4xx non-429, non-retryable) | WP01 | [D] |
| T004 | Update `RateLimitException` to extend `ProviderException` | WP01 | N | [D] |
| T005 | Update `AnthropicProvider` to throw typed exceptions | WP01 | N | [D] |
| T006 | Update `OpenAiCompatibleProvider` to throw typed exceptions | WP01 | [D] |
| T007 | Refine `AgentExecutor::callProviderWithRetry` catch clauses | WP01 | N | [D] |
| T008 | Write `AgentExecutorRetryTest` (FR-010) | WP01 | N | [D] |
| T009 | Inject `EventDispatcherInterface` into `AgentExecutor` | WP02 | N | [D] |
| T010 | Dispatch `AgentRunStarted` + `AgentRunTerminated` from `AgentExecutor` | WP02 | N | [D] |
| T011 | Dispatch `AgentRunIterationCompleted` from `AgentExecutor` | WP02 | N | [D] |
| T012 | Dispatch `AgentRunProviderCallCompleted` + `AgentRunToolCallObserved` from `AgentExecutor` | WP02 | N | [D] |
| T013 | Dispatch `AgentRunTerminated` from `RunAgentHandler` (owned paths only) | WP02 | N | [D] |
| T014 | Write `AgentExecutorEventDispatchTest` (FR-011) | WP02 | N | [D] |
| T015 | Write `AgentRunObservabilityTest` integration test (FR-014) | WP02 | N | [D] |
| T016 | Create `packages/api/openapi.yaml` bootstrap document | WP02 | [D] |
| T017 | Create `bin/check-openapi` lint script | WP02 | [D] |
| T018 | Wire `bin/check-openapi` into `composer verify` | WP02 | N | [D] |
| T019 | Verify `AgentRunBroadcasterServiceProvider` is the canonical binding | WP03 | N |
| T020 | Delete `BroadcastStorageAdapter` and its test (if any) | WP03 | N |
| T021 | Clean `MessagingServiceProvider` — remove adapter reference | WP03 | N |
| T022 | Update/create `AgentRunBroadcasterTest` (FR-012) | WP03 | N |
| T023 | Confirm `pending_approval` OpenAPI shape matches broadcaster emission | WP03 | N |
| T024 | Implement real SSE consumer in `AiRunCommand::runAsync` | WP04 | N |
| T025 | Wire SIGINT / Ctrl-C teardown | WP04 | N |
| T026 | Write `AiRunCommandWatchTest` (FR-013) | WP04 | N |
| T027 | Record smoke-test result in WP notes | WP04 | N |
| T028 | Update `docs/specs/agent-executor.md` — exception hierarchy + event dispatch contract | WP05 | N |
| T029 | Add `CHANGELOG.md` `[Unreleased]` entry | WP05 | N |
| T030 | Run `composer verify` and confirm green | WP05 | N |
| T031 | Verify SC-001..SC-008 success criteria | WP05 | N |

---

## Work Package 1 — Provider Exception Hierarchy + Retry Semantics

**File**: [tasks/WP01-provider-exception-hierarchy.md](tasks/WP01-provider-exception-hierarchy.md)
**Priority**: Critical (blocker for WP02)
**Closes**: #1509
**Estimated size**: ~380 lines

### Summary

Create the typed exception hierarchy (`ProviderException`, `TransportException`, `ClientErrorException`), update `RateLimitException` to extend the new base, update `AnthropicProvider` and `OpenAiCompatibleProvider` to throw typed exceptions instead of bare `\RuntimeException`, and refine `callProviderWithRetry` so 4xx non-429 errors re-throw immediately while 5xx/transport and 429 retry per FR-025 budget.

**Independent test**: `AgentExecutorRetryTest` runs in isolation with no external dependencies.

### Included subtasks

- [x] T001 Create `ProviderException` abstract base class (WP01)
- [x] T002 Create `TransportException` (5xx/network, retryable) (WP01)
- [x] T003 Create `ClientErrorException` (4xx non-429, non-retryable) (WP01)
- [x] T004 Update `RateLimitException` to extend `ProviderException` (WP01)
- [x] T005 Update `AnthropicProvider` to throw typed exceptions (WP01)
- [x] T006 Update `OpenAiCompatibleProvider` to throw typed exceptions (WP01)
- [x] T007 Refine `AgentExecutor::callProviderWithRetry` catch clauses (WP01)
- [x] T008 Write `AgentExecutorRetryTest` (FR-010) (WP01)

**Parallel opportunities**: T002 and T003 can be written in parallel (independent new files). T006 can be written in parallel with T005 (different provider file).

**Dependencies**: None (first WP).

**Risks**:
- `RateLimitException` is public API; extending `ProviderException` is backward-compatible (all existing `catch (\RuntimeException)` clauses still catch it) but callers catching `\RuntimeException` narrowly may miss the change — verify no such callers in the codebase.
- `callProviderWithRetry` has a `@todo` comment acknowledging the gap; the rewrite must preserve the retry budget from FR-025.

---

## Work Package 2 — Event Dispatch + OpenAPI Bootstrap

**File**: [tasks/WP02-event-dispatch-openapi-bootstrap.md](tasks/WP02-event-dispatch-openapi-bootstrap.md)
**Priority**: Critical (depends on WP01 exception types)
**Closes**: #1510
**Estimated size**: ~480 lines

### Summary

Wire the five domain event dispatches into `AgentExecutor` and `RunAgentHandler` (FR-004/005), add integration + unit tests, bootstrap `packages/api/openapi.yaml` as the canonical OpenAPI document with the `pending_approval` shape, and add `bin/check-openapi` wired into `composer verify` (NFR-004).

**Independent test**: `AgentExecutorEventDispatchTest` and `AgentRunObservabilityTest` are isolated from broadcaster and CLI concerns.

### Included subtasks

- [x] T009 Inject `EventDispatcherInterface` into `AgentExecutor` (WP02)
- [x] T010 Dispatch `AgentRunStarted` + `AgentRunTerminated` from `AgentExecutor` (WP02)
- [x] T011 Dispatch `AgentRunIterationCompleted` from `AgentExecutor` (WP02)
- [x] T012 Dispatch `AgentRunProviderCallCompleted` + `AgentRunToolCallObserved` from `AgentExecutor` (WP02)
- [x] T013 Dispatch `AgentRunTerminated` from `RunAgentHandler` (owned paths only) (WP02)
- [x] T014 Write `AgentExecutorEventDispatchTest` (FR-011) (WP02)
- [x] T015 Write `AgentRunObservabilityTest` integration test (FR-014) (WP02)
- [x] T016 Create `packages/api/openapi.yaml` bootstrap document (WP02)
- [x] T017 Create `bin/check-openapi` lint script (WP02)
- [x] T018 Wire `bin/check-openapi` into `composer verify` (WP02)

**Parallel opportunities**: T016+T017 (OpenAPI file + script) can be done in parallel with T009–T013 (event wiring) since they touch different files.

**Dependencies**: WP01 (needs `TransportException`/`ClientErrorException` types stable before touching `AgentExecutor` import block).

**Risks**:
- Event classes live in `packages/ai-observability/` (L5 peer). Import from ai-agent is a same-layer peer reference — confirm layer rules allow it (both L5).
- Double-dispatch guard: `AgentExecutor` and `RunAgentHandler` must own non-overlapping lifecycle points; the ownership comment is the only guard (no runtime lock per Decision 2).
- OpenAPI linting tool may require `npx` in CI if no PHP-native validator is available — confirm CI environment has Node.

---

## Work Package 3 — Broadcaster Consolidation

**File**: [tasks/WP03-broadcaster-consolidation.md](tasks/WP03-broadcaster-consolidation.md)
**Priority**: High (depends on WP02 OpenAPI shape for alignment check)
**Closes**: #1511
**Estimated size**: ~280 lines

### Summary

Delete `BroadcastStorageAdapter` (confirmed no external consumers per plan grep), clean `MessagingServiceProvider` to rebind `AgentRunBroadcasterInterface` via `AgentRunBroadcasterServiceProvider`, update/create `AgentRunBroadcasterTest`, and confirm the `pending_approval` OpenAPI shape aligns with what `AgentRunBroadcaster` actually emits.

**Independent test**: `AgentRunBroadcasterTest` covers the canonical broadcaster in isolation.

### Included subtasks

- [ ] T019 Verify `AgentRunBroadcasterServiceProvider` is the canonical binding (WP03)
- [ ] T020 Delete `BroadcastStorageAdapter` and its test (if any) (WP03)
- [ ] T021 Clean `MessagingServiceProvider` — remove adapter reference (WP03)
- [ ] T022 Update/create `AgentRunBroadcasterTest` (FR-012) (WP03)
- [ ] T023 Confirm `pending_approval` OpenAPI shape matches broadcaster emission (WP03)

**Parallel opportunities**: T022 can be written after T019/T020 but is independent of T021/T023.

**Dependencies**: WP02 (OpenAPI document must exist before T023 can align it).

**Risks**:
- `MessagingServiceProvider` currently binds via adapter; after removal it must either delegate to `AgentRunBroadcasterServiceProvider` or be removed as a redundant binding.
- PHPStan dead-code gate: after deleting `BroadcastStorageAdapter`, regenerate the baseline or confirm the class was in `phpstan-dead-code-baseline.neon`.

---

## Work Package 4 — AiRunCommand --watch SSE Consumer

**File**: [tasks/WP04-airuncommand-watch-sse-consumer.md](tasks/WP04-airuncommand-watch-sse-consumer.md)
**Priority**: High (FR-008/009; independent after WP01 but scheduled last for full-stack testing)
**Closes**: #1513
**Estimated size**: ~320 lines

### Summary

Replace the stub `--watch` print-and-exit in `AiRunCommand::runAsync` with a real SSE consumer using `packages/http-client/`'s `StreamHttpClient`. Parse `data:` lines from `/broadcast?channels=agent.run.<id>`, print event name + payload to stdout, exit cleanly on `terminated` event, and handle SIGINT via `pcntl_signal` to tear down the HTTP stream without terminating the server-side run.

**Independent test**: `AiRunCommandWatchTest` mocks the HTTP client and asserts event printing and exit codes.

### Included subtasks

- [ ] T024 Implement real SSE consumer in `AiRunCommand::runAsync` (WP04)
- [ ] T025 Wire SIGINT / Ctrl-C teardown (WP04)
- [ ] T026 Write `AiRunCommandWatchTest` (FR-013) (WP04)
- [ ] T027 Record smoke-test result in WP notes (WP04)

**Parallel opportunities**: T025 is wired into T024's implementation (not truly separate); T026 can be written once T024+T025 are implemented.

**Dependencies**: WP01, WP03 (scheduled after broadcaster consolidation to allow smoke-testing against a clean broadcaster; the `--watch` test benefits from the fully-wired run loop).

**Risks**:
- `pcntl_signal` requires `pcntl` PHP extension — confirm availability in CLI environment and add `ext-pcntl` to `packages/cli/composer.json` `require`.
- SSE chunked streaming: `StreamHttpClient` must support chunk-by-chunk reading; verify its API before implementing the consumer loop.
- Test isolation: `AiRunCommandWatchTest` must mock the HTTP stream without requiring a live server — use a test double that yields pre-scripted `data:` lines.

---

## Work Package 5 — Wrap-up

**File**: [tasks/WP05-wrap-up.md](tasks/WP05-wrap-up.md)
**Priority**: Required (closes documentation, CHANGELOG, and final verification)
**Closes**: #1509, #1510, #1511, #1513 (indirectly — via merge commit footer)
**Estimated size**: ~200 lines

### Summary

Update `docs/specs/agent-executor.md` with the exception hierarchy and event dispatch contract, add the `[Unreleased]` CHANGELOG entry, run `composer verify` to confirm the gate is green, and verify SC-001..SC-008.

**Independent test**: No new test files — this WP validates the full suite.

### Included subtasks

- [ ] T028 Update `docs/specs/agent-executor.md` (WP05)
- [ ] T029 Add `CHANGELOG.md` `[Unreleased]` entry (WP05)
- [ ] T030 Run `composer verify` and confirm green (WP05)
- [ ] T031 Verify SC-001..SC-008 success criteria (WP05)

**Dependencies**: WP01, WP02, WP03, WP04.

**Risks**:
- `composer verify` may expose PHPStan dead-code findings from new classes — ensure `@api` or `#[PolicyAttribute]` annotations are in place, or add to baseline.
- Spec file may be `docs/specs/agent-executor.md` or `docs/specs/ai-integration.md` — confirm canonical file before editing.

---

## Parallelization Summary

| Lane | WPs | Can run in parallel with |
|---|---|---|
| Sequential | WP01 → WP02 → WP03 | WP04 is independent after WP01 but scheduled after WP03 |
| Within WP01 | T002, T003, T006 | Parallel new files |
| Within WP02 | T016, T017 vs T009–T013 | OpenAPI files vs event wiring |

## MVP Scope

**WP01** is the MVP — typed exceptions + retry semantics is the highest-risk item (burns retry budget on wrong errors) and unblocks WP02.

## Next command

```bash
spec-kitty next --agent claude:sonnet:tasks:tasks --mission agent-executor-v1-1-audit-followups-01KS3S5M
```
