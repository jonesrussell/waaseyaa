---
work_package_id: WP02
title: SqlEntityQuery filter, count, cursor + unit tests
dependencies:
- WP01
requirement_refs:
- FR-001
- FR-002
- FR-004
- FR-005
- FR-006
- FR-007
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T004
- T005
- T006
- T007
- T008
- T009
history:
- date: '2026-05-18T23:44:03Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/entity-storage/src/SqlEntityQuery.php
execution_mode: code_change
owned_files:
- packages/entity-storage/src/SqlEntityQuery.php
- packages/entity-storage/tests/Unit/SqlEntityQueryAccessCheckTest.php
tags: []
---

# WP02 — `SqlEntityQuery` filter, count, cursor + unit tests

## Objective

This is the core of the mission. Replace the no-op `accessCheck()`
stub with a real per-row filter against `EntityAccessHandler::check('view', ...)`.
Ship all the supporting state, the DI integration, the security-critical
throw on missing account, and the cardinality / cursor semantics needed
for paginated callers. Cover the matrix with unit tests against an
in-memory SQLite database.

## Context

- Spec FRs in scope: **FR-001, FR-002, FR-004, FR-005, FR-006, FR-007, FR-008, FR-009**.
- NFRs in scope: **NFR-001** (O(1) extra DB queries), **NFR-002** (≤ 100 ms per 25-row page), **NFR-003** (no regressions), **NFR-005** (dead-code clean), **NFR-006** (PHPStan), **NFR-007** (cs-check).
- Constraints applied: **C-002** (no field-level access at query layer), **C-003** (reuse existing AccessChecker pipeline), **C-004** (accessCheck(false) preserved), **C-006** (fail closed on missing account).
- Data-model: [data-model.md](../data-model.md) §"SqlEntityQuery — new internal state".
- Research grounding: R-001 (per-row check is right surface; no `checkMultiple`), R-003 (hydrate-then-filter, keep `: array` return type).
- Contract: [contracts/entity-query-interface-additions.md](../contracts/entity-query-interface-additions.md) — semantics every implementation MUST honour.

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`. `composer install` first.

---

## Subtask T004 — Internal state + `setAccount()` + real `accessCheck(bool)`

**Purpose:** Replace the no-op behaviour. Bind the account, store the flag.

**Steps:**

1. Open `packages/entity-storage/src/SqlEntityQuery.php`. Locate the existing `accessCheck()` method (currently at line 129 with the stub comment).
2. Add two new private properties to the class:
   ```php
   private ?\Waaseyaa\Access\AccountInterface $account = null;
   private bool $accessCheckEnabled = true;
   ```
3. Add a new method (place near `accessCheck()`):
   ```php
   public function setAccount(?\Waaseyaa\Access\AccountInterface $account): static
   {
       $this->account = $account;
       return $this;
   }
   ```
4. Rewrite the existing `accessCheck()` body:
   ```php
   public function accessCheck(bool $check = true): static
   {
       $this->accessCheckEnabled = $check;
       return $this;
   }
   ```
   - Delete the `// No-op in v0.1.0 — access checking is not implemented yet.` comment.
   - Keep the method signature exactly as the interface declares (`bool $check = true`).
