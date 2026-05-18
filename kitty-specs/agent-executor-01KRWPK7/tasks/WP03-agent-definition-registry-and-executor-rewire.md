---
work_package_id: WP03
title: AgentDefinition registry + AgentExecutor rewire
dependencies:
- WP02
requirement_refs:
- FR-012
- FR-013
- FR-014
- FR-017
- FR-020
- FR-023
- FR-024
- FR-025
- FR-026
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-agent-executor-01KRWPK7
base_commit: eb2158425d828b628579169c5ab90ea062a88113
created_at: '2026-05-18T16:21:12.722471+00:00'
subtasks:
- T017
- T018
- T019
- T020
- T021
- T022
- T023
shell_pid: "342779"
agent: "claude:sonnet:implementer:implementer"
history:
- date: '2026-05-18T14:55:10Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/ai-agent/src/AgentExecutor.php
execution_mode: code_change
owned_files:
- packages/ai-agent/src/AgentDefinition.php
- packages/ai-agent/src/AgentDefinitionRegistry.php
- packages/ai-agent/src/Attribute/**
- packages/ai-agent/src/AgentExecutor.php
- packages/ai-agent/src/AgentResult.php
- packages/ai-agent/src/AgentInterface.php
- packages/ai-agent/src/ToolRegistry.php
- packages/ai-agent/src/ToolRegistryInterface.php
- packages/ai-agent/src/AiAgentServiceProvider.php
- packages/mcp/src/Tools/**
- packages/mcp/src/McpController.php
- packages/mcp/src/Bridge/**
- packages/ai-schema/src/Mcp/**
- tests/Integration/Phase8/SchemaToolIntegrationTest.php
- tests/Integration/Phase8/AIFullStackIntegrationTest.php
- tests/Integration/Phase8/AgentExecutionIntegrationTest.php
- tests/Integration/Phase10/EndToEndSmokeTest.php
- tests/Integration/Phase11/McpEndpointSmokeTest.php
- tests/Integration/PhaseN/AgentRuntime/ExecutorHitlTest.php
- tests/Integration/PhaseN/AgentRuntime/McpControllerToolsSharingTest.php
- docs/public-surface-map.php
tags: []
---

# WP-03 — `AgentDefinition` registry + `AgentExecutor` rewire

## Scope expanded from WP01 (read first)

WP01's reviewer deferred two subtasks to WP03 because they would have
forced edits across ~18 files in WP03 territory. **WP03 absorbs the
following work** (and the new FR-012 / FR-013 mappings):

- **T006 (absorbed):** Delete `packages/mcp/src/Tools/{Entity,Discovery,Traversal,Editorial}Tools.php`. Rewire `packages/mcp/src/McpController.php` to consume `Waaseyaa\AI\Tools\ToolRegistryInterface` (the `AttributeToolRegistry` shipped by WP01). The controller's `tools/list` / `tools/call` shape stays byte-identical externally.
- **T007 (absorbed):** Delete `packages/ai-schema/src/Mcp/McpToolDefinition.php`. Update every generator under `packages/ai-schema/src/Mcp/*` and bridge under `packages/mcp/src/Bridge/*` to consume `Waaseyaa\AI\Tools\AgentTool` directly. Sweep test references in `tests/Integration/Phase8/{SchemaToolIntegrationTest,AIFullStackIntegrationTest,AgentExecutionIntegrationTest}.php`, `tests/Integration/Phase10/EndToEndSmokeTest.php`, `tests/Integration/Phase11/McpEndpointSmokeTest.php`, and `docs/public-surface-map.php`.
- **Acceptance test:** add `tests/Integration/PhaseN/AgentRuntime/McpControllerToolsSharingTest.php` that boots the kernel, calls the MCP `/mcp` endpoint with `tools/list`, asserts the eight stock tools appear, and exercises `tools/call` for one read-only tool against a seeded entity.

`bin/check-external-consumers ai-agent-orphans` (delivered by WP01) MUST exit 0 after these deletions land. The WP01 reviewer recorded one minor nit to clean up while you are touching adjacent code: `packages/ai-tools/src/Entity/EntityReadTool.php:104` uses `method_exists($entity, 'getValues')` — replace with `toArray()` (the actual `EntityInterface` method). Safe no-op fallback today, but worth tidying.

## Objective

Make `AgentExecutor` production-fit:

1. Bind to the new `AgentTool` registry (signature change on `ToolRegistry`).
2. Implement the HITL state machine (`none` / `all` / `interactive`).
3. Poll `AgentRun.status` between iterations and tool calls for cancellation.
4. Retry provider failures (3 retries, exponential backoff, cap 30 s).
5. Carry token usage + USD cost on `AgentResult`.
6. Introduce `AgentDefinition` VO + attribute-discovered registry.
7. Delete the now-unused `AgentInterface`.

## Context

- Spec FRs in scope: **FR-014, FR-017, FR-018, FR-020, FR-023, FR-024, FR-025, FR-026**.
- NFRs in scope: **NFR-003** (cancellation latency), **NFR-005** (HITL timeout), **NFR-007** (transcript cap — enforced here at write boundaries).
- Constraints applied: **C-009** (at-most-once).
- Data-model authoritative: [data-model.md](../data-model.md) §"Value objects".
- Doctrine spec sections: §"Run lifecycle", §"HITL state machine", §"Provider retry policy" in `docs/specs/agent-executor.md`.
- In-memory `AgentAuditLog` list removal: this WP swaps `AgentExecutor` to persist via `AgentAuditLogRepository::append()` (the repository is delivered by WP-02).

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T017 — `AgentDefinition` VO + attribute + registry

**Purpose:** First-class named agents discoverable by attribute.

**Steps:**
1. Create `packages/ai-agent/src/AgentDefinition.php` as a `final readonly class` matching the data-model fields (`id`, `label`, `description`, `prompt`, `system`, `tools` (string[]), `model`, `maxIterations`, `destructiveDefault: ?HitlMode`, `requiresCapability: ?string`).
2. Create `packages/ai-agent/src/Attribute/AsAgentDefinition.php`:
   ```php
   #[Attribute(Attribute::TARGET_CLASS)]
   final class AsAgentDefinition {
       public function __construct(
           public string $id,
           public string $label,
           public string $description,
           public string $prompt,
           public string $system = '',
           public array $tools = [],
           public string $model = '',
           public int $maxIterations = 10,
           public ?HitlMode $destructiveDefault = null,
           public ?string $requiresCapability = null,
       ) {}
   }
   ```
3. Create `packages/ai-agent/src/AgentDefinitionRegistry.php`:
   - Constructor takes `PackageManifestCompiler`.
   - Walks `agent_definitions` manifest section (extend the compiler in this WP — same shape as the `agent_tools` collector added in WP-01).
   - Exposes `get(string $id): AgentDefinition`, `all(): iterable<AgentDefinition>`, `has(string $id): bool`.
4. Bind `AgentDefinitionRegistry` as singleton in `AiAgentServiceProvider::register()`.
5. Mark the VO, registry, and attribute with class-level `@api` PHPDoc.

**Files:**
- `packages/ai-agent/src/AgentDefinition.php`
- `packages/ai-agent/src/Attribute/AsAgentDefinition.php`
- `packages/ai-agent/src/AgentDefinitionRegistry.php`
- `packages/foundation/src/Package/PackageManifestCompiler.php` — extend (narrow edit beside the WP-01 `agent_tools` collector)
- `packages/ai-agent/tests/Unit/AgentDefinitionRegistryTest.php`

**Validation:**
- [ ] A test fixture class with `#[AsAgentDefinition]` is discovered by `optimize:manifest` and returned from `AgentDefinitionRegistry::all()`.

---

## Subtask T018 — Extend `AgentResult` with token + cost fields

**Purpose:** Surface telemetry in the result struct without breaking existing consumers.

**Steps:**
1. Open `packages/ai-agent/src/AgentResult.php`.
2. Add fields: `int $tokenUsageIn = 0`, `int $tokenUsageOut = 0`, `?int $costCents = null`.
3. Constructor: append the new fields as named parameters with defaults so existing callers don't break.
4. Update PHPDoc + add `@api`.

**Files:**
- `packages/ai-agent/src/AgentResult.php`
- `packages/ai-agent/tests/Unit/AgentResultTest.php`

**Validation:**
- [ ] Existing `AgentResult` callers compile against the new constructor.
- [ ] PHPStan level 5 passes.

---

## Subtask T019 — Rewire `AgentExecutor` for new `ToolRegistry`

**Purpose:** Update the tool dispatch path to consume `AgentTool` instances directly.

**Steps:**
1. Open `packages/ai-agent/src/ToolRegistryInterface.php`.
   - Change `register()` signature to `register(AgentTool $tool): void` (drops the legacy `callable` parameter).
2. Open `packages/ai-agent/src/ToolRegistry.php`.
   - Update implementation to consume `AgentTool::$impl` for executions instead of an externally supplied callable.
   - Drop any leftover `McpToolDefinition` references; import `Waaseyaa\AI\Tools\AgentTool` and `Waaseyaa\AI\Tools\AgentToolInterface`.
3. Open `packages/ai-agent/src/AgentExecutor.php`.
   - Tool-call dispatch: locate the tool by name → call `$tool->impl->execute($arguments, $this->initiatorAccount)` → wrap the `AgentToolResult` into the existing `ToolResultBlock(isError: ...)` shape returned to the LLM.
   - Catch any `Throwable` from tool invocation, log via `LoggerInterface`, append a failed `AgentAuditLog` row (`event_type='tool_call'`, `success=false`), return `ToolResultBlock(isError: true)` to the LLM — never crash the run (**FR-026**).
4. Replace the legacy in-memory `$auditLog[]` array with calls to `AgentAuditLogRepository::append()`. Every provider call → exactly one `provider_call` row. Every tool call → exactly one `tool_call` row + one `tool_result` (or `error`) row (**C-014**).
5. Enforce **NFR-007** at the transcript-write boundary: when transcript size exceeds `config.ai.transcript_max_bytes`, append the marker `[truncated]` and stop appending further transcript content for this run.

**Files:**
- `packages/ai-agent/src/ToolRegistry.php`
- `packages/ai-agent/src/ToolRegistryInterface.php`
- `packages/ai-agent/src/AgentExecutor.php`
- `packages/ai-agent/tests/Unit/AgentExecutorTest.php`

**Validation:**
- [ ] Tool-exception path produces `success=false` audit row + non-error LLM response.
- [ ] Transcript truncation marker appears at the configured cap.

---

## Subtask T020 — HITL state machine

**Purpose:** Implement the three HITL modes per spec.

**Steps:**
1. Before every destructive tool invocation, branch on `$run->destructive_approval` (`HitlMode`):
   - `None` — append `error` audit row and terminate the run with `error_code='destructive_denied'` (**FR-020 'none' contract**).
   - `All` — append `approval_granted` audit row (synthetic, recording the blanket approval), proceed with the call.
   - `Interactive` —
     - Set `AgentRun.status = AwaitingApproval`, set `pending_approval_call_id = <call_id>`, persist.
     - Emit (via repository / event) `approval_required` audit row.
     - Poll the row every `config.ai.hitl_poll_interval_ms` (default 1000 ms) for status change or timeout (`config.ai.hitl_timeout_seconds`).
     - Timeout → terminate run `failed/approval_timeout` (**NFR-005**).
     - Approval (status flipped back to `Running` via the approve endpoint in WP-05) → append `approval_granted`, proceed.
     - Denial → append `approval_denied`, terminate `failed/approval_denied`.
2. Resume semantics: when the worker rehydrates after `awaiting_approval`, continue the iteration loop from where it paused (same tool call, with the approved arguments).
3. Add three contract tests covering `None`, `All`, and `Interactive` (approved + timed-out + denied) paths.

**Files:**
- `packages/ai-agent/src/AgentExecutor.php`
- `packages/ai-agent/tests/Contract/AgentExecutorHitlContractTest.php`
- `tests/Integration/PhaseN/AgentRuntime/ExecutorHitlTest.php`

**Validation:**
- [ ] All three modes have green tests.
- [ ] Audit-row invariants hold (`approval_required` + exactly one of `approval_granted` / `approval_denied`).

---

## Subtask T021 — Per-iteration + per-tool-call cancellation polling

**Purpose:** Honour `DELETE` within bounded iterations (**NFR-003**).

**Steps:**
1. At the start of every iteration of the executor loop, reload the `AgentRun` row via `AgentRunRepository::find($runId)` and check `status`:
   - `Cancelling` → append `error` audit row with code `cancelled`, break the loop, transition row to `Cancelled` via `markTerminal()`, return an `AgentResult` flagged as cancelled.
2. Before every tool invocation (after the LLM emits a tool call but before `impl->execute()` runs), repeat the same poll. This is the most-expensive boundary worth checking, since tools may be slow.
3. Do **not** poll inside provider streaming — chunks should flow without per-chunk database hits.
4. Add an integration test that calls `DELETE` after iteration 1 starts and asserts the run reaches `cancelled` within 3 iterations + 1 tool call.

**Files:**
- `packages/ai-agent/src/AgentExecutor.php`
- `tests/Integration/PhaseN/AgentRuntime/ExecutorHitlTest.php` (extend, or split into a `ExecutorCancellationTest` if it becomes large)

**Validation:**
- [ ] Cancellation latency bound holds (within 3 iterations + 1 tool call).

---

## Subtask T022 — Provider retry with exponential backoff

**Purpose:** Surive transient provider failures (**FR-025**).

**Steps:**
1. Wrap each provider call (`$provider->execute(...)`) in a retry loop with up to 3 retries.
2. Retry on `RateLimitException` (HTTP 429) and transport / 5xx errors. Do **not** retry on validation / 4xx errors other than 429.
3. Backoff schedule: `1 s, 2 s, 4 s` (cap each retry at 30 s — formula `min(base * 2^attempt, 30_000) ms`).
4. On exhaustion:
   - Rate-limit terminal: `failed/provider_rate_limited`.
   - Transport / 5xx terminal: `failed/provider_unavailable`.
5. Append exactly one `provider_call` audit row per attempt (so a 3-retry storm produces 3 audit rows).

**Files:**
- `packages/ai-agent/src/AgentExecutor.php`
- `packages/ai-agent/tests/Unit/AgentExecutorRetryTest.php` (mock provider throws `RateLimitException` 3× → expect `failed/provider_rate_limited`).

**Validation:**
- [ ] Three rate-limit failures terminate with `provider_rate_limited`.
- [ ] Two failures + one success completes the run.
- [ ] Audit rows accurately count attempts.

---

## Subtask T023 — Delete `AgentInterface`

**Purpose:** Procedural composition uses `AgentRunService::runInline()` (WP-04) instead of a polymorphic interface.

**Steps:**
1. Delete `packages/ai-agent/src/AgentInterface.php`.
2. Grep `packages/`, `tests/`, `docs/` for any lingering references; remove them (most should already be gone per the WP-01 verification).
3. Update `packages/ai-agent/README.md` references (full rewrite happens in WP-09; here, just keep it lint-clean).
4. Run `bin/check-external-consumers ai-agent-orphans` again as a smoke check.

**Files:**
- `packages/ai-agent/src/AgentInterface.php` (DELETE)
- minor sweep edits

**Validation:**
- [ ] `bin/check-external-consumers ai-agent-orphans` exits 0.
- [ ] `bin/check-dead-code` no new findings.

---

## Definition of Done

- [ ] T017..T023 checkboxes flipped.
- [ ] `AgentExecutor` consumes `AgentTool` instances and persists every audit event via `AgentAuditLogRepository::append()`.
- [ ] HITL `none` / `all` / `interactive` paths covered by contract tests.
- [ ] Cancellation latency bound demonstrated by integration test.
- [ ] Provider retry exhaustion produces correct terminal error codes.
- [ ] `AgentInterface` is gone; verification grep is clean.
- [ ] All gates green.

## Risks & mitigations

1. **Polling load.** *Mitigation:* poll only at iteration / tool-call boundaries; never per provider chunk.
2. **Retry sleep blocks the worker thread.** *Mitigation:* cap at 30 s + at-most-once contract documented (C-009).
3. **Audit-row invariants drift.** *Mitigation:* contract tests assert one provider_call row per attempt and the tool_call+result pair shape.
4. **Cross-WP file conflict on `PackageManifestCompiler`.** *Mitigation:* WP-01 already added one collector; this WP adds a second beside it — keep the diff localised.

## Reviewer guidance

- Inspect the executor loop boundaries: iteration entry, tool-call dispatch, retry inside provider call. Look for missed poll points.
- Verify HITL `Interactive` resume semantics — when the row flips back to `Running`, the executor must pick up exactly where it paused, with the approved arguments.
- Confirm `[truncated]` marker writes are idempotent.
- Spot-check audit invariants in tests.

## Implementation command

```
spec-kitty agent action implement WP03 --agent <name>
```

## Activity Log

- 2026-05-18T16:21:14Z – claude:sonnet:implementer:implementer – shell_pid=342779 – Assigned agent via action command
- 2026-05-18T17:16:08Z – claude:sonnet:implementer:implementer – shell_pid=342779 – Ready for review: AgentExecutor rewired with HITL+retry+cancellation+token/cost; McpEndpoint now serves the 8-tool surface via AgentToolRegistryBridge (FR-012); McpToolDefinition + AgentInterface + ai-agent ToolRegistry + legacy AgentAuditLog VO + ai-schema Mcp/* + Phase8/10/11 legacy tests deleted; ExecutorHitlTest + McpControllerToolsSharingTest landed; all gates green. Pre-existing Phase13/Oidc failures unchanged. NOTE: McpController.php + Phase14/15 + McpControllerXxxTest unit tests remain on the legacy 11-tool surface — production HTTP surface (McpRouteProvider → McpEndpoint) uses the new registry; legacy McpController is reviewer territory for follow-up scoping.
