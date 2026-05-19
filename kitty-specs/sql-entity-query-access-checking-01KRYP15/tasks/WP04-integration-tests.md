---
work_package_id: WP04
title: Integration tests
dependencies:
- WP03
requirement_refs:
- NFR-002
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-sql-entity-query-access-checking-01KRYP15
base_commit: 22337d0fd7ded09e8ac80c88c88b35df0bef8a51
created_at: '2026-05-19T01:14:07.086065+00:00'
subtasks:
- T017
- T018
- T019
- T020
- T021
shell_pid: "474906"
agent: "claude:sonnet:implementer:implementer"
history:
- date: '2026-05-18T23:44:03Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: tests/Integration/PhaseN/EntityQueryAccessCheck/
execution_mode: code_change
owned_files:
- tests/Integration/PhaseN/EntityQueryAccessCheck/ListingFilterTest.php
- tests/Integration/PhaseN/EntityQueryAccessCheck/GraphQLResolverFilterTest.php
- tests/Integration/PhaseN/EntityQueryAccessCheck/BypassRespectsSystemContextTest.php
- tests/Integration/PhaseN/EntityQueryAccessCheck/AnonymousAccountFilterTest.php
- tests/Integration/PhaseN/EntityQueryAccessCheck/AdminBypassCapabilityTest.php
tags: []
---

# WP04 — Integration tests

## Objective

Lock the end-to-end contract: when a user listing flows through the
JSON:API controller, GraphQL resolver, or agent runtime's
`EntitySearchTool`, only access-allowed rows are returned. When a
system context explicitly opts out via `accessCheck(false)`, all rows
are returned. Anonymous accounts see only what anonymous can see.
Bypass-capability holders see the full set.

Unit tests in WP02 covered the query mechanism in isolation. WP04
proves the contract holds across three real consumer paths and three
account types.

## Context

- Spec FRs in scope: covered by WP01-WP03; this WP locks them via observed behaviour.
- Spec SCs in scope: **SC-001, SC-002, SC-003, SC-004, SC-005, SC-007**.
- Test infrastructure: `DBALDatabase::createSqlite()` + in-process kernel boot per existing `tests/Integration/PhaseN/AgentRuntime/` conventions.

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T017 — `ListingFilterTest` (JSON:API index)

**Purpose:** Prove that `GET /api/{entity-type}` returns only access-allowed rows.

**Steps:**

1. Create `tests/Integration/PhaseN/EntityQueryAccessCheck/ListingFilterTest.php`.
2. Boot the kernel in test mode with the in-memory SQLite database. Seed an entity type whose access policy allows only `entity.owner_id === account.id`.
3. Seed 6 rows: 2 owned by account A (id=10), 2 by account B (id=20), 2 by no specific owner (policy returns `forbidden` for these for everyone except admins).
4. Issue a `GET /api/{type}` request with account A as the authenticated user (via test helper that sets `_account` request attribute).
5. Assert the response body contains exactly 2 entity IDs — the ones owned by A.
6. Assert the `meta.total` (or wherever the JSON:API serializer reports cardinality) is `2`, not `6`.

**Files:**
- `tests/Integration/PhaseN/EntityQueryAccessCheck/ListingFilterTest.php` (NEW)

**Validation:**
- [ ] Test green.
- [ ] Response body assertion matches the expected 2 IDs (not 4, not 6).
- [ ] `meta.total` reflects filtered cardinality.

---

## Subtask T018 — `GraphQLResolverFilterTest`

**Purpose:** Prove that the GraphQL `nodes(filter: {...})` resolver and its companion count return filtered results.

**Steps:**

1. Create `tests/Integration/PhaseN/EntityQueryAccessCheck/GraphQLResolverFilterTest.php`.
2. Same seed pattern as T017 (6 rows, 2 per owner).
3. Execute a GraphQL query through the resolver pipeline with account A's context:
   ```graphql
   query { nodes(filter: {}) { id totalCount } }
   ```
