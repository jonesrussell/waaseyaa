---
work_package_id: WP01
title: SearchRouter account threading
dependencies: []
requirement_refs:
- FR-001
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-access-fail-closed-completeness-01KS3RJT
base_commit: 32ebd5f145ed7035f8603e6c4d25c244ee690154
created_at: '2026-05-20T23:45:54.572742+00:00'
subtasks:
- T001
- T002
- T003
- T004
shell_pid: '672099'
history:
- date: '2026-05-20T23:30:18Z'
  agent: claude:sonnet:tasks:tasks
  action: created
authoritative_surface: packages/foundation/src/Http/Router/
execution_mode: code_change
owned_files:
- packages/foundation/src/Http/Router/SearchRouter.php
- tests/Integration/Phase24/SemanticSearchAccessTest.php
tags: []
---

# WP01 — SearchRouter account threading

**Mission**: `access-fail-closed-completeness-01KS3RJT`
**Closes**: #1516
**Requirements**: FR-001, FR-010

## Objective

Thread the request's authenticated account (`_account`) from `SearchRouter::handle()` into `SearchController` so that semantic search respects per-row access control. Currently `SearchRouter` constructs `SearchController` without `account:` or `accessHandler:`, meaning all entity queries inside `SearchController` run without an account bound, leaking access-restricted rows to any caller.

## Context

### Current state (verified 2026-05-20)

**`SearchRouter`** (`packages/foundation/src/Http/Router/SearchRouter.php`):
- Constructor: `(array $config, DatabaseInterface $database, ?EntityTypeManagerInterface $entityTypeManager = null)`
- No `EntityAccessHandler` dependency; no account threading.
- `handle()` constructs `new SearchController(entityTypeManager: ..., serializer: ..., embeddingStorage: ..., embeddingProvider: ...)` — no `account:` or `accessHandler:`.

**`SearchController`** (`packages/ai-vector/src/SearchController.php`):
- Already accepts `?EntityAccessHandler $accessHandler = null` and `?AccountInterface $account = null` in its constructor (L103–108).
- When both are non-null, `$this->accessHandler->check($entity, 'view', $this->account)->isAllowed()` filters the entity result set.
- `keywordSearchIds()` already correctly calls `setAccount($this->account)` or `accessCheck(false)` depending on null-ness of `$this->account`.
- **The infrastructure is ready; only `SearchRouter` needs to thread the values in.**

### Architecture note

`SearchRouter` lives in `packages/foundation/` (L0). `EntityAccessHandler` lives in `packages/access/` (L1). The existing import `use Waaseyaa\Access\EntityAccessHandler` already appears in `packages/foundation/src/Kernel/Bootstrap/AccessPolicyRegistry.php`, confirming L0→L1 is permitted in Foundation. Verify the import does not violate any `bin/check-package-layers` rule before committing.

### Request attribute key

Per CLAUDE.md §HTTP, auth, request lifecycle: the authenticated account is stored under `_account` (with underscore). Using `account` (no underscore) returns null. **Do not change the key name.**

## Branch Strategy

- Planning base: `main`
- Merge target: `main`
- Execution worktree branch: assigned by `lanes.json` at finalize-tasks time.
- Implement command (no dependencies): `spec-kitty agent action implement WP01 --agent <name>`

---

## Subtask T001 — Add `?EntityAccessHandler $accessHandler` to `SearchRouter` constructor

**Purpose**: Inject `EntityAccessHandler` into `SearchRouter` so it can be passed to `SearchController`.

**Steps**:

1. Open `packages/foundation/src/Http/Router/SearchRouter.php`.
2. Add the import: `use Waaseyaa\Access\EntityAccessHandler;` (already present in the file's sibling `AccessPolicyRegistry.php` — same package allows the import).
3. Add constructor parameter: `private readonly ?EntityAccessHandler $accessHandler = null,`
4. Keep the existing constructor parameters unchanged; append `$accessHandler` as the last optional param.

**Resulting constructor signature**:
```php
public function __construct(
    private readonly array $config,
    private readonly DatabaseInterface $database,
    private readonly ?EntityTypeManagerInterface $entityTypeManager = null,
    private readonly ?EntityAccessHandler $accessHandler = null,
) {}
```

**Validation**:
- [ ] `SearchRouter` constructs without `$accessHandler` (backward-compatible default null).
- [ ] `composer cs-check` passes on the edited file.

---

## Subtask T002 — Thread `_account` from request into `SearchController`

**Purpose**: Pass the authenticated account and access handler to `SearchController` so it filters results.

**Steps**:

1. In `SearchRouter::handle()`, after extracting `$ctx`, add:
```php
$rawAccount = $request->attributes->get('_account');
$account = $rawAccount instanceof \Waaseyaa\Access\AccountInterface ? $rawAccount : null;
```

2. Change the `new SearchController(...)` call to include `account:` and `accessHandler:`:
```php
$searchController = new SearchController(
    entityTypeManager: $this->entityTypeManager,
    serializer: $serializer,
    embeddingStorage: $embeddingStorage,
    embeddingProvider: $embeddingProvider,
    accessHandler: $this->accessHandler,
    account: $account,
);
```

3. Add the import: `use Waaseyaa\Access\AccountInterface;`

**Important**: Do NOT use `$request->attributes->get('account')` (no underscore). Always `'_account'`.

**Validation**:
- [ ] An authenticated request (non-null `_account`) results in `SearchController` receiving a non-null `$account`.
- [ ] An unauthenticated request (null `_account`) results in `SearchController` receiving `null` — `SearchController` already handles this gracefully (skips access filter).
- [ ] `composer cs-check` passes.

---

## Subtask T003 — Wire `EntityAccessHandler` into `SearchRouter` at kernel registration

**Purpose**: Ensure `SearchRouter` receives the kernel's `EntityAccessHandler` at construction time, not just `null`.

**Steps**:

1. Find where `SearchRouter` is registered/constructed. Search:
```bash
grep -rn "SearchRouter" packages/ --include="*.php" | grep -v "test\|Test\|spec"
```
Likely in a `FoundationServiceProvider`, a route builder, or `AbstractKernel`.

2. At the construction site, pass the `EntityAccessHandler` from the kernel's service container. The pattern in AbstractKernel (line 372-374) shows `$this->logger` and `KernelServicesInterface` are available. If `EntityAccessHandler` is built during `discoverAccessPolicies()`, it is available via a stored property or return value.

3. If `EntityAccessHandler` is stored as `$this->entityAccessHandler` after `discoverAccessPolicies()`, pass it:
```php
new SearchRouter(
    config: $this->config,
    database: $this->database,
    entityTypeManager: $this->entityTypeManager,
    accessHandler: $this->entityAccessHandler ?? null,
)
```

4. If `SearchRouter` is registered in a service provider's `register()` before the kernel builds `EntityAccessHandler`, restructure so that registration happens in `boot()` after `discoverAccessPolicies()`.

**Validation**:
- [ ] `SearchRouter` receives a non-null `EntityAccessHandler` in the standard kernel boot path.
- [ ] Existing search routing tests (if any) still pass.

---

## Subtask T004 — Write `SemanticSearchAccessTest` (FR-010)

**Purpose**: Integration test proving authenticated semantic search returns only access-allowed rows for the requesting account (FR-010, SC-001).

**File**: `tests/Integration/Phase24/SemanticSearchAccessTest.php`

**Namespace**: `Waaseyaa\Tests\Integration\Phase24`

**Steps**:

1. Create `tests/Integration/Phase24/SemanticSearchAccessTest.php`.

2. Test setup:
   - Boot the kernel (use `DBALDatabase::createSqlite()` for in-memory storage).
   - Register a `node` entity type with two test nodes: `node:1` (public) and `node:2` (internal).
   - Register a test access policy for `node` that allows `viewer-a` to `view` only `node:1`, and `viewer-b` to `view` both.
   - Register a `SearchRouter` with the kernel's `EntityAccessHandler`.
   - Use an in-memory or mock `EmbeddingStorageInterface` that returns `[1, 2]` for any query (so both IDs are always in the semantic result).

3. Test assertions:
   - `viewer-a` issues search → result contains `node:1`, NOT `node:2`.
   - `viewer-b` issues search → result contains both `node:1` and `node:2`.

4. Test class outline:
```php
#[CoversClass(SearchRouter::class)]
final class SemanticSearchAccessTest extends TestCase
{
    #[Test]
    public function accessRestrictedRowsAreFilteredForViewerA(): void { ... }

    #[Test]
    public function viewerBReceivesFullResultSet(): void { ... }
}
```

5. Use `#[CoversNothing]` if the test exercises the full stack and precise coverage attribution is not possible.

**Validation**:
- [ ] Both test methods pass.
- [ ] Test does not hit a real database or embedding provider.
- [ ] `./vendor/bin/phpunit tests/Integration/Phase24/SemanticSearchAccessTest.php` exits 0.

---

## Definition of Done

- [ ] `packages/foundation/src/Http/Router/SearchRouter.php` accepts and threads `EntityAccessHandler` + `AccountInterface`.
- [ ] `SearchController` receives non-null `account` on authenticated requests.
- [ ] `SemanticSearchAccessTest` passes.
- [ ] `composer verify` exits 0 on this WP's changes.
- [ ] No new `getQuery()->execute()` callsites without binding (will be caught by WP04's gate).
- [ ] `Closes #1516` in the PR description.

## Risks

| Risk | Mitigation |
|---|---|
| `SearchRouter` registration site not obvious | Grep for `SearchRouter` in non-test PHP files; check `AbstractKernel`, service providers, and route builders |
| L0→L1 layer import (`EntityAccessHandler`) | Verify `bin/check-package-layers` still passes; Foundation already imports from Access in AccessPolicyRegistry |
| `EntityAccessHandler` not yet built when `SearchRouter` is registered | Move `SearchRouter` construction to after `discoverAccessPolicies()` completes |

## Reviewer Guidance

1. Confirm `$request->attributes->get('_account')` is used (with underscore), not `'account'`.
2. Confirm `SearchController` is constructed with both `account:` and `accessHandler:` — neither alone is sufficient.
3. Run `SemanticSearchAccessTest` directly and confirm both assertions fire.
4. Check `bin/check-package-layers` does not regress.
