---
work_package_id: WP05
title: Retro regression tests + M-B.1 issue + wrap-up
dependencies:
- WP03
- WP04
requirement_refs:
- FR-009
- FR-013
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T020
- T021
- T022
- T023
- T024
- T025
- T026
history:
- date: '2026-05-20T23:30:18Z'
  agent: claude:sonnet:tasks:tasks
  action: created
authoritative_surface: packages/path/tests/
execution_mode: code_change
owned_files:
- packages/path/tests/Unit/PathAliasResolverBindingTest.php
- packages/user/tests/Unit/AuthControllerFindUserByNameBindingTest.php
- packages/seo/tests/Unit/SitemapGeneratorBindingTest.php
- packages/user/tests/Unit/UserBlockServiceBindingTest.php
- CHANGELOG.md
- docs/specs/access-control.md
tags: []
---

# WP05 — Retro regression tests + M-B.1 issue + wrap-up

**Mission**: `access-fail-closed-completeness-01KS3RJT`
**Depends on**: WP03 (hard), WP04 (soft)
**Requirements**: FR-009, FR-013, SC-004, SC-005, SC-007

## Objective

Add four unit regression tests for the callsites that were manually fixed in #1518, #1525, and #1527. Each test uses `RecordingEntityQuery` (from WP03) to assert that the production code either binds an account (`setAccount()`) or explicitly opts out (`accessCheck(false)`). File the M-B.1 GitHub tracking issue. Update `CHANGELOG.md` and finalize `docs/specs/access-control.md`.

## Context

### The four previously-fixed callsites

| Issue | Class | Method | Expected binding |
|---|---|---|---|
| #1518 | `PathAliasResolver` | `resolve()` | `accessCheck(false)` — system-context: aliases have no per-user ACL |
| #1525 | `AuthController` | `findUserByName()` | `setAccount($account)` — user context available |
| #1527 | `SitemapGenerator` | `collectFromEntityTypes()` | `accessCheck(false)` — sitemap is always anonymous |
| #1527 | `UserBlockService` | `isBlocked()` | likely `setAccount($account)` — check the actual fix |

**CRITICAL**: Before writing each test, verify the actual pattern in the production code:
```bash
grep -n "getQuery\|setAccount\|accessCheck" packages/path/src/PathAliasResolver.php
grep -n "getQuery\|setAccount\|accessCheck" packages/auth/src/AuthController.php packages/user/src/AuthController.php
grep -n "getQuery\|setAccount\|accessCheck" packages/seo/src/SitemapGenerator.php
grep -n "getQuery\|setAccount\|accessCheck" packages/user/src/UserBlockService.php
```
The test must assert the pattern that IS in the code, not the pattern you assume. If the code uses `accessCheck(false)`, assert `$query->accessChecks === [false]`. If it uses `setAccount()`, assert `$query->boundAccount !== null`.

### Assumption A3: AuthController location

The plan assumes `AuthController` is in `packages/auth/` or `packages/user/`. Verify before writing the test:
```bash
find packages/ -name "AuthController.php" -path "*/src/*"
```

### Package for `UserBlockService`

```bash
find packages/ -name "UserBlockService.php" -path "*/src/*"
```
Almost certainly `packages/user/src/UserBlockService.php`. If so, both `AuthController` and `UserBlockService` tests go in `packages/user/tests/Unit/`.

### Test injection pattern

The retro tests need to inject `RecordingEntityQuery` in place of the real `EntityQueryInterface` returned by `$storage->getQuery()`. Common approaches:
1. **Constructor injection**: If the class accepts an `EntityStorageInterface` (or `EntityTypeManagerInterface`) in its constructor, use a mock/stub that returns `new RecordingEntityQuery()` from `getQuery()`.
2. **Method injection**: If the class accepts the storage as a method param, pass a stub.
3. **Reflection injection**: Set a private property via `Reflection` — avoid if possible.

For all four classes, use `createMock(EntityStorageInterface::class)` configured to return a `RecordingEntityQuery`. If the class uses `EntityTypeManagerInterface::getStorage()`, mock at that level.

**Example pattern**:
```php
$query = new RecordingEntityQuery();
$storage = $this->createMock(EntityStorageInterface::class);
$storage->method('getQuery')->willReturn($query);
$storage->method('loadMultiple')->willReturn([]);

$resolver = new PathAliasResolver(storage: $storage, ...);
$resolver->resolve('/some-path');

self::assertContains(false, $query->accessChecks,
    'PathAliasResolver::resolve() must call accessCheck(false) for system-context query.');
```

