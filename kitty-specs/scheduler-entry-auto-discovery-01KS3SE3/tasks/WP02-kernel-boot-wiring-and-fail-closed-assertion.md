---
work_package_id: WP02
title: Kernel Boot Wiring + Fail-Closed Assertion
dependencies:
- WP01
requirement_refs:
- C-004
- FR-003
- FR-004
- FR-007
- FR-010
- FR-011
- NFR-002
- NFR-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T007
- T008
- T009
- T010
- T011
- T012
- T013
history:
- date: '2026-05-20T23:57:21Z'
  event: created
authoritative_surface: packages/foundation/src/Kernel/
execution_mode: code_change
owned_files:
- packages/foundation/src/Kernel/Bootstrap/ScheduleEntryRegistry.php
- packages/foundation/src/Kernel/Bootstrap/Exception/ScheduleEntryInstantiationException.php
- packages/foundation/src/Kernel/AbstractKernel.php
- packages/foundation/tests/Unit/Kernel/AbstractKernelTest.php
tags: []
---

# WP02 — Kernel Boot Wiring + Fail-Closed Assertion

## Objective

Introduce `ScheduleEntryRegistry` that enumerates the manifest's `schedule_entries`, resolves each class's constructor via the container-resolved resolver protocol (adopting M-B's resolver if available), calls `register()` on each, and throws a fail-closed exception on any unresolvable dependency. Wire it into `AbstractKernel::boot()`. Honor `schedule.disabled_entries` configuration.

**Requirement coverage**: FR-003, FR-004, FR-007, FR-010, FR-011, NFR-002, NFR-004, C-004, SC-004

## Context

### M-B Cross-Mission Dependency (CRITICAL — read first)

**Before writing a single line of code**, check whether M-B's resolver has landed:

```bash
test -f packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php && echo "M-B LANDED" || echo "M-B NOT YET"
```

**If M-B has landed**: Import `PolicyDependencyResolverInterface` directly. `ScheduleEntryRegistry` uses it identically to how `AccessPolicyRegistry` uses it.

**If M-B has NOT landed**: Introduce a parallel `ScheduleEntryDependencyResolverInterface` in the same `Bootstrap/` namespace with identical shape:
```php
namespace Waaseyaa\Foundation\Kernel\Bootstrap;

/** @api */
interface ScheduleEntryDependencyResolverInterface
{
    /**
     * @param list<string> $constructorParams  Parameter type names to resolve
     * @param list<string> $availableTypes     Context types available for injection
     * @return list<mixed>                     Resolved argument list
     */
    public function resolve(string $fqcn, array $constructorParams, array $availableTypes = []): array;
}
```
M-B's plan explicitly documents that it will adopt whichever resolver interface lands first.

### Existing boot sequence in `AbstractKernel`

`AbstractKernel::boot()` (line ~156) currently calls:
1. `discoverAndRegisterProviders()`
2. `bootProviders()`
3. `discoverAccessPolicies()`

M-D adds step 4: `bootScheduleEntries()` — runs after `discoverAccessPolicies()` so all bindings from providers are available in the container before schedule entries are resolved.

### `schedule.disabled_entries` configuration

Read from: `$this->config['schedule']['disabled_entries'] ?? []` — a `list<class-string>`. If a FQCN appears in this list, `ScheduleEntryRegistry` skips it silently (logs at `info` level), does not call `register()`, and marks it as `disabled` for `schedule:list` output.

### Fail-closed exception format (NFR-004)

The exception message must include:
- The FQCN of the schedule-entries class that failed
- The type name of the unresolvable dependency
- A reference to the documentation section explaining the resolver protocol

Example message format:
```
Failed to boot schedule entry 'Waaseyaa\Foo\Schedule\FooScheduleEntries':
  Cannot resolve constructor parameter '$service' of type 'Waaseyaa\Foo\FooService'.
  Ensure a service provider binds 'Waaseyaa\Foo\FooService' before kernel boot.
  See: docs/specs/operations-playbooks.md#schedule-entries
```

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Depends on**: WP01 (must be merged before this WP begins)
- **Execution**: `spec-kitty agent action implement WP02 --agent <name>`

## Subtask Guidance

### T007 — Check M-B resolver landing; adopt or introduce parallel resolver

**Purpose**: Determine which resolver interface to use and avoid duplicating code unnecessarily.

**Steps**:
1. Run: `test -f packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php && echo LANDED`
2. **If LANDED**: Note the interface FQCN; `ScheduleEntryRegistry` will `use` it directly.
3. **If NOT LANDED**: Create `packages/foundation/src/Kernel/Bootstrap/ScheduleEntryDependencyResolverInterface.php` with the shape shown in Context above. Mark `@api`. Also create `packages/foundation/src/Kernel/Bootstrap/KernelScheduleEntryDependencyResolver.php` implementing it (using constructor reflection + container lookup, identical logic to what M-B's `KernelPolicyDependencyResolver` would do).

**Resolver implementation sketch** (needed only if M-B not landed):
```php
final class KernelScheduleEntryDependencyResolver implements ScheduleEntryDependencyResolverInterface
{
    public function __construct(private readonly ContainerInterface $container) {}

    public function resolve(string $fqcn, array $constructorParams, array $availableTypes = []): array
    {
        $args = [];
        foreach ($constructorParams as $paramType) {
            if (!$this->container->has($paramType)) {
                throw new ScheduleEntryInstantiationException($fqcn, $paramType);
            }
            $args[] = $this->container->get($paramType);
        }
        return $args;
    }
}
```

**Validation**:
- Either `PolicyDependencyResolverInterface` or `ScheduleEntryDependencyResolverInterface` is available
- `@api` present on whichever interface is introduced (M-B may reference it)

---

### T008 — Create `ScheduleEntryRegistry`

**Purpose**: Central class that enumerates manifest entries, resolves constructors, calls `register()`, and honors disabled list.

**File**: `packages/foundation/src/Kernel/Bootstrap/ScheduleEntryRegistry.php` (new)

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap;

use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Kernel\Bootstrap\Exception\ScheduleEntryInstantiationException;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Scheduler\ScheduleInterface;

/**
 * Enumerates manifest schedule entries and registers them at kernel boot.
 * Fail-closed: any unresolvable dependency aborts boot.
 *
 * @internal
 */
final class ScheduleEntryRegistry
{
    // Use PolicyDependencyResolverInterface if M-B landed; otherwise ScheduleEntryDependencyResolverInterface
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PolicyDependencyResolverInterface $resolver,  // or ScheduleEntryDependencyResolverInterface
    ) {}

    public function boot(PackageManifest $manifest, ScheduleInterface $schedule, array $config): void
    {
        $disabledEntries = $config['schedule']['disabled_entries'] ?? [];

        foreach ($manifest->scheduleEntries as $fqcn) {
            if (in_array($fqcn, $disabledEntries, true)) {
                $this->logger->info('Schedule entry disabled by configuration', ['class' => $fqcn]);
                continue;
            }

            $instance = $this->resolve($fqcn);
            $instance->register($schedule);

            $this->logger->debug('Schedule entry registered', ['class' => $fqcn]);
        }
    }

    private function resolve(string $fqcn): object
    {
        try {
            $reflection = new \ReflectionClass($fqcn);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $reflection->newInstance();
            }

            $paramTypes = array_map(
                fn(\ReflectionParameter $p) => $p->getType()?->getName() ?? '',
                $constructor->getParameters()
            );

            // Filter out non-class types (string, int, array, etc.) — they must have defaults
            $resolvedArgs = $this->resolver->resolve($fqcn, array_filter($paramTypes));
            return $reflection->newInstanceArgs($resolvedArgs);
        } catch (ScheduleEntryInstantiationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ScheduleEntryInstantiationException::fromThrowable($fqcn, $e);
        }
    }
}
```

**Adaptation notes**:
- The `resolve()` method above is a sketch. Adapt to match M-B's `AccessPolicyRegistry::resolve()` pattern exactly — they should be nearly identical.
- Respect constructor parameters with default values (e.g. `array $config = []`) — do not try to resolve scalar defaults through the container.
- Log at `debug` for successful registrations; `info` for disabled entries. Use `LoggerInterface` (not `psr/log`).

**Validation**:
- `boot()` with an empty manifest is a no-op (no exception)
- `boot()` with a disabled entry skips instantiation entirely
- Fail-closed: exception propagates, does not get swallowed

---

### T009 — Create `ScheduleEntryInstantiationException`

**Purpose**: Provide a clear, actionable error message when a schedule entry cannot be resolved at boot (NFR-004).

**File**: `packages/foundation/src/Kernel/Bootstrap/Exception/ScheduleEntryInstantiationException.php` (new)

**Check first**: If M-B has landed and introduced a `PolicyInstantiationException` marked `@api`, check if it is general enough to be extended or reused. If so, extend it. Otherwise, create a sibling.

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when a ScheduleEntriesInterface implementation cannot be resolved at boot.
 * Boot is fail-closed — this exception is never swallowed.
 */
final class ScheduleEntryInstantiationException extends RuntimeException
{
    public static function fromUnresolvableDependency(string $fqcn, string $dependencyType): self
    {
        return new self(sprintf(
            "Failed to boot schedule entry '%s':\n" .
            "  Cannot resolve constructor parameter of type '%s'.\n" .
            "  Ensure a service provider binds '%s' before kernel boot.\n" .
            "  See: docs/specs/operations-playbooks.md#schedule-entries",
            $fqcn,
            $dependencyType,
            $dependencyType,
        ));
    }

    public static function fromThrowable(string $fqcn, Throwable $cause): self
    {
        return new self(sprintf(
            "Failed to boot schedule entry '%s': %s",
            $fqcn,
            $cause->getMessage(),
        ), 0, $cause);
    }
}
```

