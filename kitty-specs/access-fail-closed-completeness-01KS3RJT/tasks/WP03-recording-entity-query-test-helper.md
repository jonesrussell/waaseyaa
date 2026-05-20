---
work_package_id: WP03
title: RecordingEntityQuery test helper
dependencies: []
requirement_refs:
- FR-008
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: "Planning/base branch: main\nMerge target: main\nExecution worktree is allocated by finalize-tasks per lanes.json.\nDo NOT create a branch manually — spec-kitty agent action implement WP03 handles it.\n"
subtasks:
- T012
- T013
- T014
history:
- date: '2026-05-20T23:30:18Z'
  agent: claude:sonnet:tasks:tasks
  action: created
authoritative_surface: packages/entity/testing/
execution_mode: code_change
owned_files:
- packages/entity/testing/RecordingEntityQuery.php
- packages/entity/composer.json
tags: []
---

# WP03 — RecordingEntityQuery test helper

**Mission**: `access-fail-closed-completeness-01KS3RJT`
**Closes**: #1529
**Requirements**: FR-008, FR-009 (enabler)

## Objective

Create `Waaseyaa\Entity\Testing\RecordingEntityQuery` — a shared test stub that implements `EntityQueryInterface`, records every `accessCheck()` and `setAccount()` call, and stubs all other methods to return `$this` or a benign empty result. Wire it under `autoload-dev` only, so it is never shipped to consumer production installs. Migrate any existing inline `EntityQueryInterface` anonymous stubs in the test suite to use this helper.

This WP is an enabler: WP05's four retro regression tests all depend on `RecordingEntityQuery` being available.

## Context

### Existing `packages/entity/testing/` directory

Per the plan (verified 2026-05-20), `packages/entity/testing/` already exists and hosts other test helpers. The `packages/entity/composer.json` `autoload-dev` section already contains:
- `"Waaseyaa\\Entity\\PhpStan\\": "testing/PhpStan/"`
- `"Waaseyaa\\Entity\\Testing\\Translation\\": "testing/Translation/"`

The new entry `"Waaseyaa\\Entity\\Testing\\": "testing/"` is a parent namespace of the existing `Translation\` entry. PSR-4 resolves from the most-specific prefix, so `Waaseyaa\Entity\Testing\Translation\*` continues to resolve to `testing/Translation/` correctly.

### CLAUDE.md gotcha

> "Never put classes that extend dev-only deps under `autoload`." `RecordingEntityQuery` must go under `autoload-dev` only, never `autoload`. A class in `src/` would be scanned by `PackageManifestCompiler` on consumer production installs and could crash kernel boot.

### Constraint C-002

`RecordingEntityQuery` lives in `packages/entity/testing/` (not `packages/entity/src/`) and is registered under `autoload-dev`.

## Branch Strategy

- Planning base: `main`
- Merge target: `main`
- No dependencies on other M-B WPs.
- Implement command: `spec-kitty agent action implement WP03 --agent <name>`

---

## Subtask T012 — Create `packages/entity/testing/RecordingEntityQuery.php`

**Purpose**: Implement the shared `EntityQueryInterface` stub that records access-binding calls for use in all regression tests.

**File**: `packages/entity/testing/RecordingEntityQuery.php`

**Full implementation** (copy exactly from `contracts/RecordingEntityQuery-contract.md`):

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Testing;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;

/**
 * Test stub for EntityQueryInterface that records access-binding calls.
 *
 * All chainable methods return $this.
 * execute() returns $stubbedResults (configurable via withResults()).
 *
 * Inspection properties:
 *   $accessChecks  — list<bool>: each accessCheck() call value, in call order.
 *   $boundAccount  — ?AccountInterface: last account passed to setAccount().
 *
 * @api — Public test-helper surface. Safe to depend on from any package's tests.
 */
final class RecordingEntityQuery implements EntityQueryInterface
{
    /** @var list<bool> */
    public array $accessChecks = [];

    public ?AccountInterface $boundAccount = null;

    /** @var list<int|string> */
    private array $stubbedResults = [];

    /** @param list<int|string> $ids */
    public function withResults(array $ids): static
    {
        $this->stubbedResults = $ids;
        return $this;
    }

    public function condition(string $field, mixed $value, string $operator = '='): static { return $this; }
    public function exists(string $field): static { return $this; }
    public function notExists(string $field): static { return $this; }
    public function sort(string $field, string $direction = 'ASC'): static { return $this; }
    public function range(int $offset, int $limit): static { return $this; }
    public function count(): static { return $this; }

    public function accessCheck(bool $check = true): static
    {
        $this->accessChecks[] = $check;
        return $this;
    }

    public function setAccount(?AccountInterface $account): static
    {
        $this->boundAccount = $account;
        return $this;
    }

    /** @return array<int|string> */
    public function execute(): array
    {
        return $this->stubbedResults;
    }
}
```

**Steps**:

1. Create the file at `packages/entity/testing/RecordingEntityQuery.php` with the above content.
2. Confirm the namespace is `Waaseyaa\Entity\Testing` (not `Waaseyaa\Entity\Testing\RecordingEntityQuery`).
3. Confirm it implements all methods declared in `packages/entity/src/Storage/EntityQueryInterface.php` (8 methods: `condition`, `exists`, `notExists`, `sort`, `range`, `count`, `accessCheck`, `setAccount`, `execute`).

