---
work_package_id: WP05
title: HTTP endpoints + SSE wiring
dependencies:
- WP04
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-019
- FR-020
- FR-022
- FR-031
- FR-033
planning_base_branch: main
merge_target_branch: main
branch_strategy: 'Plan + merge target: main. Lane allocated by finalize-tasks; consult lanes.json.'
subtasks:
- T029
- T030
- T031
- T032
- T033
history:
- date: '2026-05-18T14:55:10Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/api/src/Controller/AgentRunController.php
execution_mode: code_change
owned_files:
- packages/api/src/Controller/AgentRunController.php
- packages/api/src/Controller/AgentRunRequestValidator.php
- packages/api/composer.json
- packages/routing/src/AgentRouteServiceProvider.php
- packages/routing/composer.json
- packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php
- tests/Integration/PhaseN/AgentRuntime/AsyncHttpRunTest.php
- tests/Integration/PhaseN/AgentRuntime/CancellationTest.php
- tests/Integration/PhaseN/AgentRuntime/InteractiveHitlTest.php
tags: []
---

# WP-05 — HTTP endpoints + SSE wiring

## Objective

Expose the four HTTP endpoints from `contracts/agent-run-api.yaml`,
route them through `AgentRouteServiceProvider` in `packages/routing`
(Layer 4), enforce per-route capabilities + initiator ownership,
and push every SSE event from the doctrine vocabulary onto channel
`agent.run.<id>` via `BroadcastStorage::push`.

## Context

- Spec FRs in scope: **FR-001 (HTTP path), FR-002, FR-003, FR-004, FR-019, FR-022, FR-031, FR-033**.
- NFRs in scope: **NFR-002, NFR-003, NFR-005, NFR-006**.
- Constraints applied: **C-011 (reuses `_account`), C-012 (reuses BroadcastStorage SSE)**.
- API contract authoritative: [contracts/agent-run-api.yaml](../contracts/agent-run-api.yaml).
- SSE event vocabulary: [data-model.md](../data-model.md) §"SSE event vocabulary".
- Layer rule: route wiring is Layer-4 (mirrors `Waaseyaa\Routing\AuthOidcRouteServiceProvider`). Controllers can live in `packages/api` (Layer 4).

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T029 — `AgentRunController` (4 endpoints)

**Purpose:** Implement the HTTP surface.

**Steps:**
1. Create `packages/api/src/Controller/AgentRunController.php`. Inject:
   - `AgentRunService`
   - `AgentRunRepository`
   - `AgentAuditLogRepository`
   - `AgentDefinitionRegistry`
   - `AgentRunBroadcaster` (T031)
   - `RequestStack` or equivalent for current request `_account` access.
2. Endpoints (request/response shapes per `contracts/agent-run-api.yaml`):
   - **POST `/api/ai/agent/run`** — parse body, validate via `AgentRunRequestValidator` (T033), build an `AgentRunDraft`, call `AgentRunService::enqueue($draft)`, return `202 Accepted` with `{ run_id, stream_url, status_url, approve_url }`.
   - **GET `/api/ai/agent/run/{id}`** — load row, apply access check, return JSON snapshot per OpenAPI schema. Include `status`, `transcript_json`, `token_usage_in/out`, `cost_cents`, `tool_call_count`, `error_code`, `error_message`.
   - **DELETE `/api/ai/agent/run/{id}`** — apply access check; if row is pre-pickup (`queued`) transition straight to `Cancelled` (`markTerminal`); else flip to `Cancelling` for the worker to honour. Push `run_cancelled` SSE on the terminal transition.
   - **POST `/api/ai/agent/run/{id}/approve`** — body `{ call_id, decision: "approve" | "deny" }`. Require capability `agent.run.approve`. Match `call_id` against `pending_approval_call_id`; mismatch returns 409. On match: flip status back to `Running` (approve) or terminal `Failed/approval_denied` (deny). Append the corresponding audit row. Push `approval_resolved` SSE.
3. Every endpoint returns the standard error envelope on failure: `{ error: { code, message } }` with appropriate HTTP status.
4. Read `_account` (not `account`) from the request (constitution gotcha).

**Files:**
- `packages/api/src/Controller/AgentRunController.php`
- `packages/api/tests/Unit/Controller/AgentRunControllerTest.php`

