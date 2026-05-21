---
work_package_id: WP01
title: TranslationController access gate
dependencies:
- WP03
requirement_refs:
- C-004
- FR-001
- FR-002
- FR-003
- FR-004
- FR-010
- FR-013
- NFR-001
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T008
- T009
- T010
- T011
- T012
agent: "claude:sonnet:implementer:implementer"
shell_pid: "755033"
history:
- date: '2026-05-20T23:57:09Z'
  author: tasks-materializer
  note: Initial WP file generated
authoritative_surface: packages/api/src/Controller/
execution_mode: code_change
mission_slug: m006-translation-hardening-01KS3RY9
owned_files:
- packages/api/src/Controller/TranslationController.php
- packages/api/tests/Unit/TranslationControllerTest.php
- tests/Integration/Phase29/TranslationAccessControlTest.php
tags: []
---

# WP01 — TranslationController access gate

**Mission**: m006-translation-hardening-01KS3RY9
**Closes**: #1445 (HIGH)
**Priority**: HIGH — must land before any consumer flips `translatable: true`

## CRITICAL External Dependency

**M-B (`access-fail-closed-completeness-01KS3RJT`) WP02** replaces the `AccessPolicyRegistry`
with a container-resolved variant. `EntityAccessHandler::check()` calls into this registry.
If M-B and M-C run in parallel:

- WP01 must **not merge before M-B WP02 merges**.
- Safe: implement WP01 on its lane, rebase its PR on M-B's merge commit before merging.
- If M-B is not in flight: no sequencing constraint; implement normally.

## Objective

Wire `EntityAccessHandler` into `TranslationController` so that every read (`index`, `show`)
and every mutation (`store`, `update`, `destroy`) is gated by the entity's access policy before
any data is read or written. Implement the 403 JSON:API response shape (FR-003, NFR-003).
Cover each endpoint with a unit test (FR-010) and prove end-to-end 403 behaviour with an
integration test that boots the kernel with a deny-update policy (FR-013).

## Context

- **Controller file**: `packages/api/src/Controller/TranslationController.php` (308 lines)
- **Current state**: constructor takes only `EntityTypeManagerInterface` + `ResourceSerializer`. No `EntityAccessHandler` injection. No access check anywhere.
- **`EntityAccessHandler` location**: `packages/access/src/EntityAccessHandler.php`
  - Constructor: `__construct(array $policies = [])`
  - Key method: `check(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult`
  - Result: `AccessResult::isAllowed()` → bool (entity-level: deny unless granted per CLAUDE.md asymmetric semantics)
- **CLAUDE.md gotcha**: Account is at `$request->attributes->get('_account')`, NOT `account`. Reading `account` returns `null`.
- **`JsonApiError`**: already used in the controller. `JsonApiError::notFound()` exists. Need to add a `forbidden()` factory or construct directly.
- **`AnonymousUser`**: uses `id: 0`. The same access pipeline applies for anonymous — `EntityAccessHandler::check` handles it. Do NOT special-case anonymous.
- **Layer discipline**: L4 API (`TranslationController`) importing L1 Access (`EntityAccessHandler`) is a valid downward edge (C-004).
- **NFR-001**: access-check call adds ≤2% p95 overhead. The single `EntityAccessHandler::check()` call is pure in-memory PHP — no I/O — so this is trivially satisfied. No benchmark is required unless CI adds latency measurement.
- **NFR-003**: Error responses must conform to JSON:API error-object shape (`code`, `title`, `status`, `meta`).
- **Per-method ability mapping (FR-002)**:
  - `index` → `view`
  - `show` → `view`
  - `store` → `create`
  - `update` → `update`
  - `destroy` → `delete`

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- Implement from the workspace `spec-kitty agent action implement WP01 --agent <name>` allocates.
- **Rebase on M-B's merge commit** before merging this WP if M-B is in flight.

## Implementation Command

```bash
spec-kitty agent action implement WP01 --agent claude:sonnet
```

---

## Subtask T008 — Add `EntityAccessHandler` constructor parameter

