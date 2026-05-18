---
work_package_id: WP02
title: AgentRun + AgentAuditLog entities
dependencies:
- WP01
requirement_refs:
- FR-009
- FR-027
- FR-032
- FR-033
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-agent-executor-01KRWPK7
base_commit: eb2158425d828b628579169c5ab90ea062a88113
created_at: '2026-05-18T15:53:14.258720+00:00'
subtasks:
- T010
- T011
- T012
- T013
- T014
- T015
- T016
shell_pid: "336760"
agent: "claude:sonnet:implementer:implementer"
history:
- date: '2026-05-18T14:55:10Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/ai-agent/src/Entity/
execution_mode: code_change
owned_files:
- packages/ai-agent/src/Entity/**
- packages/ai-agent/src/Repository/**
- packages/ai-agent/src/Access/**
- packages/ai-agent/src/Enum/**
- packages/ai-agent/migrations/**
- packages/ai-agent/src/AiAgentEntityServiceProvider.php
- packages/ai-agent/composer.json
- packages/access/src/Capability/AgentCapabilities.php
- packages/access/composer.json
- tests/Integration/PhaseN/AgentRuntime/EntityPersistenceTest.php
tags: []
---

# WP-02 — `AgentRun` + `AgentAuditLog` entities

## Objective

Persist run state and audit events as first-class Waaseyaa entities
through `EntityRepository`, replacing the in-memory `AgentAuditLog`
list currently kept inside `AgentExecutor`. Wire the access policy
(initiator ownership + bypass capability), seed the capability set,
and ship the schema migration.

## Context

- Spec FRs in scope: **FR-009, FR-027, FR-032, FR-033**.
- Constraints applied: **C-001, C-003, C-014**.
- Doctrine spec sections: §"Entity schemas" and §"Audit invariants" in `docs/specs/agent-executor.md`.
- Data-model authoritative: [data-model.md](../data-model.md) §"Entities", §"Capabilities".
- Entity-storage rule of record: [.claude/rules/entity-storage-invariant.md](../../../.claude/rules/entity-storage-invariant.md) — **mandatory**.
- The in-memory list removal from `AgentExecutor` happens in **WP-03** (so this WP does not touch `AgentExecutor.php`).

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T010 — `AgentRun` entity + enums

**Purpose:** Define the run aggregate root.

**Steps:**
1. Create enums in `packages/ai-agent/src/Enum/`:
   - `RunStatus.php` — backed-string enum with cases `Queued`, `Running`, `AwaitingApproval`, `Cancelling`, `Cancelled`, `Completed`, `Failed`. Add a static `terminals(): array` returning `[Cancelled, Completed, Failed]`.
   - `HitlMode.php` — backed-string `None`, `All`, `Interactive`.
   - `EventType.php` — backed-string `IterationStart`, `ToolCall`, `ToolResult`, `ProviderCall`, `ApprovalRequired`, `ApprovalGranted`, `ApprovalDenied`, `Error`.
2. Create `packages/ai-agent/src/Entity/AgentRun.php` extending `ContentEntityBase`:
   - Constructor `__construct(array $values = [])` hardcoding `entityTypeId = 'agent_run'` and entity keys per data-model.
   - Public typed properties for every column listed in `data-model.md` § AgentRun.
   - Helper method `isTerminal(): bool` delegating to `RunStatus::terminals()`.
   - `@api` class-level PHPDoc.
3. Register the entity type via the entity-storage invariant pattern: in `AiAgentEntityServiceProvider::register()` (T016), add to `EntityTypeManager`.

**Files:**
- `packages/ai-agent/src/Enum/RunStatus.php`
- `packages/ai-agent/src/Enum/HitlMode.php`
- `packages/ai-agent/src/Enum/EventType.php`
- `packages/ai-agent/src/Entity/AgentRun.php`
- `packages/ai-agent/tests/Unit/Entity/AgentRunTest.php`

**Validation:**
- [ ] `new AgentRun(['id' => 'uuid', 'account_id' => 1, ...])` produces a valid entity.
- [ ] `isTerminal()` returns true for each terminal status.
- [ ] PHPStan level 5 passes.

---

## Subtask T011 — `AgentAuditLog` entity

**Purpose:** Persist append-only event log rows.

**Steps:**
1. Create `packages/ai-agent/src/Entity/AgentAuditLog.php` extending `ContentEntityBase`:
   - `entityTypeId = 'agent_audit_log'`.
   - Public typed properties per data-model.
   - `@api` PHPDoc.
2. Provide a static factory `AgentAuditLog::for(EventType $eventType, Uuid $runId, int $iteration, bool $success, ?string $toolName = null, ?array $toolArguments = null, ?string $toolResultSummary = null, ?int $durationMs = null)` to keep call sites tidy.

**Files:**
- `packages/ai-agent/src/Entity/AgentAuditLog.php`
- `packages/ai-agent/tests/Unit/Entity/AgentAuditLogTest.php`

**Validation:**
- [ ] Static factory produces correctly populated entities.
- [ ] Field nullability matches the data-model.

---

## Subtask T012 — Migration with indexes

**Purpose:** Create the SQL tables and indexes.

**Steps:**
1. Create `packages/ai-agent/migrations/2026_05_18_000001_create_agent_run.php` extending the standard Waaseyaa migration base (see existing migrations under `packages/*/migrations/` for the exact base class).
2. `up()` creates two tables via DBAL `Schema`:
   - `agent_run` with all columns + DBAL types per `data-model.md` § AgentRun.
   - `agent_audit_log` with the columns + types per `data-model.md` § AgentAuditLog.
   - Indexes:
     - `agent_run.idx_agent_run_status_queued_at` on `(status, queued_at)`.
     - `agent_run.idx_agent_run_account_queued_at` on `(account_id, queued_at)` (descending hinted via the migration if backend supports it).
     - `agent_run.idx_agent_run_status_started_at` on `(status, started_at)`.
     - `agent_audit_log.idx_agent_audit_run_occurred_at` on `(run_id, occurred_at)`.
3. `down()` drops both tables.
4. Use `Schema::hasTable()` guards for idempotency (kernel-boot replay tolerance).

**Files:**
- `packages/ai-agent/migrations/2026_05_18_000001_create_agent_run.php`

**Validation:**
- [ ] Migration runs cleanly on SQLite (`DBALDatabase::createSqlite()`).
- [ ] Indexes appear in `sqlite_master`.
- [ ] Migration re-running is a no-op.

---

## Subtask T013 — Repositories

**Purpose:** High-level CRUD via `EntityRepository`.

**Steps:**
1. Create `packages/ai-agent/src/Repository/AgentRunRepository.php`:
   - Constructor injects `EntityTypeManager`, `EntityStorageDriverInterface`, `EventDispatcherInterface`.
   - Exposes typed wrappers: `find(Uuid $id): ?AgentRun`, `save(AgentRun $run): void`, `markRunning(Uuid $id, DateTimeImmutable $startedAt): bool`, `markTerminal(Uuid $id, RunStatus $status, DateTimeImmutable $finishedAt, ?string $errorCode = null, ?string $errorMessage = null): bool`, `findStuckRunning(DateTimeImmutable $threshold): iterable<AgentRun>`, `findOldByQueuedAt(DateTimeImmutable $threshold): iterable<AgentRun>`.
   - `markRunning` and `markTerminal` use compare-and-swap (status precondition) and return `false` if the row already advanced. This protects **C-014**.
2. Create `packages/ai-agent/src/Repository/AgentAuditLogRepository.php`:
   - `append(AgentAuditLog $log): void`.
   - `findByRunId(Uuid $runId): iterable<AgentAuditLog>`.
   - `purgeOlderThan(DateTimeImmutable $threshold): int` — the **only** mutation allowed outside append.
3. Both repositories use the canonical pipeline from `.claude/rules/entity-storage-invariant.md`.

**Files:**
- `packages/ai-agent/src/Repository/AgentRunRepository.php`
- `packages/ai-agent/src/Repository/AgentAuditLogRepository.php`
- `packages/ai-agent/tests/Unit/Repository/AgentRunRepositoryTest.php`
- `packages/ai-agent/tests/Unit/Repository/AgentAuditLogRepositoryTest.php`

**Validation:**
- [ ] Round-trip CRUD against `DBALDatabase::createSqlite()`.
- [ ] `markTerminal()` returns false when row is already terminal (C-014 protection).
- [ ] Append-only behaviour: `AgentAuditLogRepository` exposes no update/delete except `purgeOlderThan`.

---

## Subtask T014 — `AgentRunAccessPolicy`

**Purpose:** Enforce initiator ownership with bypass capability.

**Steps:**
1. Create `packages/ai-agent/src/Access/AgentRunAccessPolicy.php` implementing both `AccessPolicyInterface` and `FieldAccessPolicyInterface` per the constitution.
2. `#[PolicyAttribute(entityType: 'agent_run')]` class attribute.
3. Entity access semantics:
   - `access('view' | 'update' | 'delete', AgentRun $run, AccountInterface $account)`:
     - `AccessResult::allowed()` if `$account->id() === $run->account_id` (initiator) **or** `$account->hasCapability('agent.run.bypass_ownership')`.
     - `AccessResult::neutral()` otherwise — let other policies decide (none expected for v1).
   - `access('create', null, AccountInterface $account)`: allowed iff `agent.run` capability.