**Validation:**
- [ ] Controller unit tests cover each endpoint's happy path + a representative error path.
- [ ] PHPStan level 5 passes.

---

## Subtask T030 — `AgentRouteServiceProvider`

**Purpose:** Register routes at Layer 4 (mirror `AuthOidcRouteServiceProvider`).

**Steps:**
1. Create `packages/routing/src/AgentRouteServiceProvider.php` extending `ServiceProvider`.
2. `boot()`:
   - Register routes against the `WaaseyaaRouter` / `RouteBuilder` surface:
     - `POST /api/ai/agent/run` → `AgentRunController::create`. Route options: `_authenticated: true`, `_permission: 'agent.run'`.
     - `GET /api/ai/agent/run/{id}` → `AgentRunController::show`. Route options: `_authenticated: true`, `_permission: 'agent.run'`, `_gate: 'agent_run.view'` (gate evaluated by AccessChecker → resolves via `AgentRunAccessPolicy`).
     - `DELETE /api/ai/agent/run/{id}` → `AgentRunController::cancel`. Same gate.
     - `POST /api/ai/agent/run/{id}/approve` → `AgentRunController::approve`. Route options: `_permission: 'agent.run.approve'`, `_gate: 'agent_run.view'`.
3. Register `Waaseyaa\Routing\AgentRouteServiceProvider` in `packages/routing/composer.json`'s `extra.waaseyaa.providers` array.
4. Add `waaseyaa/api` to `packages/routing` `require` if the route classes are referenced by FQCN (the same trick used by `AuthOidcRouteServiceProvider` — string controller refs work to avoid this if you prefer).

**Files:**
- `packages/routing/src/AgentRouteServiceProvider.php`
- `packages/routing/composer.json`

**Validation:**
- [ ] Routes resolve via the kernel router after boot.
- [ ] `bin/check-package-layers` exits 0.

---

## Subtask T031 — `AgentRunBroadcaster` + SSE event vocabulary

**Purpose:** Concrete broadcaster that materialises the SSE event vocabulary onto `BroadcastStorage::push`.

**Steps:**
1. Create `packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php` implementing `AgentRunBroadcasterInterface` (introduced in WP-04).
2. Inject `BroadcastStorage` (from `packages/api`). For each event in the vocabulary (per data-model § SSE event vocabulary), implement a typed method:
   - `runStarted(Uuid $runId, ?string $agentId, DateTimeImmutable $startedAt): void`
   - `iteration(Uuid $runId, int $iteration, int $tokensUsedSoFar): void`
   - `toolCallStarted(Uuid $runId, string $callId, string $toolName, array $argumentsRedacted): void`
   - `toolCallCompleted(Uuid $runId, string $callId, bool $success, int $durationMs): void`
   - `approvalRequired(Uuid $runId, string $callId, string $toolName, array $arguments, DateTimeImmutable $expiresAt): void`
   - `approvalResolved(Uuid $runId, string $callId, string $decision): void`
   - `providerChunk(Uuid $runId, array $chunk): void`
   - `runCompleted(Uuid $runId, string $response, array $tokenUsage, ?int $costCents, ?string $summary): void`
   - `runFailed(Uuid $runId, string $errorCode, string $errorMessage): void`
   - `runCancelled(Uuid $runId, DateTimeImmutable $cancelledAt): void`
3. Each method shapes the payload per the data-model and calls `$this->storage->push("agent.run.{$runId}", $eventName, $payload)`. **NFR-006**: push is persisted before the worker proceeds — confirm `BroadcastStorage::push` is synchronous-write (it is — see `docs/specs/broadcasting.md`).
4. Bind `AgentRunBroadcaster` as the canonical implementation of `AgentRunBroadcasterInterface` in `AiAgentServiceProvider` (existing file owned by WP-03; this WP modifies bindings within it — coordinate by limiting the edit to ONE line, the interface binding).
5. Wire the broadcaster into `RunAgentHandler` (handler injection was wired in WP-04 via the interface; this WP supplies the concrete implementation).

**Files:**
- `packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php`
- `packages/ai-agent/tests/Unit/Broadcast/AgentRunBroadcasterTest.php`

**Note on cross-WP service-provider edit:** Adding the one-line interface binding to `AiAgentServiceProvider` is necessary. WP-03 owns that file, but the edit here is additive (a `bind(AgentRunBroadcasterInterface::class, AgentRunBroadcaster::class)`) and does not modify any existing line. Reviewers should treat it as a planned coordination point.

