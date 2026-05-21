---
work_package_id: WP02
title: Container-resolved AccessPolicyRegistry
dependencies: []
requirement_refs:
- FR-002
- FR-003
- FR-004
- FR-011
- FR-012
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-access-fail-closed-completeness-01KS3RJT
base_commit: 32ebd5f145ed7035f8603e6c4d25c244ee690154
created_at: '2026-05-20T23:56:10.395356+00:00'
subtasks:
- T005
- T006
- T007
- T008
- T009
- T010
- T011
shell_pid: "717401"
agent: "claude:opus-4-7:reviewer:reviewer"
history:
- date: '2026-05-20T23:30:18Z'
  agent: claude:sonnet:tasks:tasks
  action: created
authoritative_surface: packages/foundation/src/Kernel/Bootstrap/
execution_mode: code_change
owned_files:
- packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php
- packages/foundation/src/Kernel/Bootstrap/KernelPolicyDependencyResolver.php
- packages/foundation/src/Kernel/Bootstrap/Exception/PolicyInstantiationException.php
- packages/foundation/src/Kernel/Bootstrap/AccessPolicyRegistry.php
- packages/foundation/src/Kernel/AbstractKernel.php
- packages/foundation/tests/Unit/Kernel/Bootstrap/AccessPolicyRegistryTest.php
- tests/Integration/Phase24/AttachmentPolicyDiscoveryTest.php
tags: []
---

# WP02 — Container-resolved AccessPolicyRegistry

**Mission**: `access-fail-closed-completeness-01KS3RJT`
**Closes**: #1519
**Requirements**: FR-002, FR-003, FR-004, FR-011, FR-012

## Objective

Replace `AccessPolicyRegistry`'s silent-skip heuristic (which skips any policy whose first constructor parameter is not `array`) with a container-resolved instantiation protocol. After this WP:
- `ParentDelegatedAccessPolicy` (and any future service-injected policy) is auto-instantiated without manual `ServiceProvider::boot()` registration.
- If any `#[PolicyAttribute]` class cannot be instantiated (unresolvable dep, constructor throws), kernel boot **fails immediately** with a `PolicyInstantiationException` naming the class and failing parameter — no silent log, no continue.

## Context

### Current state (verified 2026-05-20)

`AccessPolicyRegistry::discover()` (`packages/foundation/src/Kernel/Bootstrap/AccessPolicyRegistry.php`):
- Lines 41–58: If the first constructor param is not `array`, logs an error and does `continue` — the policy is **silently dropped**.
- Lines 63–69: `catch (\Throwable)` also logs and continues — any instantiation failure is swallowed.
- `ParentDelegatedAccessPolicy` has `#[PolicyAttribute(entityType: 'attachment')]` and requires `EntityTypeManagerInterface` + `EntityAccessHandler` — currently dead.

`AbstractKernel::discoverAccessPolicies()` (line ~372):
```php
new AccessPolicyRegistry($this->logger)->discover($this->manifest)
```
The kernel already has `$this->kernelServices` (a `KernelServicesInterface` implementation) that resolves bound services by class name.

### Contract (fully designed in contracts/)

See `kitty-specs/access-fail-closed-completeness-01KS3RJT/contracts/PolicyDependencyResolverInterface.md` for:
- `PolicyDependencyResolverInterface::resolveParameter()` signature and 5-rule resolution algorithm.
- `KernelPolicyDependencyResolver` full implementation.
- `PolicyInstantiationException` definition.
- Two-phase algorithm for the `EntityAccessHandler` circular-dependency case.

**Read that contract before coding.** Do not re-derive the interface.

### Circular dependency (Assumption A2 — flagged)

`ParentDelegatedAccessPolicy` requires `EntityAccessHandler` as a constructor argument. But `EntityAccessHandler` is the return value of `discover()` — it doesn't exist yet when phase-1 runs.