**Validation**:
- [ ] File exists at `packages/entity/testing/RecordingEntityQuery.php`.
- [ ] Implements all 9 methods from `EntityQueryInterface` (8 from interface + `withResults()` helper).
- [ ] `$accessChecks` records multiple calls in order: `accessCheck(true)->accessCheck(false)` produces `[true, false]`.
- [ ] `$boundAccount` is updated on each `setAccount()` call.
- [ ] `execute()` returns `$stubbedResults` (default `[]`).
- [ ] `composer phpstan` passes on the new file.

---

## Subtask T013 — Add `autoload-dev` entry to `packages/entity/composer.json`

**Purpose**: Register the `Waaseyaa\Entity\Testing\` PSR-4 namespace so test code can `use Waaseyaa\Entity\Testing\RecordingEntityQuery` in any package's test suite.

**File**: `packages/entity/composer.json`

**Steps**:

1. Open `packages/entity/composer.json`.
2. Locate the `autoload-dev.psr-4` section.
3. Add `"Waaseyaa\\Entity\\Testing\\": "testing/"` to that section.
4. The final `autoload-dev.psr-4` should look like (order must satisfy `config.sort-packages: true`):
```json
"autoload-dev": {
    "psr-4": {
        "Waaseyaa\\Entity\\PhpStan\\": "testing/PhpStan/",
        "Waaseyaa\\Entity\\Testing\\": "testing/",
        "Waaseyaa\\Entity\\Testing\\Translation\\": "testing/Translation/",
        "Waaseyaa\\Entity\\Tests\\": "tests/"
    }
}
```
5. Note: `"Waaseyaa\\Entity\\Testing\\"` comes before `"Waaseyaa\\Entity\\Testing\\Translation\\"` in lex sort — this is correct and satisfies `config.sort-packages`.
6. Run `composer dump-autoload` to regenerate the classmap and verify the new namespace resolves.

**Validation**:
- [ ] `RecordingEntityQuery` is resolvable in a test context after `composer dump-autoload`.
- [ ] `RecordingEntityQuery` does NOT appear in the production autoload classmap (`composer dump-autoload --no-dev | grep RecordingEntityQuery` returns empty).
- [ ] `composer check-composer-policy` passes (no CP002/CP003/CP006 violations introduced).
- [ ] `bin/check-package-layers` passes (no new layer violations).

---

## Subtask T014 — Migrate existing inline `EntityQueryInterface` stubs

**Purpose**: Replace any bespoke anonymous stubs of `EntityQueryInterface` in the test suite with `RecordingEntityQuery` to reduce duplication and validate the helper.

**Steps**:

1. Locate existing inline stubs:
```bash
grep -rn "EntityQueryInterface" packages/*/tests/ --include="*.php" -l
grep -rn "implements EntityQueryInterface" packages/*/tests/ --include="*.php"
```

2. For each file found, check if it creates an anonymous class implementing `EntityQueryInterface`. If so:
   - Replace the anonymous class instantiation with `new RecordingEntityQuery()`.
   - Add `use Waaseyaa\Entity\Testing\RecordingEntityQuery;` to the file's imports.
   - Adapt any assertions: if the old stub tracked state via local variables, switch to `$query->boundAccount` and `$query->accessChecks`.

3. Common patterns to migrate:
```php
// OLD: anonymous stub
$query = new class implements EntityQueryInterface {
    public bool $accessCheckCalled = false;
    public function accessCheck(bool $check = true): static { $this->accessCheckCalled = true; return $this; }
    // ... other stubs
};

// NEW: RecordingEntityQuery
$query = new RecordingEntityQuery();
// ... assertions use $query->accessChecks and $query->boundAccount
```

4. Run the affected test suites:
```bash
./vendor/bin/phpunit packages/auth/tests/ packages/seo/tests/ packages/user/tests/
```

**If no inline stubs exist**: T014 is a no-op (mark complete with note "no inline stubs found"). This is possible if the existing tests use mocking or have already been migrated.

**Validation**:
- [ ] No anonymous classes implementing `EntityQueryInterface` remain in test files (or all remaining ones have a documented rationale for staying bespoke).
- [ ] All migrated test files still pass.
- [ ] `composer phpstan` passes on all modified files.

---

## Definition of Done

- [ ] `packages/entity/testing/RecordingEntityQuery.php` exists and implements `EntityQueryInterface`.
- [ ] `packages/entity/composer.json` `autoload-dev` includes `"Waaseyaa\\Entity\\Testing\\": "testing/"`.
- [ ] `RecordingEntityQuery` is NOT in production autoload classmap.
- [ ] Any migrated inline stubs compile and test-pass.
- [ ] `composer verify` exits 0.
- [ ] `Closes #1529` in the PR description.

## Risks

| Risk | Mitigation |
|---|---|
| PSR-4 sub-namespace conflict (`Testing\` vs `Testing\Translation\`) | More-specific prefix wins in PSR-4; test with `composer dump-autoload` and resolve a `Translation\*` class to confirm |
| `EntityQueryInterface` adds new methods in future | `RecordingEntityQuery` uses `final class` — PHPUnit will surface a missing method immediately |
| Production classmap contamination | Verify with `composer dump-autoload --no-dev | grep RecordingEntityQuery` |

## Reviewer Guidance

1. Confirm `packages/entity/testing/RecordingEntityQuery.php` is under `testing/`, not `src/`.
2. Confirm `autoload-dev` (not `autoload`) in `composer.json`.
3. Run `./vendor/bin/phpunit packages/entity/tests/` and confirm no new failures.
4. Spot-check: `(new RecordingEntityQuery())->accessCheck(true)->accessCheck(false)->accessChecks === [true, false]`.