**Validation:**
- [ ] Each method writes a `BroadcastStorage::push` call with the documented event name and payload shape.

---

## Subtask T032 — Capability checks + initiator-ownership enforcement

**Purpose:** Defence-in-depth: route-level capability checks + entity-level access enforcement.

**Steps:**
1. Route options (set in T030) cover the capability layer (`_permission`, `_gate`).
2. Inside each controller method, after loading the row, call `AccessChecker::check('view' | 'update' | 'delete', $run, $account)` and return `403` on `forbidden` (**FR-033**).
3. The `approve` endpoint additionally requires the requesting account to hold `agent.run.approve` (route option) **and** pass the `agent_run.view` gate (controller check).
4. Bypass: holders of `agent.run.bypass_ownership` pass the gate for runs they did not initiate; tested in `AgentRunAccessPolicy` unit tests (WP-02) but verified again at HTTP level via integration test.

**Files:**
- `packages/api/src/Controller/AgentRunController.php` (controller-side double-check)
- `tests/Integration/PhaseN/AgentRuntime/AsyncHttpRunTest.php` — initiator path
- additional negative test in same file: stranger account → 403

**Validation:**
- [ ] Stranger account → 403 on GET / DELETE / approve.
- [ ] Bypass holder → 200 / 204 on GET / DELETE.

---

## Subtask T033 — Request validator

**Purpose:** Reject malformed requests with 400 before they touch the service.

**Steps:**
1. Create `packages/api/src/Controller/AgentRunRequestValidator.php` (or as an inner class in the controller — your call; if separate, keep it `final readonly`).
2. Validate the POST `/run` body:
   - Exactly one of `agent_id` (string) or `bundle` (object) is required.
   - `bundle` shape: `{ prompt: string, tools: string[], model: string, system?: string, max_iterations?: int }`.
   - `destructive_approval`: optional, defaults to the agent's `destructiveDefault` if `agent_id` resolves, otherwise `none`. Accept `none | all | interactive` exactly.
   - When `destructive_approval = interactive` and the run is enqueued via HTTP, this is fine (SPA will listen for SSE). When the same body is sent with the inline path (T029 controller doesn't allow inline anyway), the service raises `InvalidArgumentException` per WP-04.
3. Validate POST `/{id}/approve`:
   - `call_id`: required, must match `pending_approval_call_id` (controller does the match; validator only enforces shape).
   - `decision`: exactly `approve` or `deny`.
4. Return `400` with the standard error envelope on failure: `{ error: { code: 'validation_failed', message, field_errors: [...] } }`.

**Files:**
- `packages/api/src/Controller/AgentRunRequestValidator.php`
- `packages/api/tests/Unit/Controller/AgentRunRequestValidatorTest.php`

**Validation:**
- [ ] Missing required field → 400 with field-error array.
- [ ] Both `agent_id` and `bundle` present → 400 (exactly one).
- [ ] Unknown `destructive_approval` value → 400.

---

## Definition of Done

- [ ] T029..T033 checkboxes flipped.
- [ ] Four HTTP endpoints route, authenticate, authorize, and persist correctly.
- [ ] SSE event vocabulary fully covered by `AgentRunBroadcaster`.
- [ ] AsyncHttp + Cancellation + InteractiveHitl integration tests green.
- [ ] All gates green (layers especially — controllers in `packages/api`, routes in `packages/routing`).

## Risks & mitigations

1. **`account` vs `_account` typo.** *Mitigation:* constitution gotcha; controller test asserts request reads `_account`.
2. **Layer regression** if controller imports a Layer-5 type that imports a Layer-6 type. *Mitigation:* `bin/check-package-layers`.
3. **SSE event ordering drift.** *Mitigation:* broadcaster unit tests assert the documented event names + payload shapes.
4. **One-line bind edit to `AiAgentServiceProvider` (WP-03 owned).** *Mitigation:* call it out in the PR description; review limits the edit to that single binding line.

## Reviewer guidance

- Replay the OpenAPI contract: every documented endpoint, status code, and error envelope should be reachable.
- Run the integration tests with `BroadcastStorage` configured against a memory backend; verify the event sequence.
- Confirm route options match the spec capability matrix.

## Implementation command

```
spec-kitty agent action implement WP05 --agent <name>
```