**Purpose**: Inject `EntityAccessHandler` into `TranslationController` so access checks
are available in every method.

**File**: `packages/api/src/Controller/TranslationController.php`

**Steps**:

1. Add to the `use` imports (alphabetically appropriate):
```php
use Waaseyaa\Access\EntityAccessHandler;
use Symfony\Component\HttpFoundation\Request;
```
Check whether `Request` is already imported — it may not be if all methods currently receive
scalar arguments directly. If the controller methods need access to the `Request` object to
read `_account`, you may need to add a `Request $request` parameter to each public method,
or extract the account in a different way (see T009 for the pattern decision).

**Recommended approach for account extraction**: Add a private helper
`private function accountFromRequest(Request $request): AccountInterface` that reads
`$request->attributes->get('_account')`. This keeps the per-method code clean.

2. Modify the constructor:
```php
public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityAccessHandler $accessHandler,
    private readonly ResourceSerializer $serializer,
) {}
```

Place `$accessHandler` between `$entityTypeManager` and `$serializer` (matches plan.md).

3. Check if any DI/service container wires `TranslationController` — if a service provider
   or route builder constructs it manually, update that call site to pass the new parameter.
   Search: `grep -rn "TranslationController" packages/ --include="*.php"`.

**Validation**:
- [ ] `composer phpstan` passes on the modified file.
- [ ] No "too few arguments" errors from call sites.

---

## Subtask T009 — Per-method `check()` calls with FR-002 ability mapping

**Purpose**: Insert the access check at the start of each public method, after
`loadTranslatableEntity()` returns the entity but before any data mutation or serialization.

**File**: `packages/api/src/Controller/TranslationController.php`

**Steps**:

1. Decide how public methods receive `Request`. Currently, `store` and `update` take
   `array $data` but no `Request`. Options:
   - **Option A (recommended)**: Add `Request $request` parameter to all 5 public methods.
     This is consistent with the framework's controller pattern and cleanest for DI.
   - **Option B**: Pass the account as a parameter in method signatures from the router.
     Less desirable — leaks auth concerns into the routing layer.

   Choose **Option A** unless a framework convention exists that differs. Verify by looking
   at `JsonApiController`'s method signatures in `packages/api/src/JsonApiController.php`.

2. Add a private helper:
```php
/**
 * Perform an access check for the given entity and operation.
 *
 * @param Request $request   The current HTTP request (reads `_account` attribute).
 * @param TranslatableInterface $entity  The entity to check.
 * @param string $operation  Ability name: view, create, update, delete.
 * @return JsonApiDocument|null  Returns a 403 document if access is denied, null if allowed.
 */
private function checkAccess(
    Request $request,
    TranslatableInterface $entity,
    string $operation,
): ?JsonApiDocument {
    /** @var \Waaseyaa\Access\AccountInterface|null $account */
    $account = $request->attributes->get('_account');

    // If no account on request, treat as anonymous (AnonymousUser).
    // The access pipeline handles the anonymous case — do not special-case here.
    if ($account === null) {
        // AnonymousUser is injected by SessionMiddleware; if absent, deny.
        return $this->forbiddenDocument();
    }

    $result = $this->accessHandler->check($entity, $operation, $account);

    if (!$result->isAllowed()) {
        return $this->forbiddenDocument();
    }

    return null;
}
```

3. In each public method, after `$this->loadTranslatableEntity()` returns `$entity`,
   add the access check call. For methods that act on a specific langcode, pass the
   parent entity (not the translation) — per-langcode access is out of scope (spec §Out of scope).

   **`index` method** (ability: `view`):
   ```php
   $entity = $this->loadTranslatableEntity($entityTypeId, $id);
   // Add after loadTranslatableEntity:
   if ($denied = $this->checkAccess($request, $entity, 'view')) {
       return $denied;
   }
   ```

   **`show` method** (ability: `view`): same pattern.

   **`store` method** (ability: `create`):
   ```php
   if ($denied = $this->checkAccess($request, $entity, 'create')) {
       return $denied;
   }
   ```

   **`update` method** (ability: `update`):
   ```php
   if ($denied = $this->checkAccess($request, $entity, 'update')) {
       return $denied;
   }
   ```

   **`destroy` method** (ability: `delete`):
   ```php
   if ($denied = $this->checkAccess($request, $entity, 'delete')) {
       return $denied;
   }
   ```