**Resolution (two-phase algorithm per contracts/PolicyDependencyResolverInterface.md)**:
- Phase 1: instantiate all policies whose constructor does NOT request `EntityAccessHandler`. Build a preliminary `EntityAccessHandler` from phase-1 policies.
- Phase 2: instantiate deferred policies (those that DO request `EntityAccessHandler`), substituting the phase-1 `EntityAccessHandler` where needed. Build the final `EntityAccessHandler` from phase-1 + phase-2 policies.
- Return the final `EntityAccessHandler`.

This means `KernelServicesInterface::get(EntityAccessHandler::class)` will return null during policy discovery (kernel hasn't bound it yet). The resolver must detect this and, for a param typed `EntityAccessHandler`, return the preliminary handler from phase 1, not null and not throw.

**Implementation approach**: Pass the preliminary `EntityAccessHandler` (or null for phase 1) as context to `resolveParameter()`. One clean approach: add an optional `?EntityAccessHandler $preliminaryHandler = null` parameter to `resolveParameter()`, or store it as state on the resolver before phase-2 starts.

## Branch Strategy

- Planning base: `main`
- Merge target: `main`
- No dependencies on other WPs.
- Implement command: `spec-kitty agent action implement WP02 --agent <name>`

---

## Subtask T005 — Create `PolicyDependencyResolverInterface`

**Purpose**: Define the typed L0 contract the registry uses to resolve policy constructor arguments (NFR-005: no Symfony container types in public signature).

**File**: `packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php`

**Steps**:

1. Create the file exactly as specified in `contracts/PolicyDependencyResolverInterface.md` §Interface Definition.
2. Mark the interface `@api` — it is adopted by M-D per the contract note.
3. The interface must use only PHP built-in types and Waaseyaa types in its signature. No `Psr\Container\*`, no `Symfony\Component\DependencyInjection\*`.

**Resulting interface**:
```php
interface PolicyDependencyResolverInterface
{
    /**
     * @param class-string $policyClass
     * @param array<string> $entityTypes
     * @throws PolicyInstantiationException
     */
    public function resolveParameter(string $policyClass, \ReflectionParameter $param, array $entityTypes): mixed;
}
```

**Validation**:
- [ ] File exists with correct namespace `Waaseyaa\Foundation\Kernel\Bootstrap`.
- [ ] No Symfony or PSR-11 imports in the file.
- [ ] `composer phpstan` passes.

---

## Subtask T006 — Create `KernelPolicyDependencyResolver`

**Purpose**: Implement the resolver protocol using the kernel's `KernelServicesInterface::get()` for service resolution.

**File**: `packages/foundation/src/Kernel/Bootstrap/KernelPolicyDependencyResolver.php`

**Steps**:

1. Create the file per `contracts/PolicyDependencyResolverInterface.md` §Implementation.
2. Constructor: `__construct(private readonly KernelServicesInterface $kernelServices)`.
3. Implement `resolveParameter()` following the 5-rule priority order from the contract:
   - Rule 1: `array` type → return entity-types array.
   - Rule 2: non-builtin type → try `$this->kernelServices->get($typeName)`.
   - Rule 3: nullable + service unbound → return `null`.
   - Rule 4: has default value + service unbound → return `$param->getDefaultValue()`.
   - Rule 5: unresolvable → throw `PolicyInstantiationException`.
4. Add a `withPreliminaryHandler(EntityAccessHandler $handler): static` method (or equivalent) so the registry can inject the phase-1 handler before phase-2 resolution. Alternative: accept `?EntityAccessHandler $preliminary = null` as a fourth parameter to `resolveParameter()`. Choose the approach that keeps the interface clean and does not require adding it to `PolicyDependencyResolverInterface`.

**Validation**:
- [ ] Resolves an array-typed param to the entity-types array.
- [ ] Resolves a service-typed param via `KernelServicesInterface::get()`.
- [ ] Returns null for nullable unbound param.
- [ ] Returns default for defaulted unbound param.
- [ ] Throws `PolicyInstantiationException` for unresolvable required param.
- [ ] `composer phpstan` passes.

---

## Subtask T007 — Create `PolicyInstantiationException`

**Purpose**: Typed exception for boot-time policy instantiation failures; allows callers to catch specifically.

**File**: `packages/foundation/src/Kernel/Bootstrap/Exception/PolicyInstantiationException.php`

**Steps**:

1. Create the `Exception/` subdirectory if it does not exist.
2. Create the exception per `contracts/PolicyDependencyResolverInterface.md` §Exception:
```php
namespace Waaseyaa\Foundation\Kernel\Bootstrap\Exception;

final class PolicyInstantiationException extends \RuntimeException {}
```
3. Add `@api` PHPDoc — this is a public extension-point exception.

**Validation**:
- [ ] File exists with correct namespace.
- [ ] Extends `\RuntimeException`.
- [ ] `composer phpstan` passes.

---

## Subtask T008 — Rewrite `AccessPolicyRegistry::discover()` with two-phase resolver loop

**Purpose**: Replace the silent-skip heuristic with a fail-closed, container-resolved instantiation loop.

**File**: `packages/foundation/src/Kernel/Bootstrap/AccessPolicyRegistry.php`

**Steps**:

1. Add `PolicyDependencyResolverInterface` to the constructor:
```php
public function __construct(
    private readonly LoggerInterface $logger,
    private readonly PolicyDependencyResolverInterface $resolver,
) {}
```

2. Rewrite `discover()` with the two-phase algorithm:

```
Phase 1:
  $phase1Policies = []
  $deferred = []
  foreach $manifest->policies as $class => $entityTypes:
    validate class_exists($class)  // warning + skip still OK for missing autoload
    $ref = new ReflectionClass($class)
    $constructor = $ref->getConstructor()
    if constructor is null or has no params:
      $phase1Policies[] = new $class()
      continue
    // Check if any param requires EntityAccessHandler
    if anyParamRequires(EntityAccessHandler::class, $constructor):
      $deferred[] = [$class, $entityTypes]
      continue
    // Resolve all params via resolver
    $args = []
    foreach $constructor->getParameters() as $param:
      $args[] = $this->resolver->resolveParameter($class, $param, $entityTypes)
      // throws PolicyInstantiationException on failure — no catch here
    $phase1Policies[] = $ref->newInstanceArgs($args)

  $preliminaryHandler = new EntityAccessHandler($phase1Policies)

Phase 2:
  $allPolicies = $phase1Policies
  foreach $deferred as [$class, $entityTypes]:
    // Provide preliminary handler to resolver for EntityAccessHandler params
    $this->resolver->setPreliminaryHandler($preliminaryHandler)  // or equivalent
    $ref = new ReflectionClass($class)
    $args = []
    foreach $ref->getConstructor()->getParameters() as $param:
      $args[] = $this->resolver->resolveParameter($class, $param, $entityTypes)
    $allPolicies[] = $ref->newInstanceArgs($args)

  return new EntityAccessHandler($allPolicies)
```

3. Remove all `catch (\Throwable)` blocks that swallow instantiation errors. Only catch `\Throwable` to **re-throw** as `PolicyInstantiationException` with context.

4. Preserve the "class not found" warning + continue (this is an autoload race, not a policy error).

5. **Preserve backwards compatibility** for `ConfigEntityAccessPolicy` (array-first-param shape). The resolver's Rule 1 handles this transparently.

**Validation**:
- [ ] `ConfigEntityAccessPolicy` (array-first-param) still instantiates correctly.
- [ ] A policy with no constructor still instantiates.
- [ ] A policy with `EntityTypeManagerInterface` dependency instantiates via resolver.
- [ ] A policy with an unresolvable dep throws `PolicyInstantiationException`, does NOT log-and-continue.
- [ ] `composer phpstan` passes.

---

## Subtask T009 — Wire `KernelPolicyDependencyResolver` into `AbstractKernel`

**Purpose**: Pass the resolver to `AccessPolicyRegistry` so it can resolve service deps during discovery.

**File**: `packages/foundation/src/Kernel/AbstractKernel.php`

**Steps**:

1. Find `discoverAccessPolicies()` (approximately line 372):
```php
// Current:
return new AccessPolicyRegistry($this->logger)->discover($this->manifest);
```

2. Change to:
```php
$resolver = new KernelPolicyDependencyResolver($this->kernelServices);
return (new AccessPolicyRegistry($this->logger, $resolver))->discover($this->manifest);
```

3. Add the required `use` imports at the top of `AbstractKernel.php`.

4. Confirm `$this->kernelServices` is available at this call site (it should be — service providers have already registered).

**Validation**:
- [ ] Kernel boots without errors on a fresh checkout.
- [ ] `composer phpstan` passes.
- [ ] `./vendor/bin/phpunit --testsuite Integration` exits 0 (no regressions in existing integration tests).

---

## Subtask T010 — Write `AttachmentPolicyDiscoveryTest` (FR-011)

**Purpose**: Integration test proving `ParentDelegatedAccessPolicy` is auto-discovered and active for `attachment` entities at runtime without any manual registration (FR-011, SC-002).

**File**: `tests/Integration/Phase24/AttachmentPolicyDiscoveryTest.php`

**Namespace**: `Waaseyaa\Tests\Integration\Phase24`

**Steps**:

1. Boot the kernel with `DBALDatabase::createSqlite()`.
2. Call `$kernel->getEntityAccessHandler()` (or whatever the accessor is — check `AbstractKernel`).
3. Get the policy for entity type `attachment`:
```php
$handler = $kernel->getEntityAccessHandler();
// Use reflection or a dedicated accessor to retrieve the policy for 'attachment'
// EntityAccessHandler likely has a method like getPolicy(string $entityType) or similar
```
4. Assert: `$policy instanceof ParentDelegatedAccessPolicy`.
5. Assert: no `ServiceProvider::boot()` call was needed to register it — the test must NOT manually bind `ParentDelegatedAccessPolicy`.

**Validation**:
- [ ] Test passes after WP02's changes.
- [ ] Test fails on the pre-WP02 codebase (proving it is a genuine regression guard).
- [ ] `./vendor/bin/phpunit tests/Integration/Phase24/AttachmentPolicyDiscoveryTest.php` exits 0.

---

## Subtask T011 — Write `AccessPolicyRegistryTest` boot-failure unit test (FR-012)

**Purpose**: Unit test asserting that registering a `#[PolicyAttribute]` class with an unresolvable constructor dependency causes `PolicyInstantiationException` at boot, not a silent log (FR-012, SC-???).

**File**: `packages/foundation/tests/Unit/Kernel/Bootstrap/AccessPolicyRegistryTest.php`

**Steps**:

1. Create the test file.

2. Use an anonymous policy class with an unresolvable dependency:
```php
// In the test, create a manifest that includes a fake policy class FQCN
// The class has a constructor with an unresolvable required service param.
// Use a mock/stub KernelServicesInterface that returns null for everything.
```

3. Test structure:
```php
#[CoversClass(AccessPolicyRegistry::class)]
final class AccessPolicyRegistryTest extends TestCase
{
    #[Test]
    public function unresolvablePolicyDependencyThrowsAtBoot(): void
    {
        $resolver = new class implements PolicyDependencyResolverInterface {
            public function resolveParameter(string $policyClass, \ReflectionParameter $param, array $entityTypes): mixed
            {
                throw new PolicyInstantiationException("Cannot resolve {$param->getName()} for {$policyClass}");
            }
        };

        $registry = new AccessPolicyRegistry(new NullLogger(), $resolver);
        // Build a PackageManifest with a fake policy that has required constructor params
        // ...
        $this->expectException(PolicyInstantiationException::class);
        $registry->discover($manifest);
    }
}
```

4. Also add a positive test: a policy with no required params (or all resolvable params) instantiates successfully.

**Validation**:
- [ ] `unresolvablePolicyDependencyThrowsAtBoot` passes.
- [ ] `composer phpstan` passes.
- [ ] `./vendor/bin/phpunit packages/foundation/tests/Unit/Kernel/Bootstrap/AccessPolicyRegistryTest.php` exits 0.

---

## Definition of Done

- [ ] `PolicyDependencyResolverInterface`, `KernelPolicyDependencyResolver`, `PolicyInstantiationException` all exist.
- [ ] `AccessPolicyRegistry::discover()` uses the two-phase resolver algorithm.
- [ ] No `catch`-and-continue for instantiation failures; all failures throw `PolicyInstantiationException`.
- [ ] `ParentDelegatedAccessPolicy` is active without manual registration (`AttachmentPolicyDiscoveryTest` passes).
- [ ] Boot-failure test (`AccessPolicyRegistryTest`) passes.
- [ ] `ConfigEntityAccessPolicy` (array-first-param) unchanged and working.
- [ ] `composer verify` exits 0.
- [ ] `Closes #1519` in the PR description.

## Risks

| Risk | Mitigation |
|---|---|
| `EntityAccessHandler` forward-reference circular dep | Implement two-phase algorithm exactly per contracts/; unit-test phase-2 deferred resolution separately |
| `KernelServicesInterface::get()` unavailable during discover | Confirm service providers have all called `register()` before `discoverAccessPolicies()` runs in `AbstractKernel` |
| `PackageManifest` shape for test | Check existing tests or `PackageManifest` constructor for how to build a minimal manifest with policies |
| PHPStan level 5 — `mixed` return type | `resolveParameter()` returns `mixed`; ensure all callers handle narrowing correctly |

## Reviewer Guidance

1. Confirm `PolicyDependencyResolverInterface` has NO Symfony or PSR-11 imports (NFR-005).
2. Confirm `AccessPolicyRegistry::discover()` has NO `catch`-and-continue blocks that swallow instantiation errors.
3. Run `AttachmentPolicyDiscoveryTest` directly and confirm `ParentDelegatedAccessPolicy` is the returned policy.
4. Confirm `ConfigEntityAccessPolicy` tests still pass (backward compat).
5. The two-phase algorithm: check that phase-2 deferred policies actually receive the phase-1 `EntityAccessHandler`, not null.

## Activity Log

- 2026-05-20T23:56:11Z – claude:sonnet:implementer:implementer – shell_pid=677610 – Assigned agent via action command
- 2026-05-21T00:14:05Z – claude:sonnet:implementer:implementer – shell_pid=677610 – Container-resolved registry; ParentDelegatedAccessPolicy auto-instantiates via two-phase algorithm; boot fails on unresolvable deps; 5/5 tests pass, PHPStan clean
- 2026-05-21T00:14:56Z – claude:opus-4-7:reviewer:reviewer – shell_pid=696102 – Started review via action command
- 2026-05-21T00:23:01Z – claude:sonnet:implementer:implementer – shell_pid=702754 – Started implementation via action command
- 2026-05-21T00:37:59Z – claude:sonnet:implementer:implementer – shell_pid=702754 – Cycle 1: optional resolver param + in-place handler mutation; SurfaceMap fixed; SSR/OIDC failures pre-existing from cycle 0
- 2026-05-21T00:38:53Z – claude:opus-4-7:reviewer:reviewer – shell_pid=717401 – Started review via action command
- 2026-05-21T00:41:45Z – claude:opus-4-7:reviewer:reviewer – shell_pid=717401 – Moved to planned