4. Field access semantics: neutral (open-by-default) for all fields; future field-level redaction can extend.
5. Apply the same policy class to `agent_audit_log` via a second `#[PolicyAttribute(entityType: 'agent_audit_log')]` and resolve the related run through `AgentRunRepository::find($log->run_id)`.

**Files:**
- `packages/ai-agent/src/Access/AgentRunAccessPolicy.php`
- `packages/ai-agent/tests/Unit/Access/AgentRunAccessPolicyTest.php`

**Validation:**
- [ ] Initiator account: allowed for all CRUD.
- [ ] Different non-bypass account: neutral (effectively denied).
- [ ] Bypass-capability account: allowed.
- [ ] Anonymous account: neutral / denied.

> **Test pattern:** anonymous classes implementing the intersection type are required (per CLAUDE.md gotcha — `createMock()` can't mock intersection types).

---

## Subtask T015 — Capability seed

**Purpose:** Make the capability table visible to `packages/access` so it can be granted to roles.

**Steps:**
1. Create `packages/access/src/Capability/AgentCapabilities.php`:
   - Class-level `@api`.
   - Static method `seed(): array` returning a list of capability descriptors per `data-model.md` § Capabilities, including: `agent.run`, `agent.run.approve`, `agent.run.bypass_ownership`, `tool.entity.read`, `tool.entity.list`, `tool.entity.create`, `tool.entity.update`, `tool.entity.delete`, `tool.entity.search`, `tool.relationship.traverse`, `tool.vector.search`. (Remote `tool.mcp.*` capabilities are registered at boot in WP-07.)
2. Wire the seed into `packages/access`'s capability registration mechanism (locate via grep on existing seed wiring in the package; mirror its shape).
3. `agent.run.approve` defaults to a separate-but-symmetric grant — holders of `agent.run` are granted `agent.run.approve` in the default seed (per plan resolution R-002).

**Files:**
- `packages/access/src/Capability/AgentCapabilities.php`
- `packages/access/tests/Unit/Capability/AgentCapabilitiesTest.php`

**Validation:**
- [ ] Seed exposes the 11 capability names.
- [ ] `agent.run` and `agent.run.approve` share the same default grant.

---

## Subtask T016 — `AiAgentEntityServiceProvider` wiring + composer.json bumps

**Purpose:** Wire entity types, storage drivers, repositories, and the access policy into the kernel without touching the main `AiAgentServiceProvider` (owned by WP-03).

**Steps:**
1. Create `packages/ai-agent/src/AiAgentEntityServiceProvider.php` extending `ServiceProvider`:
   - `register()`:
     - Add `EntityType` registrations for `agent_run` + `agent_audit_log` via `EntityTypeManager`.
     - Bind `AgentRunRepository` + `AgentAuditLogRepository` as singletons.
     - Register `AgentRunAccessPolicy` (the policy attribute carries entity binding; this step ensures the policy is reflectable by the access registry).
2. Add `Waaseyaa\AI\Agent\AiAgentEntityServiceProvider` to `packages/ai-agent/composer.json`'s `extra.waaseyaa.providers` array.
3. Bump `packages/ai-agent/composer.json`:
   - Add `waaseyaa/access` to `require` if not already present.
   - Sort packages (CP-001).
4. Add `packages/access/composer.json` bumps if a capability-seed dependency change is required (mirror existing seed wiring).

**Files:**
- `packages/ai-agent/src/AiAgentEntityServiceProvider.php`
- `packages/ai-agent/composer.json`
- `packages/access/composer.json` (may be untouched if seed wiring is internal)
- `tests/Integration/PhaseN/AgentRuntime/EntityPersistenceTest.php` — boot kernel, persist a run, replay audit log, assert the policy denies a non-owner.

**Validation:**
- [ ] Kernel boot succeeds.
- [ ] Integration test passes against SQLite.
- [ ] `bin/check-composer-policy` exits 0.
- [ ] `bin/check-package-layers` exits 0.

---

## Definition of Done

- [ ] T010..T016 checkboxes flipped.
- [ ] Migration runs cleanly; four indexes exist.
- [ ] Repositories support the operations called for by WP-03 / WP-04 (no API surface changes after this WP).
- [ ] Access policy enforces initiator-or-bypass at entity level.
- [ ] Capability seed exposes 11 names.
- [ ] All gates green.

## Risks & mitigations

1. **DBAL column-type variance across backends.** *Mitigation:* `Types::TEXT` per data-model; application enforces the 256 KB cap, not the column max.
2. **Bypass-capability policy mistake.** *Mitigation:* unit-test policy with three accounts (initiator, stranger, bypass holder).
3. **`createMock()` on intersection type fails.** *Mitigation:* use anonymous classes (constitution gotcha).

## Reviewer guidance

- Check every column in the migration against `data-model.md` § AgentRun / § AgentAuditLog.
- Spot-check the indexes — they're load-bearing for the reaper and user-history query paths.
- Verify access policy implements **both** interfaces and `#[PolicyAttribute]` is class-level.
- Verify `markTerminal` returns false on already-terminal status (C-014 compliance).

## Implementation command

```
spec-kitty agent action implement WP02 --agent <name>
```

## Activity Log

- 2026-05-18T15:53:15Z – claude:sonnet:implementer:implementer – shell_pid=336760 – Assigned agent via action command