4. In all cases, the check comes **before** any mutation or data read beyond entity loading.
   For `index` and `show`: check before iterating translations or serializing.
   For `store`, `update`, `destroy`: check after loading but before writing.

**Important**: The `loadTranslatableEntity()` method currently uses try/catch and returns
`TranslatableInterface`. The entity access check uses this entity object. The check must
run on the **parent entity** even for translation sub-operations (FR-002 note in spec).

**Validation**:
- [ ] `composer phpstan` passes on the full file.
- [ ] `composer cs-check` passes.

---

## Subtask T010 — 403 response shape

**Purpose**: Implement the `forbiddenDocument()` private method to return a consistent
JSON:API error document per FR-003 and NFR-003. The response must not leak whether the
entity exists.

**File**: `packages/api/src/Controller/TranslationController.php`

**Steps**:

1. Add a `forbiddenDocument()` private method:
```php
/**
 * Returns a 403 Forbidden JSON:API error document.
 *
 * Does not leak entity existence — the same shape is returned whether the entity
 * exists or the account lacks the required ability.
 */
private function forbiddenDocument(): JsonApiDocument
{
    return $this->errorDocument(
        new JsonApiError(
            status: '403',
            title: 'Forbidden',
            code: 'FORBIDDEN',
        ),
    );
}
```

2. Verify the `JsonApiError` constructor in `packages/api/src/JsonApiError.php` accepts
   a `code` parameter. If it does not, add it or use whatever field the existing
   JSON:API error shape uses for machine-readable error codes.

3. The `statusCode` on `JsonApiDocument::fromErrors()` must be set to `403`:
   - Check whether `errorDocument()` already propagates `(int) $error->status` as the
     status code. Looking at line 306 of `TranslationController.php`:
     ```php
     return JsonApiDocument::fromErrors([$error], statusCode: (int) $error->status);
     ```
     This already works — `(int) '403'` = `403`. No change needed there.

4. Confirm the 403 response JSON:API shape:
```json
{
  "errors": [
    {
      "status": "403",
      "title": "Forbidden",
      "code": "FORBIDDEN"
    }
  ]
}
```
This is the stable error shape per NFR-003. The `title` must be stable across calls
(not user-derived). The `code` must be `FORBIDDEN`.

**Validation**:
- [ ] `composer phpstan` passes.
- [ ] Confirm `JsonApiError` supports a `code` field — if not, add it and update usages.

---

## Subtask T011 — Unit test `TranslationControllerTest`

**Purpose**: Cover the happy path (access allowed → operation proceeds) and the 403 path
(access denied → 403 returned without mutation) for each of the 5 endpoints (FR-010).

**File**: `packages/api/tests/Unit/TranslationControllerTest.php` *(new file or edit existing)*

**Namespace**: `Waaseyaa\Api\Tests\Unit`

**Steps**:

1. Look for an existing `TranslationControllerTest.php`. If it exists, add the access-gate
   tests to it. If not, create it.

2. Test setup pattern (adapt from `JsonApiControllerCrudTest.php`):

```php
protected function setUp(): void
{
    // Use a real EntityTypeManager + InMemoryEntityStorage + ResourceSerializer.
    // Use a stub EntityAccessHandler that returns allowed or forbidden.
    // Construct TranslationController with these.
}
```

3. For `EntityAccessHandler`, you cannot `createMock()` a `final class` in PHPUnit.
   Use a real `EntityAccessHandler` instance but feed it with anonymous class policies:

```php
private function makeAccessHandler(bool $allow): EntityAccessHandler
{
    $handler = new EntityAccessHandler();
    $handler->addPolicy(new class($allow) implements AccessPolicyInterface {
        public function __construct(private bool $allow) {}
        public function access(
            EntityInterface $entity,
            string $operation,
            AccountInterface $account,
        ): AccessResult {
            return $this->allow ? AccessResult::allowed() : AccessResult::forbidden();
        }
    });
    return $handler;
}
```

4. For each of the 5 public methods, add two tests:
   - `{method}AllowedProceedsNormally` — access allowed → HTTP 200 (or 201/204 for create/delete).
   - `{method}ForbiddenReturns403` — access forbidden → `statusCode` is 403, `code` is `FORBIDDEN`.

   Example for `show`:
   ```php
   #[Test]
   public function showAllowedReturnsSingleTranslation(): void
   {
       // Setup: create entity with 'fr' translation.
       // Call: $controller->show($request, 'article', 1, 'fr').
       // Assert: statusCode 200, data contains 'fr' translation.
   }

   #[Test]
   public function showForbiddenReturns403(): void
   {
       // Setup: EntityAccessHandler returns forbidden.
       // Call: $controller->show($request, 'article', 1, 'fr').
       // Assert: statusCode 403, errors[0].code === 'FORBIDDEN'.
   }
   ```

5. The `Request` object passed to controller methods needs `_account` set:
   ```php
   $request = new Request();
   $request->attributes->set('_account', $this->makeAccount());
   ```
   For the forbidden path, either use an account that the policy denies, or construct
   an `EntityAccessHandler` that always returns forbidden regardless of account.

6. For the anonymous account test (SC-002), pass a `Request` with no `_account` attribute
   set and assert that the controller returns 403.

**Coverage target**: 10 test methods (2 per endpoint × 5 endpoints) + 1 anonymous test.

**Validation**:
- [ ] `./vendor/bin/phpunit packages/api/tests/Unit/TranslationControllerTest.php` passes (all green).
- [ ] `composer phpstan` clean on test file.
- [ ] `#[CoversClass(TranslationController::class)]` attribute on the test class.

---

## Subtask T012 — Integration test `TranslationAccessControlTest`

**Purpose**: Prove end-to-end that the access gate works in a real kernel context:
kernel boots, translatable entity type is registered with a deny-update policy,
PATCH as a deny-only user returns 403 and leaves the entity unmodified (SC-001).
Also covers SC-002 (anonymous view denied → 403).

**File**: `tests/Integration/Phase29/TranslationAccessControlTest.php` *(new file)*

**Namespace**: `Waaseyaa\Tests\Integration\Phase29`

**Steps**:

1. Follow the existing Phase29 integration test pattern
   (see `TwoAxisAccessPolicyIntegrationTest.php` in the same directory).

2. Use `DBALDatabase::createSqlite(':memory:')` for the storage backend.

3. Test setup:
   - Register a `TranslatableEntity` entity type (create a minimal concrete subclass of
     `ContentEntityBase` in the test fixture, or reuse an existing fixture from
     `packages/api/tests/` or `packages/entity/tests/`).
   - Register an `AccessPolicyInterface` that:
     - Returns `AccessResult::allowed()` for `view` to all accounts.
     - Returns `AccessResult::forbidden()` for `update` to the `viewer-only` test account.
     - Returns `AccessResult::allowed()` for `update` to the `editor` test account.
   - Create a test entity with ID=1, add a `fr` translation.

4. Test `patchDeniedReturnsForbidden`:
   - Build a PATCH request for `PATCH /api/article/1/translations/fr`.
   - Set `_account` to the `viewer-only` account on the request attributes.
   - Call `$controller->update($request, 'article', 1, 'fr', ['data' => ['attributes' => ['title' => 'Hacked']]])`.
   - Assert response `statusCode` is `403`.
   - Reload the entity from storage and assert `title` is NOT `'Hacked'`.

