---
work_package_id: WP04
title: 'F1: Entity deep-link route helper'
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-016
- NFR-005
- NFR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T015
- T016
- T017
- T018
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "9932"
history:
- date: '2026-04-27'
  note: Generated from plan.md + research.md Q3 + contracts/.
authoritative_surface: packages/routing/src/EntityDeepLinkRouteBuilder.php
execution_mode: code_change
mission_id: 01KQ7M1PHWD8QAQPJC91RAVE0T
mission_slug: single-entity-work-surface-01KQ7M1P
owned_files:
- packages/routing/src/EntityDeepLinkRouteBuilder.php
- packages/routing/tests/Unit/EntityDeepLinkRouteBuilderTest.php
- packages/routing/tests/Integration/EntityDeepLinkResolutionTest.php
tags: []
---

# WP04 — F1: Entity deep-link route helper

## Objective

Add `Waaseyaa\Routing\EntityDeepLinkRouteBuilder`: a small helper that composes the existing `RouteBuilder::create()` + `entityParameter()` to register `/<segment>/<entity_type>/{id}` routes with one declarative call. Consumers chain `controller(...)` and any other `RouteBuilder` methods (`methods`, access options, etc.) before `build()`.

Per research.md Q3 we **compose** rather than extend `RouteBuilder` (which is `final` with private constructor). No edits to existing routing classes.

## Context (read first)

- **spec.md** FR-001, FR-002 — exact behavioral contract.
- **research.md** Q3 — composition decision and rationale.
- **contracts/README.md** F1 — acceptance criteria.
- **`packages/routing/src/RouteBuilder.php`** — existing fluent API. Reuse `entityParameter()` (verified at ~line 56).
- **`packages/routing/src/ParamConverter/EntityParamConverter.php`** — already handles `'parameters' => ['id' => ['type' => 'entity:<type>']]` route options. F1 wires the option; the existing converter resolves the entity at request time.

## Branch Strategy

- **Planning base**: `main`
- **Final merge target**: `main`
- Lane via `finalize-tasks`. Use `spec-kitty agent action implement WP04 --agent <name> --mission single-entity-work-surface-01KQ7M1P`.

## Subtasks

### T015 — `EntityDeepLinkRouteBuilder` class

**File**: `packages/routing/src/EntityDeepLinkRouteBuilder.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Routing;

/**
 * Convenience helper for routes of the shape `<segment>/<entity_type>/{id}`.
 *
 * Composes RouteBuilder; the returned builder is fully chainable so consumers
 * can attach controller, methods, access options, etc. before build().
 *
 * Example:
 *   $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
 *       ->controller(EditWorkspaceController::class . '::view')
 *       ->methods('GET')
 *       ->build();
 *   $router->addRoute('app.edit_node', $route);
 */
final readonly class EntityDeepLinkRouteBuilder
{
    public function __construct(
        public string $segment,
        public string $entityTypeId,
    ) {}

    public static function for(string $segment, string $entityTypeId): self
    {
        return new self($segment, $entityTypeId);
    }

    /**
     * Begin building the route by setting the controller. Returns a chainable
     * RouteBuilder so the caller can configure additional options.
     *
     * @param string|callable $controller
     */
    public function controller(string|callable $controller): RouteBuilder
    {
        $path = rtrim($this->segment, '/') . '/' . $this->entityTypeId . '/{id}';
        return RouteBuilder::create($path)
            ->controller($controller)
            ->entityParameter('id', $this->entityTypeId)
            ->methods('GET');
    }
}
```

**Notes**:
- Path normalization: trim trailing slashes from segment to avoid `//`.
- Default method is GET; consumer can override with `->methods('POST')` etc. before `build()` since `RouteBuilder::methods()` is a setter.
- `entityParameter` sets the `parameters` route option recognized by the existing `EntityParamConverter`.

### T016 — Unit test

**File**: `packages/routing/tests/Unit/EntityDeepLinkRouteBuilderTest.php`

**Cases**:
- `for('/edit', 'node')->controller('App\Foo::view')->build()` produces a Symfony `Route`:
  - Path: `/edit/node/{id}`
  - Methods: `['GET']`
  - Defaults `_controller`: `'App\Foo::view'`
  - Options `parameters`: `['id' => ['type' => 'entity:node']]`
- Segment with trailing slash (`'/edit/'`) normalizes to no double-slash: path `/edit/node/{id}`.
- Method override: `->methods('POST')` after `controller(...)` sets methods to `['POST']`.
- `for('/profile/edit', 'user')` produces path `/profile/edit/user/{id}`.

### T017 — Integration test

**File**: `packages/routing/tests/Integration/EntityDeepLinkResolutionTest.php`

