---
work_package_id: WP08
title: ai-observability listeners + telemetry
dependencies:
- WP04
requirement_refs:
- FR-029
planning_base_branch: main
merge_target_branch: main
branch_strategy: 'Plan + merge target: main. Lane allocated by finalize-tasks; consult lanes.json.'
subtasks:
- T042
- T043
- T044
history:
- date: '2026-05-18T14:55:10Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/ai-observability/src/Pricing/
execution_mode: code_change
owned_files:
- packages/ai-observability/src/Pricing/**
- packages/ai-observability/src/Listener/AgentRunTelemetryListener.php
- packages/ai-observability/src/AgentTelemetryServiceProvider.php
- packages/ai-observability/composer.json
- tests/Integration/PhaseN/AgentRuntime/TelemetryTest.php
tags: []
---

# WP-08 — `ai-observability` listeners + telemetry

## Objective

Subscribe to AgentRun lifecycle events; capture per-provider-call
token usage, USD cost via a static `ModelPriceTable`, per-tool
invocation count, and wall-clock + per-iteration latency. Persist
the resulting telemetry via `packages/telescope`.

## Context

- Spec FRs in scope: **FR-029**.
- Doctrine spec section: §"Observability".
- Data-model authoritative: [data-model.md](../data-model.md) §"Audit invariants" + §"`AgentRun` columns" (`token_usage_in/out`, `cost_cents`).
- Plan resolution: static `ModelPriceTable.php` lives at `packages/ai-observability/src/Pricing/ModelPriceTable.php` (R-003).
- Best-effort side-effects rule: listener must wrap work in try-catch + log via `LoggerInterface` (constitution gotcha).

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T042 — `ModelPriceTable`  `[P]`

**Purpose:** Map `{provider, model}` → input/output USD-per-million-tokens.

**Steps:**
1. Create `packages/ai-observability/src/Pricing/ModelPriceTable.php`:
   - Static, namespaced `final class ModelPriceTable`.
   - Encapsulates a private `const PRICES` table keyed by `"{provider}:{model}"`:
     ```php
     'anthropic:claude-sonnet-4-6' => ['input_per_million' => 300, 'output_per_million' => 1500],
     'anthropic:claude-opus-4-7' => ['input_per_million' => 1500, 'output_per_million' => 7500],
     'openai:gpt-4o' => ...
     'null:null' => ['input_per_million' => 0, 'output_per_million' => 0],
     ```
     (Values in **cents-per-million-tokens** to keep arithmetic in integers; document the unit at the top of the file.)
   - Public method `priceCentsFor(string $providerModel, int $tokensIn, int $tokensOut): ?int` — returns NULL for unknown models (data-model semantics).
2. Mark the class `@api`.
3. Document the update cadence in a top-of-file comment: "Static price table; update via PR. Sourced from each provider's public pricing page. Mismatched models return NULL (downstream nil-safe)."

**Files:**
- `packages/ai-observability/src/Pricing/ModelPriceTable.php`
- `packages/ai-observability/tests/Unit/Pricing/ModelPriceTableTest.php`

**Validation:**
- [ ] Known model → integer cost.
- [ ] Unknown model → NULL.
- [ ] Arithmetic is exact (no floating-point drift).

---

## Subtask T043 — `AgentRunTelemetryListener`

**Purpose:** Subscribe to AgentRun lifecycle events and aggregate telemetry per run.

**Steps:**
1. Create `packages/ai-observability/src/Listener/AgentRunTelemetryListener.php`. Inject `?LoggerInterface = null`, `TelescopeRecorderInterface` (from `packages/telescope`), `ModelPriceTable`.
2. Subscribe to the AgentRun domain events emitted by `RunAgentHandler` (WP-04) / `AgentExecutor` (WP-03). The event surface is the **same** events backing the SSE broadcaster — listener taps the EventDispatcher channel, not the BroadcastStorage channel.
3. Track per-run state in an in-memory map keyed by `runId`:
   - `tokensIn`, `tokensOut` (sum across provider calls)
   - `costCents` (computed via `ModelPriceTable` on `provider_call` events)
   - `toolCallCount`
   - `iterations: int[]` (per-iteration wall-clock ms)
   - `startedAt`, `finishedAt`
4. On `run_completed` / `run_failed` / `run_cancelled`: flush the aggregated record to Telescope.
5. **Best-effort:** wrap every event handler in try-catch and log via `LoggerInterface`. Listener crashes must not affect the primary run (constitution gotcha).

**Files:**
- `packages/ai-observability/src/Listener/AgentRunTelemetryListener.php`
- `packages/ai-observability/src/AgentTelemetryServiceProvider.php` — registers the listener with the EventDispatcher.
- `packages/ai-observability/tests/Unit/Listener/AgentRunTelemetryListenerTest.php`

**Validation:**
- [ ] Single-run lifecycle (`run_started` → 2 `iteration` → 1 `tool_call_completed` → `run_completed`) produces a single Telescope record with the expected fields.
- [ ] Listener exception path: simulated TelescopeRecorder failure → handler swallows + logs, primary run unaffected.

---

## Subtask T044 — Telemetry persistence

**Purpose:** Land the Telescope persistence integration.

**Steps:**
1. Define the Telescope record shape (a simple associative array fits Telescope's existing recorder API — confirm by reading `packages/telescope/src/`):
   ```
   {
     'run_id', 'agent_definition_id', 'account_id',
     'tokens_in', 'tokens_out', 'cost_cents',
     'tool_call_count',
     'wall_clock_ms', 'iteration_durations_ms',
     'status' (terminal), 'error_code',
     'started_at', 'finished_at'
   }
   ```
2. In `AgentRunTelemetryListener`, on terminal status, call `$telescope->recordAgentRun($record)` (extend the Telescope recorder interface if needed — keep the extension within `packages/ai-observability` ownership; add new methods to a new sub-interface `AgentTelescopeRecorderInterface` if Telescope's base recorder doesn't expose the surface).
3. Update `AgentRun` row's `token_usage_in/out`, `cost_cents`, `tool_call_count` fields (the listener already has these in memory; persist via `AgentRunRepository::save()` at flush time). This is the **only** allowed listener-side write to AgentRun.
4. Add a Prometheus counter / histogram via the existing Telescope-Prometheus bridge (mirror existing patterns in `packages/telescope`):
   - `waaseyaa_agent_run_total{status, agent_id}`
   - `waaseyaa_agent_run_wall_clock_ms` (histogram)
   - `waaseyaa_agent_provider_tokens_total{provider, model, direction}`

**Files:**
- `packages/ai-observability/src/Listener/AgentRunTelemetryListener.php` (extend)
- `packages/ai-observability/src/Recorder/AgentTelescopeRecorderInterface.php` — only if Telescope's base recorder is insufficient.
- `tests/Integration/PhaseN/AgentRuntime/TelemetryTest.php` — end-to-end run produces a Telescope record + Prometheus metric increment.

**Validation:**
- [ ] End-to-end run produces a Telescope record with all documented fields.
- [ ] Prometheus counters increment.
- [ ] `AgentRun.token_usage_in/out`, `cost_cents`, `tool_call_count` populated at terminal status.

---

## Definition of Done

- [ ] T042..T044 checkboxes flipped.
- [ ] `ModelPriceTable` covers Anthropic + OpenAI + NullLlm at minimum.
- [ ] Listener subscribes to lifecycle events; produces a Telescope record per terminal run.
- [ ] `AgentRun.token_usage_in/out`, `cost_cents`, `tool_call_count` populated at terminal.
- [ ] Prometheus metrics increment.
- [ ] All gates green.

## Risks & mitigations

1. **Listener crash propagation.** *Mitigation:* try-catch + log via `LoggerInterface`.
2. **Unknown-model cost NULL leaks downstream.** *Mitigation:* documented in data-model; consumers nil-safe.
3. **Telemetry write contention with worker writes to AgentRun.** *Mitigation:* listener writes at terminal status only (last-write semantics); compare-and-swap on `markTerminal` keeps status invariants intact.

## Reviewer guidance

- Confirm listener crashes can't break a primary run (mock a Telescope recorder that throws).
- Verify the per-iteration latency capture maps 1:1 to executor iteration boundaries.
- Spot-check the Telescope record shape matches Telescope's existing event conventions.

## Implementation command

```
spec-kitty agent action implement WP08 --agent <name>
```