**Validation**:
- Exception message includes FQCN, dependency type, and doc link (NFR-004)
- `declare(strict_types=1)` present; `final class`

---

### T010 — Wire `bootScheduleEntries()` into `AbstractKernel::boot()`

**Purpose**: Activate the registry at kernel boot so schedule entries are registered on every request/command cycle.

**File**: `packages/foundation/src/Kernel/AbstractKernel.php` (edit)

**Steps**:
1. Add a `protected Schedule $schedule;` property (check if it already exists under a different name or is wired elsewhere in the kernel).
2. Add method:
   ```php
   protected function bootScheduleEntries(): void
   {
       $resolver = $this->container->has(PolicyDependencyResolverInterface::class)
           ? $this->container->get(PolicyDependencyResolverInterface::class)
           : new KernelScheduleEntryDependencyResolver($this->container);

       (new ScheduleEntryRegistry($this->logger, $resolver))
           ->boot($this->manifest, $this->schedule, $this->config);
   }
   ```
3. In `boot()`, after the `$this->discoverAccessPolicies()` call, add:
   ```php
   $this->bootScheduleEntries();
   ```

**Important**: Check how `$this->schedule` is initialized. If `Schedule` is not yet a property of `AbstractKernel`, verify where schedule state lives (it may be in a derived kernel or injected). Add `protected Schedule $schedule;` only if it's not present. Consult the existing `AbstractKernel` boot sequence carefully before editing.