**Setup**: minimal kernel boot with:
- `WaaseyaaRouter` + `EntityParamConverter` registered.
- `EntityRepositoryInterface` backed by `InMemoryEntityStorage` or `DBALDatabase::createSqlite()` with one `node` entity (id=1).
- A controller class that records the entity it received.
- An `AccessPolicyInterface` for `node` that allows view for a fixture account.

**Cases**:
- GET `/edit/node/1` invokes the controller with the hydrated `Node` entity (id=1).
- GET `/edit/node/999` returns 404; controller is **not** invoked.
- GET `/edit/node/1` with denied access policy returns 403; controller is **not** invoked.
- GET `/edit/node/abc` (non-numeric id; assumes integer-id entity type) returns 404 cleanly.

If the test infrastructure for booting a router + access pipeline doesn't exist yet, leverage `tests/Integration/PhaseN/` patterns from existing integration tests for reference.

### T018 — Author-side docstring example

**Steps**:

1. Add the example block from T015's class PHPDoc (already present above).
2. Cross-link from `packages/routing/src/RouteBuilder.php` PHPDoc to `EntityDeepLinkRouteBuilder` ("see also") so a reader of `RouteBuilder` discovers the helper.

**No code change** for cross-link — just a one-line PHPDoc addition. Adjust `owned_files` if `RouteBuilder.php` is touched: it's not in the WP04 owned_files list, so **do not edit** `RouteBuilder.php`. Skip the cross-link if it would require editing a non-owned file. The class-level PHPDoc on `EntityDeepLinkRouteBuilder` is sufficient discovery surface.

## Definition of Done

- [ ] `EntityDeepLinkRouteBuilder` class exists, composes `RouteBuilder`, returns a chainable `RouteBuilder` from `controller()`.
- [ ] Class is `final readonly`; constructor properties are public readonly.
- [ ] `for()` static factory and `controller()` instance method present per the contract.
- [ ] Unit test covers path construction, methods, parameter resolver wiring, segment trimming.
- [ ] Integration test covers happy path, 404, 403, malformed id.
- [ ] PHPDoc on the class includes a runnable example.
- [ ] No edits to `RouteBuilder.php`, `WaaseyaaRouter.php`, or `EntityParamConverter.php`.
- [ ] `composer phpstan`, `composer cs-check`, PHPUnit pass.
- [ ] No code changes outside `owned_files`.

## Risks

| Risk | Mitigation |
|---|---|
| `RouteBuilder::entityParameter()` signature differs from expected | Read the actual method before implementing; adjust the call accordingly. The contract is "set up an entity-resolving route option for parameter `id` against entity type `X`". Whatever method does that is the right call. |
| Integration test bootstraps too much kernel | Use `InMemoryEntityStorage` and skip `DBALDatabase` if entity-resolution path doesn't require persistence. The test exercises the routing → param converter → controller chain, not storage. |
| `methods('GET')` being default surprises consumers wanting POST | Documented in PHPDoc. Consumers chain `->methods('POST')` to override. Test covers the override case. |

## Reviewer guidance

- Verify the helper does **not** subclass or modify `RouteBuilder` — composition only. Reject if composition is replaced with inheritance.
- Verify the returned `RouteBuilder` is fully chainable (test asserts `->methods()`, `->controller()` work after the helper hands off).
- Verify the integration test exercises the entity param converter, not just the path matcher.
- Confirm no CHANGELOG edit (deferred to WP10).

## Implementation command

```bash
spec-kitty agent action implement WP04 --agent <agent-name> --mission single-entity-work-surface-01KQ7M1P
```

No dependencies — independent of WP01-WP03.

## Activity Log

- 2026-04-27T16:20:14Z – claude:sonnet-4-6:implementer:implementer – shell_pid=32380 – Started implementation via action command
- 2026-04-27T16:24:08Z – claude:sonnet-4-6:implementer:implementer – shell_pid=32380 – F1 deep-link helper; composes RouteBuilder; tests + phpstan + cs all pass
- 2026-04-27T16:24:32Z – claude:opus-4-7:reviewer:reviewer – shell_pid=9932 – Started review via action command
- 2026-04-27T16:26:22Z – claude:opus-4-7:reviewer:reviewer – shell_pid=9932 – Review passed: final readonly class composes RouteBuilder cleanly; 14/16 tests pass with 2 skipped explicitly deferring to WP10 E2E (kernel-boot dependent assertions). PHPStan L5 clean, CS clean, layer check OK, only the 3 owned files touched.
- 2026-04-27T18:21:52Z – claude:opus-4-7:reviewer:reviewer – shell_pid=9932 – Done override: Mission merged at ca0ff03
