---
work_package_id: WP03
title: Broadcaster Consolidation
dependencies:
- WP02
requirement_refs:
- FR-006
- FR-007
- FR-012
- C-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T019
- T020
- T021
- T022
- T023
history:
- date: '2026-05-20T23:57:13Z'
  event: created
authoritative_surface: packages/ai-agent/src/Broadcast/
execution_mode: code_change
owned_files:
- packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php
- packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php
- packages/ai-agent/src/Broadcast/AgentRunBroadcasterServiceProvider.php
- packages/ai-agent/src/MessagingServiceProvider.php
- packages/ai-agent/tests/Unit/Broadcast/AgentRunBroadcasterTest.php
tags: []
---

# WP03 — Broadcaster Consolidation

**Closes**: #1511
**Depends on**: WP02 (OpenAPI document must exist for T023 alignment)
**Implement command**: `spec-kitty agent action implement WP03 --agent <name>`

## Objective

Delete `BroadcastStorageAdapter` (a WP04-era stub that was superseded by `AgentRunBroadcaster` in WP05 of the predecessor mission), clean `MessagingServiceProvider` to use the canonical `AgentRunBroadcasterServiceProvider` binding, update the broadcaster test, and confirm the `pending_approval` OpenAPI shape matches the broadcaster's actual emission.

## Context

The plan's grep confirmed `BroadcastStorageAdapter` is referenced **only within `packages/ai-agent/`**:
- `packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php` (the class itself)
- `packages/ai-agent/src/Broadcast/AgentRunBroadcasterInterface.php` (referenced in doc comment or type)
- `packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php` (may reference it)
- `packages/ai-agent/src/Broadcast/AgentRunBroadcasterServiceProvider.php` (binding target)
- `packages/ai-agent/src/MessagingServiceProvider.php` (currently binds adapter at line 68)

No external consumers. Direct deletion per greenfield removal policy (DIR-003) — no `@deprecated` staging needed.

`MessagingServiceProvider` currently (line 68):
```php
fn(): AgentRunBroadcasterInterface => new BroadcastStorageAdapter(...)
```
After this WP, `MessagingServiceProvider` should either delegate to `AgentRunBroadcasterServiceProvider` or remove the redundant binding entirely if `AgentRunBroadcasterServiceProvider` is already registered.

## Subtasks

### T019 — Verify `AgentRunBroadcasterServiceProvider` is the canonical binding

**File**: `packages/ai-agent/src/Broadcast/AgentRunBroadcasterServiceProvider.php`

**Purpose**: Confirm `AgentRunBroadcasterServiceProvider` binds `AgentRunBroadcasterInterface` → `AgentRunBroadcaster` correctly and is registered in the package's `composer.json` `extra.waaseyaa.providers` list.

**Steps**:
1. Open `packages/ai-agent/src/Broadcast/AgentRunBroadcasterServiceProvider.php`.
2. Confirm the `register()` method binds `AgentRunBroadcasterInterface::class` → `AgentRunBroadcaster::class` (or creates a singleton).
3. Open `packages/ai-agent/composer.json` and check `extra.waaseyaa.providers` — confirm `AgentRunBroadcasterServiceProvider` is listed.
4. If `MessagingServiceProvider` also lists itself in providers, trace the order to confirm `AgentRunBroadcasterServiceProvider` wins (last-write or DI container logic).
5. Note any configuration (constructor args for `AgentRunBroadcaster` — it likely needs `BroadcastStorage` injected) and confirm the binding handles it correctly.

**Validation**:
- [ ] `AgentRunBroadcasterServiceProvider` binds `AgentRunBroadcasterInterface` → `AgentRunBroadcaster`
- [ ] Listed in `packages/ai-agent/composer.json` `extra.waaseyaa.providers`
- [ ] No binding conflict with `MessagingServiceProvider`'s current adapter binding

---

### T020 — Delete `BroadcastStorageAdapter` and its test

**Files**: 
- `packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php` (DELETE)
- `packages/ai-agent/tests/Unit/Broadcast/BroadcastStorageAdapterTest.php` (DELETE if exists)

