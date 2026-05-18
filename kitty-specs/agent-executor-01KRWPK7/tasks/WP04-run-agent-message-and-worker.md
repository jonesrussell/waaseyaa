---
work_package_id: WP04
title: RunAgent message + worker + AgentRunService + reaper
dependencies:
- WP03
requirement_refs:
- FR-001
- FR-008
- FR-018
- FR-028
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-agent-executor-01KRWPK7
base_commit: eb2158425d828b628579169c5ab90ea062a88113
created_at: '2026-05-18T17:44:31.698622+00:00'
subtasks:
- T024
- T025
- T026
- T027
- T028
shell_pid: '362237'
history:
- date: '2026-05-18T14:55:10Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/ai-agent/src/Message/
execution_mode: code_change
owned_files:
- packages/ai-agent/src/Message/**
- packages/ai-agent/src/Service/AgentRunService.php
- packages/ai-agent/src/Reaper/**
- packages/ai-agent/src/MessagingServiceProvider.php
- packages/config/src/Schema/Ai/ProvidersConfig.php
- packages/config/src/Schema/Ai/ScalarsConfig.php
- defaults/ai.yaml
- tests/Integration/PhaseN/AgentRuntime/EnqueueAndConsumeTest.php
- tests/Integration/PhaseN/AgentRuntime/ReaperTest.php
tags: []
---

# WP-04 — `RunAgent` message + worker + `AgentRunService` + reaper

## Objective

Make production agent execution possible. Add the Messenger message
and handler (the only path that runs `AgentExecutor` in production),
the `AgentRunService` exposing both async (`enqueue()`) and sync
(`runInline()`) entry points, and the `StalledRunReaper` that
recovers from worker crashes. Register provider and scalar config
entities so the worker can resolve everything it needs at boot.

## Context

- Spec FRs in scope: **FR-001 (enqueue path), FR-008, FR-022 (push hooks), FR-028**.
- NFRs in scope: **NFR-002, NFR-004, NFR-015**.
- Constraints applied: **C-009, C-010, C-012, C-014**.
- Data-model authoritative: [data-model.md](../data-model.md) §"Messages" and §"Config entities".
- Doctrine spec sections: §"Worker handler", §"Reaper", §"Config" in `docs/specs/agent-executor.md`.
- Broadcasting surface: `packages/api/src/Controller/BroadcastStorage.php`. The push event vocabulary is defined in [data-model.md](../data-model.md) §"SSE event vocabulary"; concrete wiring lives in WP-05, but the handler must emit `run_started`, `iteration`, `tool_call_*`, `provider_chunk`, `run_completed`, `run_failed`, `run_cancelled` as it progresses.

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T024 — `RunAgent` Messenger message

**Purpose:** A tiny envelope that carries the run id.

**Steps:**
1. Create `packages/ai-agent/src/Message/RunAgent.php`:
   ```php
   final readonly class RunAgent
   {
       public function __construct(public Uuid $runId) {}
   }
   ```
2. The message contains **only** `runId`. The handler loads the row from the repository (per data-model).
3. Verify Symfony Messenger can serialize it (Symfony Uid is supported out of the box).

**Files:**
- `packages/ai-agent/src/Message/RunAgent.php`
- `packages/ai-agent/tests/Unit/Message/RunAgentTest.php`

**Validation:**
- [ ] Construction + serialization round-trip via Messenger's `Envelope`.

---

## Subtask T025 — `RunAgentHandler` + worker-concurrency guard

**Purpose:** Execute the run inside a Messenger handler. This is the sole production entry point to `AgentExecutor`.

**Steps:**
1. Create `packages/ai-agent/src/Message/RunAgentHandler.php` annotated with Symfony's `#[AsMessageHandler]`. Inject:
   - `AgentRunRepository`
   - `AgentAuditLogRepository`
   - `AgentExecutor`
   - `AgentDefinitionRegistry`
   - `AgentRunBroadcaster` (delivered by WP-05; for WP-04 we ship a `BroadcastStorageInterface` adapter and let the broadcaster bind at the WP-05 boundary)
   - `LoggerInterface` (`Waaseyaa\Foundation\Log\LoggerInterface`, optional, default `NullLogger`)
2. `__invoke(RunAgent $message)`:
   1. Load row via `AgentRunRepository::find($message->runId)`. If missing, log and return.
   2. **Worker-concurrency guard:** `if ($run->started_at !== null) { return; }` and call `repo->markRunning($id, now())` — `markRunning` already does the compare-and-swap (returns false if another worker won), so on false return the handler exits without invoking the executor (**NFR-015**).
   3. Resolve the bundle (from `agent_definition_id` via `AgentDefinitionRegistry` or from the inline `bundle_json` snapshot).
   4. Build an `AgentContext` carrying the initiator account (loaded from `account_id`), the resolved bundle, the registry.
   5. Push `run_started` SSE event.
   6. Call `AgentExecutor::executeWithProvider($context)` — this drives iterations, tool calls, HITL, retries.
   7. On normal completion: persist transcript / tokens / cost, transition row to `Completed`, push `run_completed`.
   8. On exception: transition to `Failed` with derived `error_code` / `error_message`, push `run_failed`. Catch every `Throwable` — handler must never propagate to the transport.
   9. Every event push goes through a small adapter so WP-05 can later supply the real `AgentRunBroadcaster`. For WP-04 the adapter writes through `BroadcastStorage::push` directly (the storage class exists already).

**Files:**
- `packages/ai-agent/src/Message/RunAgentHandler.php`
- `packages/ai-agent/src/Broadcast/AgentRunBroadcasterInterface.php` (NEW — minimal contract WP-05 will implement; WP-04 ships a `BroadcastStorageAdapter` to keep the worker functional)
- `packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php` (NEW)
- `packages/ai-agent/tests/Unit/Message/RunAgentHandlerTest.php`

**Validation:**
- [ ] Concurrent-handler test: two `__invoke` calls on the same `runId` — second short-circuits without re-running.
- [ ] Exception during execution → run reaches `Failed`, never propagates to transport.

---

## Subtask T026 — `AgentRunService::enqueue()` + `runInline()`

**Purpose:** One service, two entry points: production async + dev / CLI sync.

**Steps:**
1. Create `packages/ai-agent/src/Service/AgentRunService.php`. Inject:
   - `MessageBusInterface` (Symfony Messenger)
   - `AgentRunRepository`
   - `RunAgentHandler` (for inline)
2. Public API:
   - `enqueue(AgentRunDraft $draft): AgentRun` — validates draft (required prompt, optional agent_id OR inline bundle, destructive_approval), persists row at `queued`, dispatches `RunAgent`, returns the persisted row.
   - `runInline(AgentRunDraft $draft): AgentRun` — same persistence path, but instead of dispatching the message, invokes `RunAgentHandler->__invoke(new RunAgent($run->id))` in-process. Returns the row after the handler completes.
3. `AgentRunDraft` is a small DTO (record-like `final readonly class`) carrying `accountId`, `agentDefinitionId | bundle`, `prompt`, `destructiveApproval` (`HitlMode`).
4. Both entry points produce **identical** persistence state and audit-row sequences. Inline mode does **not** bypass `RunAgentHandler` (**FR-008**).
5. `runInline()` MAY refuse `HitlMode::Interactive` (no human is present); document this and let the CLI in WP-06 enforce the user-facing check, but `runInline()` should still throw `InvalidArgumentException` if called with interactive HITL.

**Files:**
- `packages/ai-agent/src/Service/AgentRunService.php`
- `packages/ai-agent/src/Service/AgentRunDraft.php`
- `packages/ai-agent/tests/Unit/Service/AgentRunServiceTest.php`

**Validation:**
- [ ] Enqueue persists a row + dispatches the message (verify with `InMemoryTransport`).
- [ ] Inline produces an identical row + audit-row sequence as the async path (use `sync` transport for the async control).
- [ ] Inline with `Interactive` HITL throws `InvalidArgumentException`.

---

## Subtask T027 — `StalledRunReaper` service

**Purpose:** Recover from worker crashes (**NFR-004**, **FR-007**).

**Steps:**
1. Create `packages/ai-agent/src/Reaper/StalledRunReaper.php`. Inject `AgentRunRepository`, `Clock`, `LoggerInterface`.
2. Public method `reap(int $maxRuntimeSeconds): int`:
   - Compute threshold: `now() - maxRuntimeSeconds`.
   - Use `AgentRunRepository::findStuckRunning($threshold)` — this returns rows where `status='running'` AND `started_at < threshold`.
   - For each row: call `markTerminal($id, RunStatus::Failed, now(), errorCode: 'worker_crashed')` — if it returns false (already terminal), skip (the worker may have completed just before the reaper looked).
   - Push `run_failed` SSE event via the broadcaster adapter.
   - Append `error` audit row with `event_type='error'`, `tool_name=null`, `tool_result_summary='worker_crashed'`.
   - Return the count of rows successfully flipped.
3. Reap loop SHALL NOT regress a terminal status (**C-014**) — guaranteed by `markTerminal`'s precondition.

**Files:**
- `packages/ai-agent/src/Reaper/StalledRunReaper.php`
- `packages/ai-agent/tests/Unit/Reaper/StalledRunReaperTest.php`
- `tests/Integration/PhaseN/AgentRuntime/ReaperTest.php`

**Validation:**
- [ ] Stuck row → `failed/worker_crashed` within one reap call.
- [ ] Already-terminal row → untouched (compare-and-swap protection).

---

## Subtask T028 — Messenger transport + config entities

**Purpose:** Boot-time wiring of the queue and the new `config.ai.*` schemas.

**Steps:**
1. Create `packages/ai-agent/src/MessagingServiceProvider.php`:
   - Register the `RunAgent` message under the production transport (the existing `packages/queue` integration). Use `sync` transport in test environments — wire via existing `APP_ENV` / messenger.yaml conventions.
   - Bind `RunAgentHandler` as a service tagged for Messenger auto-discovery (Symfony picks up `#[AsMessageHandler]`).
   - Bind `AgentRunService` and `StalledRunReaper`.
2. Register Waaseyaa CMI config entity schemas in `packages/config/src/Schema/Ai/`:
   - `ProvidersConfig.php` — list shape per data-model § `config.ai.providers` (id, type, model_default, timeout_ms, rate_limit_per_min, api_key_env_var). Secrets via env-var indirection per **C-010**.
   - `ScalarsConfig.php` — covers `config.ai.run_retention_days`, `config.ai.hitl_timeout_seconds`, `config.ai.max_runtime_seconds`, `config.ai.transcript_max_bytes`, `config.ai.hitl_poll_interval_ms`.
3. Add a default sync file `defaults/ai.yaml` with the scalar defaults (30 days, 300 s, 600 s, 262144 bytes, 1000 ms) and an empty providers list. `bin/check-no-secrets` and `bin/check-ingestion-defaults` MUST stay green (no secrets, defaults match doctrine).
4. Add `Waaseyaa\AI\Agent\MessagingServiceProvider` to `packages/ai-agent/composer.json`'s `extra.waaseyaa.providers` array.
5. Add an end-to-end integration test `tests/Integration/PhaseN/AgentRuntime/EnqueueAndConsumeTest.php`:
   - Boots the kernel with `sync` Messenger transport.
   - Calls `AgentRunService::enqueue()` with a `NullLlmProvider` bundle.
   - Asserts the row reaches `Completed` within 30 s wall-clock (**NFR-002**).

**Files:**
- `packages/ai-agent/src/MessagingServiceProvider.php`
- `packages/config/src/Schema/Ai/ProvidersConfig.php`
- `packages/config/src/Schema/Ai/ScalarsConfig.php`
- `defaults/ai.yaml`
- `packages/ai-agent/composer.json`
- `tests/Integration/PhaseN/AgentRuntime/EnqueueAndConsumeTest.php`

**Validation:**
- [ ] End-to-end test green within 30 s wall-clock.
- [ ] `bin/check-no-secrets` exits 0.
- [ ] `bin/check-ingestion-defaults` exits 0 (the new `ai.yaml` file matches the doctrine defaults).

---

## Definition of Done

- [ ] T024..T028 checkboxes flipped.
- [ ] `RunAgent` message + handler land; handler is idempotent under concurrent dispatch.
- [ ] `AgentRunService::enqueue()` + `runInline()` produce identical persistence state.
- [ ] `StalledRunReaper` recovers stuck rows within one tick.
- [ ] Provider + scalar config entities registered; default `defaults/ai.yaml` checked in.
- [ ] All gates green.

## Risks & mitigations

1. **Worker concurrency.** *Mitigation:* `markRunning` compare-and-swap + Messenger transport-level locking (**NFR-015**).
2. **Reaper regresses terminal status.** *Mitigation:* `markTerminal` precondition (**C-014**).
3. **Inline mode diverges from async path.** *Mitigation:* both go through `RunAgentHandler::__invoke` (**FR-008**) — service test asserts identical audit-row sequences.

## Reviewer guidance

- Check `markRunning` is the **only** way `started_at` gets set. Any other code path setting `started_at` is a bug.
- Confirm `runInline()` does not bypass the handler.
- Verify `defaults/ai.yaml` carries env-var names, not secret values.
- Spot-check the test for `EnqueueAndConsumeTest` runs end-to-end via `NullLlmProvider` and exits within budget.

## Implementation command

```
spec-kitty agent action implement WP04 --agent <name>
```
