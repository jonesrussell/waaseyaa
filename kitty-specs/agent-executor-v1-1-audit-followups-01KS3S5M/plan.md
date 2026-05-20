# Implementation Plan: Agent Executor v1.1 — Audit Follow-ups

**Branch**: `main` | **Date**: 2026-05-20 | **Spec**: [spec.md](spec.md)
**Input**: `kitty-specs/agent-executor-v1-1-audit-followups-01KS3S5M/spec.md`
**Mission**: `agent-executor-v1-1-audit-followups-01KS3S5M`

## Summary

Four audit threads surfaced during the predecessor mission's review cycle. This mission makes the agent runtime's externally observable contract match what the spec promised: typed exception hierarchy with correct retry semantics (WP01), live event dispatch from producer classes (WP02), broadcaster class consolidation (WP03), and a functional `--watch` SSE consumer in the CLI (WP04). WP05 closes documentation and CHANGELOG.

**Branch contract**: `main` → `main`. No worktree. PRs merge directly to main.

## Technical Context

**Language/Version**: PHP 8.5+ (`declare(strict_types=1)` in every file)
**Primary Dependencies**: Symfony 7.x EventDispatcher, Foundation `EventDispatcherInterface` (L0), `packages/http-client/` (L0) for SSE consumer
**Storage**: N/A (no new entity types)
**Testing**: PHPUnit 10.5 with `#[Test]`, `#[CoversClass]` attributes; testify-style assertions; integration tests under `tests/Integration/Phase*/`
**Target Platform**: PHP-FPM / CLI, Linux
**Project Type**: PHP monorepo (packages in `packages/`)
**Performance Goals**: NFR-001 ≤1% overhead on provider calls; NFR-002 ≤2 ms median per lifecycle event dispatch
**Constraints**: No changes to `AgentRunInterface`, `AgentRunRepository`, or `RunAgentHandler` message shape (C-001). Layer discipline: exception classes in L5 (`packages/ai-agent/`), event dispatch via L0 `EventDispatcherInterface`, CLI consumer in L6 (`packages/cli/`) over HTTP.
**Scale/Scope**: ~12 files changed + ~5 new files + 5 new/updated test files; single package boundary (ai-agent L5, cli L6)

### OpenAPI gap (WP02 scope expansion)

No canonical OpenAPI document exists anywhere in the repository (`find` returns nothing for `openapi*` outside vendor). WP02 must:
1. Create `packages/api/openapi.yaml` as the canonical document.
2. Add `bin/check-openapi` (OpenAPI lint against JSON Schema Draft 4 / OpenAPI 3.x).
3. Wire `bin/check-openapi` into `composer verify`.

### BroadcastStorageAdapter scope confirmation

Grep of all `packages/` confirms `BroadcastStorageAdapter` is referenced **only within `packages/ai-agent/`**:
- `packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php` (the class itself)
- `packages/ai-agent/src/Broadcast/AgentRunBroadcasterInterface.php`
- `packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php`
- `packages/ai-agent/src/Broadcast/AgentRunBroadcasterServiceProvider.php`
- `packages/ai-agent/src/MessagingServiceProvider.php`

No external consumers. **Direct deletion in WP03** — no `@deprecated` staging needed (greenfield removal policy, DIR-003).

## Charter Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Check | Status | Notes |
|---|---|---|
| Layer architecture respected | PASS | Exceptions in L5 `ai-agent`, dispatch via L0 `EventDispatcherInterface`, CLI consumer in L6 connecting via HTTP |
| No Drupal-runtime dependencies | PASS | No Drupal-shaped global state introduced |
| No service locators | PASS | All services injected via constructor DI |
| Tests required per WP | PASS | FR-010..FR-014 mandate 4 unit tests + 1 integration test |
| `composer verify` green | PASS | C-004 enforced; WP02 adds `bin/check-openapi` to this gate |
| CHANGELOG entry required | PASS | WP05 responsibility |
| `docs/specs/` updated | PASS | WP05 updates `docs/specs/ai-pipeline.md` (or canonical AI spec) |
| No `@deprecated` shims | PASS | Alpha greenfield removal policy applies; adapter deleted outright |
| Traceability: PR closes #1509, #1510, #1511, #1513 | PASS | C-002; `Closes #N` footer on merge commit |
| No magic numbers / `interface{}` / bare `\RuntimeException` for HTTP outcomes | PASS | FR-001/FR-002 replace bare exceptions with typed hierarchy |
| PHPStan baseline must stay clean | PASS | New classes are concrete finals; dead-code gate applies |

**Charter check: GATE PASSES**

## Project Structure

### Documentation (this mission)