5. Test `indexDeniedForAnonymousReturnsForbidden` (SC-002):
   - Build a GET request with no `_account` set (anonymous).
   - Set up the entity policy to deny `view` for anonymous (return `forbidden()` when
     `$account instanceof AnonymousUser`).
   - Call `$controller->index($request, 'article', 1)`.
   - Assert response `statusCode` is `403`.

6. Test `patchAllowedProceedsNormally`:
   - Same setup, but use the `editor` account.
   - Assert response `statusCode` is `200`.
   - Assert the entity's `fr` translation has the new title.

**Note on account constructors**: `AnonymousUser` uses `id: 0`. Create test accounts as
simple `AccountInterface` implementations (anonymous classes are fine):
```php
private function makeAccount(int $id, array $roles = []): AccountInterface
{
    return new class($id, $roles) implements AccountInterface {
        public function __construct(private int $id, private array $roles) {}
        public function id(): int { return $this->id; }
        public function getRoles(): array { return $this->roles; }
        // Add remaining AccountInterface methods as needed.
    };
}
```

**Validation**:
- [ ] `./vendor/bin/phpunit tests/Integration/Phase29/TranslationAccessControlTest.php` passes (all green).
- [ ] `#[CoversNothing]` on the integration test class.
- [ ] Entity unmodified assertion passes for the 403 path.
- [ ] Anonymous denial test passes.

---

## Definition of Done

- [ ] `TranslationController` constructor accepts `EntityAccessHandler` as second parameter.
- [ ] All 5 public methods call `checkAccess()` after `loadTranslatableEntity()` using FR-002 mapping.
- [ ] 403 response has `status: "403"`, `code: "FORBIDDEN"`, stable `title: "Forbidden"`.
- [ ] `_account` attribute key used everywhere (not `account`).
- [ ] `TranslationControllerTest` passes with all 11+ test methods green.
- [ ] `TranslationAccessControlTest` passes in Phase29 with entity-unmodified assertion.
- [ ] `composer verify` sub-checks pass: cs-check, phpstan, phpunit.
- [ ] PR rebased on M-B WP02 merge commit if M-B ran in parallel.
- [ ] Commit message includes `Closes #1445`.

## Risks

| Risk | Mitigation |
|------|------------|
| `_account` attribute absent (null) from request | `checkAccess()` must handle null account — return 403, do not call `EntityAccessHandler::check()` with null |
| `EntityAccessHandler` constructor signature changes in M-B WP02 | Rebase on M-B's commit before merging; review the M-B diff to adjust construction in tests if needed |
| `JsonApiError` missing `code` field | Check `JsonApiError.php` constructor before T010; add if missing |
| Integration test fixture entity class not in autoload | Create fixtures under `tests/` (already in `autoload-dev`), not `src/` |
| `createMock()` on `EntityAccessHandler` fails (final) | Use real `EntityAccessHandler` + anonymous class `AccessPolicyInterface` (T011 pattern) |

## Reviewer Guidance

- Confirm `checkAccess()` is called in **all 5** public methods — not just the 3 mutations.
- Confirm ability strings match exactly: `'view'`, `'create'`, `'update'`, `'delete'` (lowercase).
- Confirm `_account` (with underscore prefix) is used, not `account`.
- Confirm the 403 response does not reveal entity existence (same response whether entity is not found vs. access denied on a found entity — but this is tricky since `loadTranslatableEntity` already returns 404 for missing entities; the 403 path only fires on found entities, which is acceptable per FR-003's intent: "response does not leak whether the entity exists" means the 403 body gives no entity-state information, not that 403 and 404 look identical).
- Confirm integration test asserts entity is **unmodified** after a denied PATCH.

## Activity Log

- 2026-05-21T00:50:13Z – claude:sonnet:implementer:implementer – shell_pid=755033 – Started implementation via action command
- 2026-05-21T00:56:36Z – claude:sonnet:implementer:implementer – shell_pid=755033 – EntityAccessHandler injected; per-method check() calls (view/create/update/delete); 403 JSON:API error shape with FORBIDDEN code; anti-enumeration confirmed; 22 unit tests + 3 integration tests pass; phpstan clean; stale baseline entries removed