5. Add class-level `@api` PHPDoc if not present (it's an extension point; dead-code gate respects it).

**Files:**
- `packages/entity-storage/src/SqlEntityQuery.php` (EDIT — state + two methods)

**Validation:**
- [ ] PHPStan passes — `?AccountInterface` is a known type.
- [ ] cs-check passes.

---

## Subtask T005 — Lazy DI resolve of `EntityAccessHandler`

**Purpose:** Wire `EntityAccessHandler` into the query without changing the constructor signature.

**Steps:**

1. The query constructor currently takes `EntityTypeInterface`, `DatabaseInterface`, `?SqlEntityQueryResultCache`, `?FieldDefinitionRegistryInterface`. **Do not change it** — `SqlEntityStorage::getQuery()` (the single factory site at `SqlEntityStorage.php:1159`) constructs the query and we don't want to thread DI deeper than needed.
2. Add a private nullable property `?\Waaseyaa\Access\EntityAccessHandler $accessHandler = null` to the class.
3. Add a private method `resolveAccessHandler()` that lazy-resolves the handler from the kernel container at first call. Use the existing service-container access pattern in this package — grep for `resolve(` or `$this->container` to find the local idiom. If `entity-storage` uses a constructor-injected container, plumb the handler in at construction via that container. If it uses a static service-locator helper, use that. **Match the existing local pattern; do not introduce a new one.**
4. Cache the resolved handler so subsequent `execute()` / `count()` calls reuse it.

**Files:**
- `packages/entity-storage/src/SqlEntityQuery.php` (EDIT)

**Validation:**
- [ ] PHPStan passes — the resolution path is correctly typed.
- [ ] Layer check passes — `packages/entity-storage` (L1) consumes `packages/access` (L1); same-layer is fine.
- [ ] No new constructor parameter — the existing single call site at `SqlEntityStorage::1159` is unchanged.

---

## Subtask T006 — `execute()` rewrite

**Purpose:** The core change. Hydrate the candidate page, run access check per row, drop forbidden, return surviving IDs. Throw on missing account when check is enabled.

**Steps:**

1. Open `SqlEntityQuery::execute()` at line 474.
2. At the start of the method, before any DB work, add:
   ```php
   if ($this->accessCheckEnabled && $this->account === null) {
       throw \Waaseyaa\EntityStorage\Exception\MissingQueryAccountException::forQuery($this->entityType);
   }
   ```
3. Run the existing SQL execution to obtain the candidate page (whatever `execute()` does today to materialize IDs). **Keep that machinery.**
4. After the candidate IDs are known and BEFORE returning:
   - If `$this->accessCheckEnabled === false`: return the candidate IDs as today. No filter, no hydration overhead.
   - Else: hydrate the candidate rows into entities. Use the existing hydration helper that `mapRowToEntity()` provides (the comment at line 1175+ in `SqlEntityStorage` indicates a private `mapRowToEntity()` exists in the storage; if the query needs to hydrate, it'll need a path back to the storage's hydration. The cleanest path is: the storage passes a hydrator callback into the query at construction OR the query calls `$storage->loadMultiple($candidateIds)`. Pick the one that matches existing patterns — grep `loadMultiple` in `SqlEntityStorage.php` first.)
5. Loop the hydrated entities:
   ```php
   $survivors = [];
   foreach ($entities as $entity) {
       $result = $this->resolveAccessHandler()->check($entity, 'view', $this->account);
       if (!$result->isForbidden()) {
           $survivors[] = $entity->id();
       }
   }
   return $survivors;
   ```
6. Verify the method's PHP type annotations and return-type still match `EntityQueryInterface::execute(): array`. (Element type is still implicitly array-of-IDs; the contract is `array`.)

**Files:**
- `packages/entity-storage/src/SqlEntityQuery.php` (EDIT — `execute()`)

**Validation:**
- [ ] `accessCheckEnabled=true` + no account → throws `MissingQueryAccountException`.
- [ ] `accessCheckEnabled=true` + account bound → returns access-filtered IDs.
- [ ] `accessCheckEnabled=false` → returns all candidate IDs without hydration (no perf regression on bypass path).
- [ ] Removing the `accessCheck()` stub comment is part of the same diff.

---

## Subtask T007 — `count()` post-filter cardinality

**Purpose:** Pagers must see the filtered total when `accessCheck=true`.

**Steps:**

1. Locate the existing `count()` method (line ~122). Today it just sets `$this->isCount = true` and returns `$this`.
2. The actual cardinality is materialized on `execute()`. The simplest implementation that satisfies FR-006:
   - When `accessCheckEnabled=true` AND `isCount=true`: in `execute()`, run the same hydrate-and-filter pass on the candidate window, return `[count($survivors)]` (or however the existing count return shape works — grep callers of `->count()->execute()` to confirm).
   - When `accessCheckEnabled=false` AND `isCount=true`: existing behaviour (raw `COUNT(*)`).
3. **Do not** introduce a separate query path. Reuse the candidate-IDs+filter machinery from T006.

**Files:**
- `packages/entity-storage/src/SqlEntityQuery.php` (EDIT — `count()` + relevant `execute()` branch)

**Validation:**
- [ ] Filtered count matches the post-filter cardinality of a paginated execute against the same query.
- [ ] Bypass count matches pre-filter cardinality (current behaviour).

---

## Subtask T008 — `range()` unfiltered-window cursor

**Purpose:** Paginated callers must not re-scan candidates already evaluated.

**Steps:**

1. The existing `range(int $offset, int $limit)` sets `$this->offset` and `$this->limit` for the SQL query.
2. The page returned by `execute()` is the **survivors** from the candidate window `[offset, offset+limit)`. Callers requesting the next page pass `offset = previous_offset + limit` (NOT `offset + count(survivors)`).
3. This is already the behaviour of the existing `range()` — the candidate window is `LIMIT $limit OFFSET $offset`. The filter reduces the result size, not the window position.
4. **What this subtask actually delivers:** a code comment + a test pinning the contract. The implementation may already be correct; the test asserts it.
5. Add a comment near `range()` documenting the contract: "Cursor advances by the unfiltered candidate window. Callers paginate by adding `$limit` to `$offset`, not by adding `count(execute())`."
6. The integration test in WP04 will demonstrate end-to-end traversal.

**Files:**
- `packages/entity-storage/src/SqlEntityQuery.php` (EDIT — docstring comment on `range()`)

**Validation:**
- [ ] Unit test (T009) asserts that paginated traversal of a 100-row table with `range(0, 25)`, `range(25, 25)`, `range(50, 25)`, `range(75, 25)` returns disjoint survivor sets whose union equals the full filtered population.

---

## Subtask T009 — Unit tests covering the matrix

**Purpose:** Lock the contract.

**Steps:**

1. Create `packages/entity-storage/tests/Unit/SqlEntityQueryAccessCheckTest.php`.
2. Test harness: in-memory SQLite via `DBALDatabase::createSqlite()`. Seed an entity type whose access policy returns:
   - `allowed` when `entity.owner_id === account.id`
   - `forbidden` otherwise
   - For tests that need anonymous behavior, use `AnonymousUser`-shape account.
3. Test cases (each as `#[Test]` method):
   - **`executeReturnsOnlyOwnedRows`** — 5 rows, two owned by account A, three by account B; A's account bound; `execute()` returns 2 IDs.
   - **`executeAllowsBypassWithAccessCheckFalse`** — same seed; `accessCheck(false)->execute()` returns all 5 IDs.
   - **`executeThrowsWhenCheckEnabledAndAccountMissing`** — no account bound; `accessCheckEnabled` default true; expect `MissingQueryAccountException`.
   - **`executeMixedAllowDenyDropsForbidden`** — 10 rows interleaved owner; assert exact survivor IDs.
   - **`countReflectsPostFilterCardinality`** — same seed; `->count()->execute()` returns `2`.
   - **`countAccessCheckFalseReflectsPreFilterCardinality`** — `accessCheck(false)->count()->execute()` returns `5`.
   - **`rangeCursorAdvancesByUnfilteredWindow`** — 100 rows, half owned; `range(0, 25)`, `range(25, 25)`, `range(50, 25)`, `range(75, 25)`; union of all survivor IDs equals the full owned set; intersections are empty.
   - **`anonymousAccountSeesEmptyWhenPolicyForbidsAll`** — Anonymous account; policy always returns `forbidden`; `execute()` returns `[]`.
   - **`per25RowPageLatencyUnder100ms`** — seed 25 rows; assert `microtime(true)`-based wall-clock of one `execute()` < 0.1 seconds. Use `$this->markTestSkipped()` only if the harness is consistently above threshold on CI noise.
4. PHPUnit 10.5 attributes: `#[CoversClass(SqlEntityQuery::class)]` at class level, `#[Test]` per method.
5. Use anonymous-class implementations of `AccessPolicyInterface` for the test policy (constitution gotcha: `createMock()` may fail on intersection types). Bind the policy via `EntityAccessHandler::addPolicy()`.

**Files:**
- `packages/entity-storage/tests/Unit/SqlEntityQueryAccessCheckTest.php` (NEW)

**Validation:**
- [ ] `./vendor/bin/phpunit packages/entity-storage/tests/Unit/SqlEntityQueryAccessCheckTest.php` green.
- [ ] All 9 test methods named in step 3 present and passing.

---

## Definition of Done

- [ ] T004..T009 checkboxes flipped.
- [ ] `SqlEntityQuery::accessCheck(true)` is the default; calling `execute()` without a bound account throws.
- [ ] `SqlEntityQuery::accessCheck(false)` preserves the bypass path and runs no hydration / no per-row check.
- [ ] `count()` returns post-filter cardinality when check enabled; pre-filter when disabled.
- [ ] `range()` cursor advances by unfiltered window.
- [ ] No-op stub comment is gone.
- [ ] Unit test matrix (9 cases) green.
- [ ] All gates green: cs-check, phpstan, layers, dead-code, composer-policy.

## Risks & mitigations

1. **Hydration before access check changes performance shape.** *Mitigation:* hydration was happening anyway one layer up (callers do `loadMultiple($ids)`); the unit test asserts the < 100 ms threshold.
2. **`SqlEntityStorage::loadMultiple` recursion** if the query's filter path calls back into a `loadMultiple` that itself uses a query. *Mitigation:* `loadMultiple` should not run an access check internally (it doesn't today; see R-002). Verify by reading `SqlEntityStorage::loadMultiple()` before invoking.
3. **PHPStan strict-mode complaints** about nullable property access. *Mitigation:* the `accessCheckEnabled && $account === null` guard at the top of `execute()` provides the null-check that PHPStan needs to narrow `?AccountInterface` to `AccountInterface` for the body.

## Reviewer guidance

- Look at `execute()` first. Is the throw at the top? Is the bypass branch genuinely a fast path (no hydration)? Is the per-row check using `EntityAccessHandler::check()`, not `AccessChecker::check()` (R-001)?
- Check that the no-op stub comment is GONE. If it remains, the diff is incomplete.
- Verify `setAccount(null)` clears the binding (chainable + nullable).
- Confirm `count()` reuses the filter machinery, not a parallel SQL count branch (avoiding code duplication).
- Spot-check the unit-test policy is an anonymous class, not a `createMock`.

## Implementation command

```
spec-kitty agent action implement WP02 --agent <name>
```