**Validation**:
- `bootScheduleEntries()` is called exactly once in the boot sequence
- It runs after all providers have booted (container bindings are available)
- No regression to `discoverAccessPolicies()` or earlier boot steps

---

### T011 — Unit test `registersScheduleEntriesAtBoot` (FR-010)

**Purpose**: Verify the kernel's boot-time `register()` invocation for each manifest entry.

**File**: `packages/foundation/tests/Unit/Kernel/AbstractKernelTest.php` (add test method)

**Implementation approach**:
1. Create a minimal fixture `*ScheduleEntries` anonymous class in the test that records calls to `register()`.
2. Create a `PackageManifest` with `scheduleEntries: [FixtureScheduleEntries::class]`.
3. Boot a test kernel (or directly test `ScheduleEntryRegistry::boot()`).
4. Assert `register()` was called exactly once on the fixture.
5. Assert the return value is recorded.

Note: Testing `AbstractKernel` directly may require a concrete test-double subclass. Check existing `AbstractKernelTest` structure and follow the same pattern.

**Validation**:
- Test fails if `register()` is not called
- Test passes if `register()` is called exactly once per manifest entry
- Uses `#[Test]` and `#[CoversClass(ScheduleEntryRegistry::class)]` (or `AbstractKernel`)

---

### T012 — Unit test `failsBootOnUnresolvableScheduleEntry` (FR-011)