4. Assert `nodes[].id` contains exactly 2 IDs (account A's owned rows).
5. Assert `totalCount` is `2`.
6. Add a second test method exercising the unfiltered count path: same query as system context with `accessCheck(false)` (mock the resolver context to bypass) — assert `totalCount` is `6`.

**Files:**
- `tests/Integration/PhaseN/EntityQueryAccessCheck/GraphQLResolverFilterTest.php` (NEW)

**Validation:**
- [ ] Both tests green.
- [ ] GraphQL count test catches the cardinality-leak bug that motivated the mission.

---

## Subtask T019 — `BypassRespectsSystemContextTest`

**Purpose:** Prove that `accessCheck(false)` returns all rows including those the bound (or unbound) account couldn't see.

**Steps:**

1. Create `tests/Integration/PhaseN/EntityQueryAccessCheck/BypassRespectsSystemContextTest.php`.
2. Seed 5 rows owned by various accounts.
3. Run the query with `accessCheck(false)`:
   ```php
   $ids = $storage->getQuery()
       ->accessCheck(false)
       ->execute();
   ```
4. Assert `count($ids) === 5`.
5. Add a companion test: `accessCheck(false)->count()->execute()` returns `5`.
6. Add a third test method: even without a bound account, `accessCheck(false)` succeeds (does not throw `MissingQueryAccountException`).

**Files:**
- `tests/Integration/PhaseN/EntityQueryAccessCheck/BypassRespectsSystemContextTest.php` (NEW)

**Validation:**
- [ ] All three test methods green.

---

## Subtask T020 — `AnonymousAccountFilterTest`

**Purpose:** Anonymous accounts see only what the policy explicitly allows.

**Steps:**

1. Create `tests/Integration/PhaseN/EntityQueryAccessCheck/AnonymousAccountFilterTest.php`.
2. Seed 4 rows: 2 with `status='published'` (publicly visible), 2 with `status='draft'` (only visible to owner).
3. Policy: anonymous can view published; can't view draft.
4. Bind `AnonymousUser` (id=0) to the query.
5. Assert `execute()` returns the 2 published IDs only.

**Files:**
- `tests/Integration/PhaseN/EntityQueryAccessCheck/AnonymousAccountFilterTest.php` (NEW)

**Validation:**
- [ ] Test green.
- [ ] No `MissingQueryAccountException` — `AnonymousUser` is a valid bound account.

---

## Subtask T021 — `AdminBypassCapabilityTest`

**Purpose:** A bypass-capability holder sees the full set via the same query path (not by toggling `accessCheck(false)`).

**Steps:**

1. Create `tests/Integration/PhaseN/EntityQueryAccessCheck/AdminBypassCapabilityTest.php`.
2. Seed an entity type whose access policy:
   - Returns `allowed` if `account.id === entity.owner_id`.
   - Returns `allowed` if `account.hasPermission('entity.bypass_ownership')`.
   - Otherwise `forbidden`.
3. Seed 6 rows with various owners.
4. Set up an admin account (id=99) holding `entity.bypass_ownership` capability.
5. Bind the admin account via `setAccount(...)`. **Do NOT** call `accessCheck(false)` — the admin sees everything because the policy says so, not because the check is disabled.
6. Assert `execute()` returns all 6 IDs.

**Files:**
- `tests/Integration/PhaseN/EntityQueryAccessCheck/AdminBypassCapabilityTest.php` (NEW)

**Validation:**
- [ ] Test green.
- [ ] The test demonstrates that capability-driven bypass is policy-level, not query-level.

---

## Definition of Done

- [ ] T017..T021 checkboxes flipped — 5 integration test files filed.
- [ ] `./vendor/bin/phpunit tests/Integration/PhaseN/EntityQueryAccessCheck/` runs all 5 tests green.
- [ ] All gates green: cs-check, phpstan, layers, dead-code, composer-policy.
- [ ] No new `getQuery()` consumers without explicit account binding or bypass — re-run the audit grep from WP03 as a final sanity check.

## Risks & mitigations

1. **Test fixtures drift from production policies.** *Mitigation:* tests use minimal anonymous-class policies; they're not exercising real production policies (those are unit-tested in the access subsystem's own suite). The tests assert the CONTRACT — given a policy that says X, the query returns Y.
2. **Test runtime exceeds CI budget.** *Mitigation:* 5 tests against in-memory SQLite total < 5 s on a developer machine. CI parallelisation handles the rest.
3. **GraphQL resolver test requires too much kernel surface.** *Mitigation:* mirror the pattern from existing `packages/graphql/tests/Integration/` setups; the framework already supports in-process GraphQL execution.

## Reviewer guidance

- Verify each test seeds a deterministic fixture and asserts a deterministic outcome — no flakiness, no time-dependent randomness.
- Verify the JSON:API test asserts on the `meta.total` count, not just the row count of the response array.
- Verify the GraphQL test exercises BOTH the main query and the count query, since both were `accessCheck(false)` before this mission.
- Verify the bypass test does NOT bind an account on one of its sub-cases — that's the key fact: bypass succeeds even without an account.

## Implementation command

```
spec-kitty agent action implement WP04 --agent <name>
```

## Activity Log

- 2026-05-19T01:14:08Z – claude:sonnet:implementer:implementer – shell_pid=474906 – Assigned agent via action command
