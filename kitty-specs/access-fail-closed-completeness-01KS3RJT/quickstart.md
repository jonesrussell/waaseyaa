# Quickstart: Access Fail-Closed Completeness (M-B)

**For implementers picking up a WP in this mission.**

---

## Branch contract

All WPs land directly on `main`. No worktrees or feature branches unless the
Spec Kitty lane workflow creates them automatically.

---

## Key files to read before starting any WP

| File | Why |
|---|---|
| `kitty-specs/access-fail-closed-completeness-01KS3RJT/spec.md` | Source of truth for all FRs and constraints |
| `kitty-specs/access-fail-closed-completeness-01KS3RJT/plan.md` | WP scope, file map, assumption flags |
| `kitty-specs/access-fail-closed-completeness-01KS3RJT/research.md` | Resolved design decisions (read before WP02 and WP04) |
| `kitty-specs/access-fail-closed-completeness-01KS3RJT/contracts/PolicyDependencyResolverInterface.md` | Resolver interface contract + full implementation sketch (WP02) |
| `kitty-specs/access-fail-closed-completeness-01KS3RJT/contracts/RecordingEntityQuery-contract.md` | Full RecordingEntityQuery implementation contract (WP03, WP05) |
| `kitty-specs/access-fail-closed-completeness-01KS3RJT/contracts/getquery-bindings-baseline-format.md` | Baseline file format and CI wiring spec (WP04) |

---

## WP-by-WP orientation

### WP01 — SearchRouter account threading

1. Read `packages/foundation/src/Http/Router/SearchRouter.php` — note `SearchController` is already constructed without `account:` or `accessHandler:`.
2. Read `packages/ai-vector/src/SearchController.php` — note it already accepts `?AccountInterface $account = null` and `?EntityAccessHandler $accessHandler = null`.
3. The gap: `SearchRouter::handle()` does not read `$request->attributes->get('_account')`.
4. Fix: add `?EntityAccessHandler $accessHandler = null` to `SearchRouter`'s constructor; read `_account` from request; pass both to `SearchController`.
5. Find where `SearchRouter` is registered (grep for `SearchRouter` in `packages/*/src/ServiceProvider/` or `packages/foundation/src/Kernel/`) and thread the kernel's `EntityAccessHandler` in.
6. Write `tests/Integration/Phase24/SemanticSearchAccessTest.php` using two users with different access policies.

**Gotcha**: `_account` (underscore prefix) not `account` — see CLAUDE.md §HTTP, auth, request lifecycle.

### WP02 — Container-resolved AccessPolicyRegistry

1. Read `research.md §R-001` for the two-phase algorithm.
2. Read `contracts/PolicyDependencyResolverInterface.md` for the full interface + implementation sketch.
3. Create `packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php`.
4. Create `packages/foundation/src/Kernel/Bootstrap/KernelPolicyDependencyResolver.php`.
5. Create `packages/foundation/src/Kernel/Bootstrap/Exception/PolicyInstantiationException.php`.
6. Edit `packages/foundation/src/Kernel/Bootstrap/AccessPolicyRegistry.php` — replace the heuristic skip with two-phase resolution; throw on failure.
7. Edit `packages/foundation/src/Kernel/AbstractKernel.php` line ~372 — wire `KernelPolicyDependencyResolver`.
8. Verify `ConfigEntityAccessPolicy` (array-first-param shape) still passes its unit tests.
9. Write `tests/Integration/Phase24/AttachmentPolicyDiscoveryTest.php` — boot kernel, assert `ParentDelegatedAccessPolicy` is the handler for `attachment` entities.
10. Write `packages/foundation/tests/Unit/Kernel/Bootstrap/AccessPolicyRegistryTest.php` — assert unresolvable dep throws `PolicyInstantiationException`.

**Key concern**: Verify Assumption A2 — at the time `discoverAccessPolicies()` runs, `KernelServicesInterface::get('Waaseyaa\Access\EntityAccessHandler')` must return `null` (not yet built). If it returns a real handler, the two-phase algorithm simplifies to one pass.