**Purpose**: Verify that kernel boot throws `ScheduleEntryInstantiationException` when a schedule entry has an unresolvable dependency.

**File**: `packages/foundation/tests/Unit/Kernel/AbstractKernelTest.php` (add test method)

**Implementation approach**:
1. Create a fixture `*ScheduleEntries` class whose constructor requires a non-existent binding.
2. Boot with a container that does not have that binding.
3. Assert `ScheduleEntryInstantiationException` is thrown.
4. Assert the exception message contains the FQCN of the schedule-entries class (NFR-004).

**Validation**:
- Test asserts exception type exactly (`ScheduleEntryInstantiationException`)
- Test asserts exception message contains the fixture class FQCN

---

### T013 — Unit test `skipsDisabledScheduleEntries` (SC-004)

**Purpose**: Verify that entries listed in `schedule.disabled_entries` are not instantiated.

**File**: `packages/foundation/tests/Unit/Kernel/AbstractKernelTest.php` (add test method)

**Implementation approach**:
1. Create two fixture schedule-entries classes.
2. Set `config['schedule']['disabled_entries']` to include one of them.
3. Boot.
4. Assert the non-disabled entry's `register()` was called.
5. Assert the disabled entry's `register()` was NOT called (use a spy/counter).

**Validation**:
- Test confirms skip (no instantiation) for disabled entries
- Test confirms non-disabled entries still register normally

## Definition of Done

- [ ] `ScheduleEntryRegistry` created with `boot()` handling disabled list, resolution, registration, and fail-closed throw
- [ ] `ScheduleEntryInstantiationException` created with FQCN + dep type + doc link in message
- [ ] `AbstractKernel::bootScheduleEntries()` calls registry after `discoverAccessPolicies()`
- [ ] Three unit tests pass: `registersScheduleEntriesAtBoot`, `failsBootOnUnresolvableScheduleEntry`, `skipsDisabledScheduleEntries`
- [ ] M-B resolver adoption documented in a comment within `ScheduleEntryRegistry` (which interface was adopted and why)
- [ ] `bin/check-package-layers` passes
- [ ] `composer verify` green

## Risks

| Risk | Mitigation |
|---|---|
| M-B not landed; parallel resolver needed | T007 gates the decision; parallel interface is structurally identical, trivial to introduce |
| `AbstractKernel` lacks a `$schedule` property | Investigate before editing; the Schedule object may be built differently |
| Container interface differs from expected | Check `packages/foundation/src/Container/` for the container type in use |
| `AbstractKernelTest` has complex setup | Follow existing test fixtures exactly; do not invent new test infrastructure |

## Reviewer Guidance

- Verify `bootScheduleEntries()` is called AFTER `bootProviders()` — container bindings must be live
- Confirm exception message format matches NFR-004 (FQCN + dep type + doc link)
- Check that disabled entries are truly skipped (not just not registered) — no instantiation should occur
- Verify M-B resolver adoption is consistent: either the interface is `PolicyDependencyResolverInterface` or the parallel `ScheduleEntryDependencyResolverInterface` — not both