**Note**: `createMock()` cannot mock `final class` — use `createStub()` or a manual anonymous class for `final` storage implementations. Check if the storage impl is final.

## Branch Strategy

- Planning base: `main`
- Merge target: `main`
- Hard dep: WP03 must be merged.
- Soft dep: WP04 should be merged (so baseline is clean).
- Implement command: `spec-kitty agent action implement WP05 --agent <name>`

---

## Subtask T020 — Write `PathAliasResolverBindingTest` (retro #1518)

**Purpose**: Regression test proving `PathAliasResolver::resolve()` binds access correctly on entity queries (FR-009, #1518).

**File**: `packages/path/tests/Unit/PathAliasResolverBindingTest.php`

**Steps**:

1. Verify the actual binding pattern:
```bash
grep -n "getQuery\|setAccount\|accessCheck" packages/path/src/PathAliasResolver.php
```

2. Identify the constructor dependencies of `PathAliasResolver`. Grep:
```bash
head -50 packages/path/src/PathAliasResolver.php
```

3. Create the test file in `packages/path/tests/Unit/`:
```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Path\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Testing\RecordingEntityQuery;
use Waaseyaa\Path\PathAliasResolver;
// ... other imports

#[CoversClass(PathAliasResolver::class)]
final class PathAliasResolverBindingTest extends TestCase
{
    #[Test]
    public function resolveCallsAccessCheckFalseForSystemContext(): void
    {
        $query = new RecordingEntityQuery();
        $storage = $this->createMock(/* storage interface */);
        $storage->method('getQuery')->willReturn($query);

        $resolver = new PathAliasResolver(/* inject storage */);
        $resolver->resolve('/test-path');

        self::assertContains(false, $query->accessChecks,
            'PathAliasResolver::resolve() must use accessCheck(false) for system-context query (regression #1518).'
        );
    }
}
```

4. Adjust assertions based on actual production pattern found in step 1.

**Validation**:
- [ ] Test passes on current codebase.
- [ ] Test would fail if `accessCheck(false)` (or `setAccount()`) was removed from the production code.
- [ ] `./vendor/bin/phpunit packages/path/tests/Unit/PathAliasResolverBindingTest.php` exits 0.

---

## Subtask T021 — Write `AuthControllerFindUserByNameBindingTest` (retro #1525)

**Purpose**: Regression test proving `AuthController::findUserByName()` binds access correctly (FR-009, #1525).

**File**: `packages/user/tests/Unit/AuthControllerFindUserByNameBindingTest.php` (or `packages/auth/tests/Unit/` — confirm location first)

**Steps**:

1. Locate `AuthController`:
```bash
find packages/ -name "AuthController.php" -path "*/src/*"
grep -n "findUserByName\|getQuery\|setAccount\|accessCheck" <found-file>
```

2. Create the test with the same pattern as T020. `findUserByName()` likely passes an account via method argument or class constructor — identify and inject.

3. Expected assertion (verify actual code):
```php
// If findUserByName uses setAccount:
self::assertNotNull($query->boundAccount,
    'AuthController::findUserByName() must call setAccount() (regression #1525).'
);

// If it uses accessCheck(false):
self::assertContains(false, $query->accessChecks,
    'AuthController::findUserByName() must use accessCheck(false) (regression #1525).'
);
```

4. Place the test file in the correct package's `tests/Unit/` directory.

**Validation**:
- [ ] Test passes.
- [ ] `./vendor/bin/phpunit <package>/tests/Unit/AuthControllerFindUserByNameBindingTest.php` exits 0.

---

## Subtask T022 — Write `SitemapGeneratorBindingTest` (retro #1527)

**Purpose**: Regression test proving `SitemapGenerator::collectFromEntityTypes()` binds access correctly (FR-009, #1527).

**File**: `packages/seo/tests/Unit/SitemapGeneratorBindingTest.php`

**Steps**:

1. Locate `SitemapGenerator`:
```bash
grep -n "getQuery\|setAccount\|accessCheck" packages/seo/src/SitemapGenerator.php
```

2. `SitemapGenerator` likely uses `EntityTypeManagerInterface` to iterate entity types. Mock at the `EntityTypeManagerInterface` level if needed, or mock individual storage instances.

3. The expected pattern for sitemap is `accessCheck(false)` (sitemap is always anonymous per the baseline comment in contracts/getquery-bindings-baseline-format.md). Verify.

4. Create:
```php
#[CoversClass(SitemapGenerator::class)]
final class SitemapGeneratorBindingTest extends TestCase
{
    #[Test]
    public function collectFromEntityTypesUsesAccessCheckFalse(): void
    {
        $query = new RecordingEntityQuery();
        // ... mock setup
        $generator = new SitemapGenerator(/* deps */);
        $generator->collectFromEntityTypes(['node']);
        self::assertContains(false, $query->accessChecks,
            'SitemapGenerator::collectFromEntityTypes() must use accessCheck(false) for system-context (regression #1527).'
        );
    }
}
```

**Validation**:
- [ ] Test passes.
- [ ] `./vendor/bin/phpunit packages/seo/tests/Unit/SitemapGeneratorBindingTest.php` exits 0.

---

## Subtask T023 — Write `UserBlockServiceBindingTest` (retro #1527)

**Purpose**: Regression test proving `UserBlockService::isBlocked()` binds access correctly (FR-009, #1527).

**File**: `packages/user/tests/Unit/UserBlockServiceBindingTest.php`

**Steps**:

1. Locate `UserBlockService`:
```bash
grep -n "getQuery\|setAccount\|accessCheck" packages/user/src/UserBlockService.php
```

2. `isBlocked()` likely accepts an `AccountInterface` argument — inject the same account into the `RecordingEntityQuery` setup.

3. Expected pattern: `setAccount($account)` since user blocking is per-user context. Verify actual code.

4. Create:
```php
#[CoversClass(UserBlockService::class)]
final class UserBlockServiceBindingTest extends TestCase
{
    #[Test]
    public function isBlockedCallsSetAccountWithRequestAccount(): void
    {
        $query = new RecordingEntityQuery();
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(42);
        // ... storage mock returning $query
        $service = new UserBlockService(/* deps */);
        $service->isBlocked($account, /* blocked account */);
        self::assertNotNull($query->boundAccount,
            'UserBlockService::isBlocked() must call setAccount() (regression #1527).'
        );
    }
}
```

**Validation**:
- [ ] Test passes.
- [ ] `./vendor/bin/phpunit packages/user/tests/Unit/UserBlockServiceBindingTest.php` exits 0.

---

## Subtask T024 — Add `[Unreleased]` CHANGELOG entry

**Purpose**: Record all five WPs in the project changelog per the release workflow convention (see `feedback_changelog_release_workflow.md`).

**File**: `CHANGELOG.md`

**Steps**:

1. Open `CHANGELOG.md`. Locate the `## [Unreleased]` section (top of the file, below the title).

2. Add bullets under `### Added`, `### Fixed`, or `### Changed` as appropriate. Example:

```markdown
## [Unreleased]

### Fixed

- fix(access,foundation): thread request account through SearchRouter into SearchController; closes #1516
- fix(foundation): replace AccessPolicyRegistry silent-skip heuristic with container-resolved instantiation; auto-discovers policies with service dependencies; fail-closed on unresolvable deps; closes #1519
- fix(entity,access): add RecordingEntityQuery shared test helper to packages/entity/testing/; closes #1529
- fix(foundation): add bin/check-getquery-bindings CI gate and tools/getquery-bindings-baseline.txt; closes #1528
- test(path,auth,seo,user): add regression tests for PathAliasResolver, AuthController, SitemapGenerator, UserBlockService getQuery bindings (#1518, #1525, #1527)
```

3. Follow the existing bullet style in the file — do not invent a new format.

4. Do NOT add a version heading — only `[Unreleased]`. The release-cut workflow promotes it.

**Validation**:
- [ ] `CHANGELOG.md` `[Unreleased]` section has entries for all five WPs.
- [ ] No new version heading added.
- [ ] `composer cs-check` passes (CHANGELOG is not PHP, but verify no linter touches it).

---

## Subtask T025 — Finalize `docs/specs/access-control.md` with registry resolver contract

**Purpose**: Update the access control spec to document the new `PolicyDependencyResolverInterface` and container-resolved registry pattern so future contributors have canonical reference (C-003, SC-005).

**File**: `docs/specs/access-control.md`

**Steps**:

1. Open `docs/specs/access-control.md`. Find the section discussing `AccessPolicyRegistry`.

2. Add or update a subsection: `### Container-resolved policy instantiation (M-B, v0.1.0-alpha.187+)`.

3. Document:
   - The two-phase resolution algorithm.
   - `PolicyDependencyResolverInterface` signature and 5-rule resolution order.
   - The fail-closed boot behavior (no silent log, `PolicyInstantiationException` thrown).
   - How to write a new policy with injected dependencies (no manual `ServiceProvider::boot()` needed).
   - The `EntityAccessHandler` forward-reference two-phase pattern (for `ParentDelegatedAccessPolicy`-style policies).

4. Reference the contracts file for full detail: `kitty-specs/access-fail-closed-completeness-01KS3RJT/contracts/PolicyDependencyResolverInterface.md` (note: this is a planning artifact; the spec should stand alone for future readers — synthesize the key points into the spec text).

5. Add drift detector stamp at the top of the spec:
```markdown
<!-- Spec reviewed 2026-05-20 - updated for M-B container-resolved registry -->
```

**Validation**:
- [ ] `docs/specs/access-control.md` has the new subsection.
- [ ] The two-phase algorithm is described.
- [ ] `tools/drift-detector.sh` does not flag the spec as stale after this update.

---

## Subtask T026 — File GitHub issue "M-B.1: drive `getquery-bindings-baseline.txt` to zero"

**Purpose**: Create the tracking issue required by SC-007 / FR-013 before mission merge.

**This is an action, not a code change.**

**Steps**:

1. File the GitHub issue using `gh`:
```bash
gh issue create \
  --title "M-B.1: drive \`getquery-bindings-baseline.txt\` to zero" \
  --body "Follow-up to mission access-fail-closed-completeness-01KS3RJT (M-B).

The M-B mission added \`bin/check-getquery-bindings\` and committed an initial baseline (\`tools/getquery-bindings-baseline.txt\`) capturing currently-exempt unbound \`getQuery()->...->execute()\` callsites.

**Goal of this mission**: drive the baseline to zero by fixing each exemption:
- Callsites using system-context \`accessCheck(false)\` should be evaluated for whether they can be given a real account or whether the exemption is permanent.
- Callsites that are genuinely permanent system-context should have inline justification comments in the production code.

**Reference**: M-B PR (link TBD), \`tools/getquery-bindings-baseline.txt\`.

**Acceptance**: \`php bin/check-getquery-bindings --generate-baseline\` produces an empty (header-only) baseline file.
" \
  --label "follow-up,access,technical-debt"
```

2. Capture the issue number from the output (e.g. `#1542`).

3. Reference the issue number in:
   - The WP05 PR description: "Closes #1516, #1519, #1528, #1529. Follow-up tracking: #<M-B.1 issue number>."
   - The `CHANGELOG.md` entry (if not already there).

4. **Per `feedback_pr_traceability_signals.md`**: after the mission PR merges, also close the tracking issue IF needed, or leave it open (it is a follow-up mission, so it should remain open).

**Validation**:
- [ ] GitHub issue "M-B.1: drive `getquery-bindings-baseline.txt` to zero" exists and is open.
- [ ] Issue number is referenced in the WP05 PR description.
- [ ] `composer verify` still exits 0 (no code change needed for this subtask).

---

## Definition of Done

- [ ] All four regression tests pass (`PathAliasResolverBindingTest`, `AuthControllerFindUserByNameBindingTest`, `SitemapGeneratorBindingTest`, `UserBlockServiceBindingTest`).
- [ ] Each test would fail if the production binding was removed (verify by briefly removing the binding and running the test).
- [ ] `CHANGELOG.md` `[Unreleased]` updated.
- [ ] `docs/specs/access-control.md` updated with registry resolver contract section.
- [ ] M-B.1 GitHub issue filed and referenced.
- [ ] `composer verify` exits 0.
- [ ] PR description: `Closes #1516, #1519, #1528, #1529` in footer.

## Risks

| Risk | Mitigation |
|---|---|
| Assumption A3 (AuthController location) | Grep for `findUserByName` before creating the test file path |
| Some callsites use `accessCheck(false)` not `setAccount()` | Grep actual production code before asserting binding pattern |
| `RecordingEntityQuery` not available (WP03 not merged) | Hard dependency — do not start T020–T023 until WP03 is in `main` |
| `createMock()` on `final class` storage | Use `createStub()` or anonymous class for final implementations |
| `gh` labels may not exist | Drop `--label` flag if labels are not pre-configured in the repo |

## Reviewer Guidance

1. Run all four regression tests: `./vendor/bin/phpunit --filter "PathAliasResolverBindingTest|AuthControllerFindUserByNameBindingTest|SitemapGeneratorBindingTest|UserBlockServiceBindingTest"` — all must pass.
2. Temporarily remove the binding (e.g. comment out `accessCheck(false)` in `PathAliasResolver`) and confirm the corresponding test fails — this validates the test is a genuine regression guard.
3. Confirm the M-B.1 GitHub issue exists and is linked from the PR.
4. Confirm `CHANGELOG.md` has entries for all five WPs under `[Unreleased]`.
5. Confirm `docs/specs/access-control.md` drift-detector stamp is updated.