```
kitty-specs/agent-executor-v1-1-audit-followups-01KS3S5M/
├── spec.md
├── plan.md              ← this file
├── research.md          ← Phase 0 output (below)
├── data-model.md        ← Phase 1 output (below)
├── contracts/
│   └── openapi-pending-approval-shape.yaml   ← Phase 1 output
└── tasks.md             ← /spec-kitty.tasks output (NOT created here)
```

### Source code (repository root — affected paths)

```
packages/ai-agent/src/
├── Provider/
│   ├── ProviderException.php          (new — abstract base)
│   ├── TransportException.php         (new — 5xx / network, retryable)
│   ├── ClientErrorException.php       (new — 4xx non-429, non-retryable)
│   ├── RateLimitException.php         (edit — extend ProviderException)
│   ├── AnthropicProvider.php          (edit — throw typed exceptions)
│   └── OpenAiCompatibleProvider.php   (edit — throw typed exceptions if applicable)
├── AgentExecutor.php                   (edit — callProviderWithRetry + event dispatch)
├── RunAgentHandler.php                 (edit — event dispatch at owned lifecycle points)
├── Broadcast/
│   ├── BroadcastStorageAdapter.php    (DELETE)
│   ├── AgentRunBroadcaster.php        (edit — DI cleanup)
│   └── AgentRunBroadcasterServiceProvider.php (edit — ensure canonical binding)
└── MessagingServiceProvider.php       (edit — remove adapter reference)

packages/api/
└── openapi.yaml                        (new — canonical OpenAPI document)

packages/cli/src/Command/Ai/
└── AiRunCommand.php                    (edit — real SSE consumer in runAsync)

bin/
└── check-openapi                       (new — OpenAPI lint script)

docs/specs/
└── ai-pipeline.md                      (edit — exception hierarchy + event dispatch contract)

CHANGELOG.md                            (edit — [Unreleased] entry)

packages/ai-agent/tests/Unit/
├── Provider/
│   └── AgentExecutorRetryTest.php      (new — FR-010)
├── AgentExecutorEventDispatchTest.php  (new — FR-011)
└── Broadcast/
    └── AgentRunBroadcasterTest.php     (edit/new — FR-012; delete adapter test if exists)

packages/cli/tests/Unit/Command/Ai/
└── AiRunCommandWatchTest.php           (new — FR-013)

tests/Integration/Phase11/
└── AgentRunObservabilityTest.php       (new — FR-014)
```

**Structure Decision**: PHP monorepo. All changes confined to L5 `packages/ai-agent/`, L4 `packages/api/` (OpenAPI), and L6 `packages/cli/`. Layer discipline enforced: no upward imports, HTTP-only connection from L6 CLI to L0 broadcast endpoint.

## Complexity Tracking

No charter violations requiring justification. The OpenAPI document creation is a new file addition (not a complexity violation); WP02 scope expansion is grounded in NFR-004's explicit condition.

---

## Phase 0: Research

### Decision 1 — Exception hierarchy shape

**Decision**: Abstract class `ProviderException extends \RuntimeException` as the common ancestor; `TransportException` and `ClientErrorException` extend it; `RateLimitException` (existing) is updated to extend it.

**Rationale**: Abstract class (not marker interface) because `callProviderWithRetry` needs a single catch clause for the common ancestor in defensive catch-all scenarios. Extending `\RuntimeException` keeps it unchecked (PHP convention) and maintains backward compatibility for callers catching `\RuntimeException` today. The three-class shape exactly matches FR-001.

**Alternatives considered**: Marker interface — rejected because it requires two catch clauses (or `instanceof` union checks) in `callProviderWithRetry` and can't be caught as a base type in a single clause.

### Decision 2 — Event dispatch coordination (no double-dispatch)

**Decision**: `AgentExecutor` dispatches `AgentRunStarted`, `AgentRunIterationCompleted`, `AgentRunProviderCallCompleted`, `AgentRunToolCallObserved`, and the _normal_ `AgentRunTerminated`. `RunAgentHandler` dispatches `AgentRunTerminated` **only** for its owned termination paths (supervisor kill, run cancelled before executor starts). Ownership comment in both classes names the invariant.

**Rationale**: The five events map to distinct lifecycle points. `AgentExecutor` controls the run loop; `RunAgentHandler` wraps it and handles abnormal termination before/after executor engagement. The single-dispatch invariant is a code comment + PHPDoc `@throws` annotation, not a runtime lock — a runtime deduplication lock would add latency (NFR-002).

**Alternatives considered**: Always dispatch from `RunAgentHandler` and have `AgentExecutor` emit to a buffer — rejected as over-engineering; the ownership split is clear.

### Decision 3 — Best-effort dispatch (listener exception handling)