**Purpose**: Remove the superseded adapter class. SC-005 states: `! [ -f packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php ]` must be true.

**Steps**:
1. Run: `grep -rn "BroadcastStorageAdapter" /home/jones/dev/waaseyaa/packages/ --include="*.php"` to confirm all reference sites (should match the plan's list of 5 files, all within `packages/ai-agent/`).
2. Check if a test file exists: `packages/ai-agent/tests/Unit/Broadcast/BroadcastStorageAdapterTest.php`.
3. Delete `BroadcastStorageAdapter.php` via `git rm packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php`.
4. If the adapter test exists, delete it: `git rm packages/ai-agent/tests/Unit/Broadcast/BroadcastStorageAdapterTest.php`.
5. Check `phpstan-dead-code-baseline.neon` — if `BroadcastStorageAdapter` has entries there, remove them.

**Validation**:
- [ ] `packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php` does not exist
- [ ] No remaining `use Waaseyaa\AI\Agent\Broadcast\BroadcastStorageAdapter` anywhere in the repo
- [ ] PHPStan baseline updated if needed

---

### T021 — Clean `MessagingServiceProvider`

**File**: `packages/ai-agent/src/MessagingServiceProvider.php`

**Purpose**: Remove the adapter reference from `MessagingServiceProvider`. The binding should defer to `AgentRunBroadcasterServiceProvider`.

**Steps**:
1. Open `packages/ai-agent/src/MessagingServiceProvider.php`.
2. Remove the `use Waaseyaa\AI\Agent\Broadcast\BroadcastStorageAdapter;` import (line 15).
3. Remove or rewrite the binding at line 68:
   ```php
   // BEFORE:
   fn(): AgentRunBroadcasterInterface => new BroadcastStorageAdapter(...)
   
   // AFTER: Remove this binding entirely if AgentRunBroadcasterServiceProvider
   // handles it. Or, if MessagingServiceProvider must keep it, delegate:
   // fn(): AgentRunBroadcasterInterface => $this->container->get(AgentRunBroadcaster::class)
   ```
4. Check the comment at line 41 ("WP-04 rebinds this...") — remove the now-outdated comment.
5. If removing the binding leaves `MessagingServiceProvider::register()` empty or redundant with `AgentRunBroadcasterServiceProvider`, consider whether `MessagingServiceProvider` still serves a purpose. If not, mark it for removal in WP05 (do not delete in this WP unless it's safe).
6. Run `bin/waaseyaa optimize:manifest` mentally — the manifest needs to pick up the removal.

**Validation**:
- [ ] No `BroadcastStorageAdapter` import or reference in `MessagingServiceProvider`
- [ ] `AgentRunBroadcasterInterface` is still bound (either by `AgentRunBroadcasterServiceProvider` or by a clean rebind in `MessagingServiceProvider`)
- [ ] PHP syntax clean (no dangling imports)

---

### T022 — Update/create `AgentRunBroadcasterTest` (FR-012)

**File**: `packages/ai-agent/tests/Unit/Broadcast/AgentRunBroadcasterTest.php`

**Purpose**: Regression coverage for the canonical broadcaster. Confirms `AgentRunBroadcaster::push()` calls `BroadcastStorage::push()` with the correct channel name and payload.

**Steps**:
1. Check if `AgentRunBroadcasterTest.php` already exists.
2. If it exists: review and update to cover the canonical `AgentRunBroadcaster` (remove any tests that covered the adapter).
3. If it doesn't exist: create it.
4. Test methods:

   **Test 1 — `pushCallsBroadcastStorageWithCorrectChannel`**:
   - Create a mock `BroadcastStorage`.
   - Call `AgentRunBroadcaster::push(runId: 'abc', event: 'agent.run.started', data: ['key' => 'val'])`.
   - Assert `BroadcastStorage::push()` was called with channel `agent.run.abc`, event `agent.run.started`, and the expected payload.

   **Test 2 — `pushCatchesAndLogsStorageException`**:
   - Mock `BroadcastStorage::push()` throws `\RuntimeException`.
   - Assert `AgentRunBroadcaster::push()` does NOT re-throw.
   - Assert the logger receives an `error` call (if logger is injectable — check `AgentRunBroadcaster` constructor).

5. Use `#[Test]`, `#[CoversClass(AgentRunBroadcaster::class)]` attributes.

**Validation**:
- [ ] Test file exists and passes
- [ ] Covers channel naming and error logging
- [ ] No references to deleted `BroadcastStorageAdapter`

---

### T023 — Confirm `pending_approval` OpenAPI shape matches broadcaster emission

**Files**: `packages/api/openapi.yaml` (edit if drift found), `packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php` (read-only for comparison)

**Purpose**: SC-006 requires the OpenAPI `pending_approval` shape to match what `AgentRunBroadcaster` actually emits. This is the alignment verification step.

**Steps**:
1. Open `packages/ai-agent/src/Broadcast/AgentRunBroadcaster.php` and find all `push(...)` call sites.
2. Identify the `pending_approval` push — the payload array should list the exact fields emitted (e.g., `run_id`, `state`, `awaiting_tool`, `requested_at`).
3. Open `packages/api/openapi.yaml` (created in WP02/T016).
4. Compare the `AgentRunPendingApproval` schema fields against the actual push payload:
   - All `required` fields in the schema must be present in every push call.
   - Nullable fields in the schema must be actually nullable in the push.
   - Field names must match exactly (snake_case, no camelCase mismatch).
   - Enum values for `state` must include `pending_approval` with exact casing.
5. If drift is found, update `packages/api/openapi.yaml` to reflect reality (the schema must match the code, not the other way around unless FR-007 specifies a different intended shape).
6. Run `bin/check-openapi` to confirm the updated file is still valid.
7. Note any corrections made in the WP commit message for reviewer visibility.

**Validation**:
- [ ] `pending_approval` schema fields match `AgentRunBroadcaster` push payload exactly
- [ ] `bin/check-openapi` passes on the updated `openapi.yaml`
- [ ] SC-006 passes: schema alignment verified

---

## Branch Strategy

**Planning/base branch**: `main`
**Merge target**: `main`
**Execution**: Worktree allocated per `lanes.json`. Depends on WP02 (`packages/api/openapi.yaml` must exist for T023).

## Definition of Done

- [ ] `BroadcastStorageAdapter.php` deleted (SC-005: `! [ -f packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php ]`)
- [ ] No `BroadcastStorageAdapter` reference anywhere in `packages/` (confirmed by grep)
- [ ] `MessagingServiceProvider` cleaned of adapter reference
- [ ] `AgentRunBroadcasterServiceProvider` is the sole binding for `AgentRunBroadcasterInterface`
- [ ] `AgentRunBroadcasterTest` passes (2 methods)
- [ ] `pending_approval` OpenAPI shape aligned with broadcaster emission (SC-006)
- [ ] `bin/check-openapi` passes
- [ ] PHPStan clean (baseline updated if needed for deleted class)
- [ ] CS-Fixer clean

## Risks

| Risk | Mitigation |
|---|---|
| `MessagingServiceProvider` removing adapter binding breaks another binding in same provider | Read full provider before editing; keep non-adapter bindings intact |
| PHPStan dead-code baseline has entries for `BroadcastStorageAdapter` | Grep baseline and remove matching entries |
| `pending_approval` shape has more drift than named in #1511 | Read broadcaster push payload carefully; update all diverging fields |

## Reviewer Guidance

1. Run `grep -rn "BroadcastStorageAdapter" packages/` — should return zero results after this WP.
2. Check `MessagingServiceProvider` still registers all other bindings it owned (only the adapter binding should be gone).
3. Verify `AgentRunBroadcasterTest` uses `CoversClass(AgentRunBroadcaster::class)` not the deleted adapter.
4. SC-005 check: `! [ -f packages/ai-agent/src/Broadcast/BroadcastStorageAdapter.php ] && echo PASS`.