### WP03 — RecordingEntityQuery

1. Read `contracts/RecordingEntityQuery-contract.md` — the full implementation is there, copy it.
2. Create `packages/entity/testing/RecordingEntityQuery.php`.
3. Edit `packages/entity/composer.json` — add `"Waaseyaa\\Entity\\Testing\\": "testing/"` under `autoload-dev`.
4. Run `composer dump-autoload` and verify the new class is discoverable.
5. Find the two existing inline `EntityQueryInterface` stubs (grep: `implements EntityQueryInterface` in test files outside `testing/`) and migrate them.

**Constraint**: `RecordingEntityQuery.php` must NOT appear under `autoload` (production classmap). Only `autoload-dev`.

### WP04 — bin/check-getquery-bindings

1. Read `research.md §R-003` for the scanner algorithm (token-based, no external deps).
2. Read `contracts/getquery-bindings-baseline-format.md` for the baseline format and CI wiring.
3. Write `bin/check-getquery-bindings` as a pure-PHP CLI script using `token_get_all()`.
4. Run it: `php bin/check-getquery-bindings --generate-baseline` → this creates `tools/getquery-bindings-baseline.txt`. Commit both.
5. Wire into `composer.json` (see contracts doc for exact JSON diff).
6. Add "CI gates" section to `CLAUDE.md`.
7. Write `tests/Integration/Phase24/GetQueryBindingsGateTest.php` — creates a temp file with an unbound callsite, runs the script against it, asserts non-zero exit.

**Order note**: Run WP04 after WP01 and WP02 so the baseline reflects the fixed codebase (not the old callsites that WP01/WP02 fix).

### WP05 — Retro regression tests + wrap-up

1. Ensure WP03 (`RecordingEntityQuery`) is merged before starting WP05.
2. For each of the four regression targets, read the source file and the existing test to understand the current callsite:
   - `packages/path/src/PathAliasResolver.php`
   - `packages/user/src/UserBlockService.php`
   - `packages/seo/src/SitemapGenerator.php`
   - Auth controller: run `grep -rn "findUserByName" packages/ --include="*.php" -l` to confirm location.
3. Write four test files using `RecordingEntityQuery` (see contracts for assertion patterns).
4. Add `CHANGELOG.md` entry under `[Unreleased]`.
5. Update `docs/specs/access-control.md` — add registry resolver contract section.
6. File GitHub issue: *"M-B.1: drive `getquery-bindings-baseline.txt` to zero"*.

---

## Shared verification commands

```bash
# Run all tests
./vendor/bin/phpunit

# Run just this mission's new integration tests (once Phase24 tests exist)
./vendor/bin/phpunit tests/Integration/Phase24/

# Run just the retro regression tests (WP05)
./vendor/bin/phpunit --filter "PathAliasResolverBindingTest|AuthControllerFindUserByNameBindingTest|SitemapGeneratorBindingTest|UserBlockServiceBindingTest"

# Full CI gate
composer verify

# Check getquery bindings specifically
php bin/check-getquery-bindings

# Static analysis
composer phpstan

# Code style
composer cs-check
composer cs-fix
```

---

## Dead-code gate note

Any new class in `packages/foundation/src/` or `packages/entity/testing/` that is
not yet called by production code must have `@api` PHPDoc to suppress the dead-code gate.
`PolicyDependencyResolverInterface` and `KernelPolicyDependencyResolver` need `@api`.
`PolicyInstantiationException` is called at runtime → no `@api` needed.
`RecordingEntityQuery` is autoload-dev → not scanned by dead-code gate.

---

## Issues this mission closes (for merge commit footer)

```
Closes #1516
Closes #1519
Closes #1528
Closes #1529
```

Issues #1518, #1525, #1527 remain closed — WP05 adds regression tests, does NOT reopen.