**Decision**: Wrap each `$dispatcher->dispatch($event)` in a try-catch at the call site inside `AgentExecutor`. Log via `LoggerInterface` (injected, nullable, defaults to NullLogger). Do not catch at `RunAgentHandler` level (it already has its own error handling).

**Rationale**: CLAUDE.md "Best-effort side effects" gotcha: listeners must not crash the primary run. The try-catch is inline (not a decorator) to keep the stack trace readable in logs.

### Decision 4 — OpenAPI document bootstrap

**Decision**: Create `packages/api/openapi.yaml` as OpenAPI 3.1.0 with `info.title: Waaseyaa Framework API`, covering the `pending_approval` AgentRun shape (minimum viable for FR-007/NFR-004). `bin/check-openapi` uses `composer require --dev spectral-cli` or a PHP-native validator; if no suitable Composer tool exists, use `npx @stoplight/spectral-cli lint` in CI.

**Rationale**: The spec (NFR-004) says "if no such step exists today, WP02's wrap-up adds a `bin/check-openapi` minimum." The canonical path `packages/api/openapi.yaml` is referenced by the spec's assumption. Minimum viable: one path (`/api/agent-run/{id}/events` or equivalent) with `pending_approval` shape.

**Alternatives considered**: Swagger 2.0 — rejected; OpenAPI 3.1.0 is the current standard and aligns with JSON Schema for type validation. Generating from PHP attributes — deferred (not in this mission's scope, C-001 prohibits touching the entity model).

### Decision 5 — SSE consumer implementation in AiRunCommand

**Decision**: Use `packages/http-client/` (existing L0 package) with a streaming GET to `/broadcast?channels=agent.run.<id>`. Parse `data:` lines; print `event + payload` to stdout. Block until `terminated` event received or SIGINT. Register `pcntl_signal(SIGINT, ...)` handler that closes the stream and exits 0.

**Rationale**: Spec assumption: "uses the framework's existing HttpClient (`packages/http-client/`)". No new transport library (C-003 spirit: no upward import, HTTP-only connection). SIGINT via `pcntl_signal` is available in PHP CLI context; the handler closes the connection cleanly without server-side termination.

**Alternatives considered**: Symfony HttpClient directly — rejected in favour of the framework's own abstraction to maintain layer discipline and avoid adding a new Symfony dependency to `packages/cli/` if not already present.

---

## Phase 1: Design & Contracts

### Data Model

See `data-model.md` (generated below).

### API Contract — `pending_approval` shape

The OpenAPI contract for the AgentRun `pending_approval` state emitted by `AgentRunBroadcaster` is captured in `contracts/openapi-pending-approval-shape.yaml`.

---

## Work Package Outline

| WP | Title | Closes | Key deliverables |
|---|---|---|---|
| WP01 | Provider exception hierarchy + retry semantics | #1509 | `ProviderException`, `TransportException`, `ClientErrorException`; update `RateLimitException`; update `AnthropicProvider` + `OpenAiCompatibleProvider`; refine `callProviderWithRetry`; `AgentExecutorRetryTest` (FR-010) |
| WP02 | Event dispatch + OpenAPI bootstrap | #1510 | Dispatch 5 events from `AgentExecutor` + `RunAgentHandler` (FR-004/005); `AgentExecutorEventDispatchTest` (FR-011); `AgentRunObservabilityTest` integration test (FR-014); create `packages/api/openapi.yaml`; add `bin/check-openapi`; wire into `composer verify` (FR-007/NFR-004) |
| WP03 | Broadcaster consolidation | #1511 | Delete `BroadcastStorageAdapter`; clean `MessagingServiceProvider`; verify `AgentRunBroadcasterServiceProvider` binding; update `AgentRunBroadcasterTest` (FR-012); confirm `pending_approval` OpenAPI shape matches broadcaster emission |
| WP04 | AiRunCommand --watch SSE consumer | #1513 | Real SSE consumer in `AiRunCommand::runAsync`; SIGINT handling; `AiRunCommandWatchTest` (FR-013); smoke-test recorded in WP notes |
| WP05 | Wrap-up | — | Update `docs/specs/ai-pipeline.md`; `CHANGELOG.md` `[Unreleased]` entry; full `composer verify` green; confirm SC-001..SC-008 pass |

**WP ordering**: WP01 → WP02 → WP03 → WP04 → WP05 (WP02 depends on WP01 exception types being present; WP03 depends on WP02 OpenAPI shape for the broadcaster payload alignment check; WP04 is independent after WP01 but scheduled last to allow SSE testing against a fully-wired run loop).

**Branch contract (final)**: all WPs commit to `main`; no worktree; PRs merge with `Closes #N` footer per C-002.
