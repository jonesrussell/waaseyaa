# Implementation Plan: Access Fail-Closed Completeness (M-B)

**Branch**: `main` | **Date**: 2026-05-20 | **Mission**: `access-fail-closed-completeness-01KS3RJT`
**Spec**: `kitty-specs/access-fail-closed-completeness-01KS3RJT/spec.md`
**Predecessor**: `sql-entity-query-access-checking-01KRYP15` (all 5 WPs approved)
**Closes**: #1516, #1519, #1528, #1529 | **Retro-covers**: #1518, #1525, #1527

---

## Branch Contract

- **Current branch at plan start**: `main`
- **Planning/base branch**: `main`
- **Merge target**: `main`
- `branch_matches_target: true`

---

## Summary

The predecessor mission flipped `SqlEntityQuery::accessCheck()` to fail-closed (alpha.181). Three structural gaps surfaced post-merge:

1. `SearchRouter` constructs `SearchController` without threading the request account, leaking access-restricted rows (#1516).
2. `AccessPolicyRegistry::discover()` silently skips policies whose first constructor param is not `array`, meaning `ParentDelegatedAccessPolicy` and every future service-injected policy is dead on arrival (#1519).
3. No static CI guard exists for new unbound `getQuery()->...->execute()` callsites; three regressions shipped to production before triage (#1518, #1525, #1527).

This mission closes all three gaps with targeted edits to `SearchRouter`, a container-resolved `AccessPolicyRegistry`, the `RecordingEntityQuery` shared test helper, and a new `bin/check-getquery-bindings` CI gate.

---

## Technical Context

### Codebase snapshot (verified 2026-05-20)

| Symbol | Location | Current state |
|---|---|---|
| `SearchRouter::handle()` | `packages/foundation/src/Http/Router/SearchRouter.php` | Constructs `SearchController` without `account:` param |
| `SearchController::__construct()` | `packages/ai-vector/src/SearchController.php` | Already accepts `?AccountInterface $account = null` |
| `AccessPolicyRegistry::discover()` | `packages/foundation/src/Kernel/Bootstrap/AccessPolicyRegistry.php` | Has silent-skip heuristic for non-array first params |
| `AbstractKernel::discoverAccessPolicies()` | `packages/foundation/src/Kernel/AbstractKernel.php:372` | Calls `new AccessPolicyRegistry($this->logger)->discover($this->manifest)` after `bootProviders()` |
| `ParentDelegatedAccessPolicy` | `packages/attachment/src/Policy/ParentDelegatedAccessPolicy.php` | Has `#[PolicyAttribute]`, requires `EntityTypeManagerInterface` + `EntityAccessHandler` — currently dead |
| `EntityQueryInterface` | `packages/entity/src/Storage/EntityQueryInterface.php` | Stable; 7 methods including `setAccount()`, `accessCheck()`, `execute()` |
| `KernelServicesInterface::get()` | `packages/foundation/src/ServiceProvider/KernelServicesInterface.php` | Returns `?object` — the kernel resolver mechanism already exists |
| `ServiceProvider::resolve()` | `packages/foundation/src/ServiceProvider/ServiceProvider.php:113` | Uses `KernelServicesInterface::get()` internally; available to providers |
| `RecordingEntityQuery` testing dir | `packages/entity/testing/` | Exists (other helpers present); `autoload-dev` registered for `Waaseyaa\Entity\PhpStan\` and `Waaseyaa\Entity\Testing\Translation\` namespaces |
| Integration tests | `tests/Integration/Phase23/` | Latest phase; new tests land in `Phase24/` |
| `composer verify` | `composer.json` | Runs cs-check, phpstan, composer-policy, package-layers, no-secrets, ingestion-defaults, symfony-imports, dead-code, test suite |

### Key design constraint (NFR-005)

The container resolver protocol MUST NOT import any Symfony-specific container types in its **public signature**. `KernelServicesInterface` already satisfies this — it returns `?object` and is Waaseyaa-owned. The new `PolicyDependencyResolverInterface` wraps it with a typed, policy-focused contract: `resolve(string $abstract): object` (throws on unresolvable, never returns null). This is PSR-11-compatible in semantics (throws `NotFoundExceptionInterface`-equivalent) without importing PSR-11 in the interface signature.

### Layer placement

- `PolicyDependencyResolverInterface` → `packages/foundation/src/Kernel/Bootstrap/` (L0) — same package as `AccessPolicyRegistry`
- `KernelPolicyDependencyResolver` (implementation) → same package, wraps `KernelServicesInterface`
- `RecordingEntityQuery` → `packages/entity/testing/` (autoload-dev only, L1)
- `bin/check-getquery-bindings` → repo root `bin/` (no layer, CLI tool)

### WP01 gap analysis

`SearchController` already accepts `?AccountInterface $account = null`. The fix is a one-liner in `SearchRouter::handle()`:
```php
$account = $request->attributes->get('_account');
$searchController = new SearchController(
    entityTypeManager: $this->entityTypeManager,
    serializer: $serializer,
    embeddingStorage: $embeddingStorage,
    embeddingProvider: $embeddingProvider,
    accessHandler: $this->accessHandler,  // must also be threaded
    account: $account instanceof AccountInterface ? $account : null,
);
```
`SearchRouter` currently has no `EntityAccessHandler` dependency — it must receive one (optional, nullable) from the kernel or be wired differently. **Assumption A1**: `SearchRouter` gets `?EntityAccessHandler $accessHandler = null` added to its constructor. The kernel/service provider that registers `SearchRouter` threads the kernel's `EntityAccessHandler` in. This is additive and does not break C-005.

### WP02 resolver design

`AbstractKernel::discoverAccessPolicies()` (line 372-374) creates `AccessPolicyRegistry` with only `$this->logger`. After WP02 it will also pass a `PolicyDependencyResolverInterface`. The resolver is backed by a `KernelPolicyDependencyResolver` that wraps the kernel's own `KernelServicesInterface` implementation (already used by `ServiceProvider`). The `KernelServicesInterface::get()` call path exists and works — WP02 only needs to expose it to `AccessPolicyRegistry` through a typed contract.

**Circular-dependency guard**: If a policy depends on `EntityAccessHandler` (like `ParentDelegatedAccessPolicy` does), the `EntityAccessHandler` is built *from* the policies. Resolution order matters. The resolver must detect when `EntityAccessHandler` is requested during the discover pass and either:
- Provide a forward reference (deferred), or
- Fail with a precise error naming the cycle.

**Assumption A2**: `EntityAccessHandler` is not available from `KernelServicesInterface` during the `discoverAccessPolicies()` call (it is being built). A policy requiring it (like `ParentDelegatedAccessPolicy`) must receive a *proxy* or the registry must support a two-pass resolution for `EntityAccessHandler` specifically. The planner recommends a **forward-reference proxy** — `EntityAccessHandler` is passed as a lazy proxy to the policy; the proxy resolves on first `check()` call. **Flag for review**: This is an architectural nuance the spec does not fully specify (spec §Assumptions says "DI container is available at the point where discover() runs" but `EntityAccessHandler` is the object being assembled). WP02 must document the chosen approach and test it.

---

## Charter Check

Charter loaded: compact mode (software-dev-default, DDD paradigm, DIR-001/DIR-002/DIR-003). No conflicts found — all WPs follow domain-driven design, no cross-layer violations, no Symfony types in public interface surfaces.

---

## Gates

| Gate | Result | Notes |
|---|---|---|
| Layer discipline | PASS | Resolver interface L0, `RecordingEntityQuery` L1 autoload-dev |
| NFR-005 (no Symfony in public sig) | PASS | `PolicyDependencyResolverInterface` uses only `object` and `\Throwable` |
| C-002 (test helper not in `autoload`) | PASS | New namespace `Waaseyaa\Entity\Testing\` registered under `autoload-dev` only |
| C-005 (no existing API broken) | PASS | All changes additive; `ConfigEntityAccessPolicy` array-first-param path preserved |
| C-006 (no CI bypass) | PASS | Gate wired into existing `composer verify` |
| Circular dep flag | FLAGGED | `EntityAccessHandler` forward-reference in WP02 — see Assumption A2 |

---

## Work Package Outline

### WP01 — SearchRouter account threading

**Scope**: Thread `_account` from request through `SearchRouter::handle()` into `SearchController`.

**Files changed**:
- `packages/foundation/src/Http/Router/SearchRouter.php` — add `?EntityAccessHandler $accessHandler = null` constructor param; read `_account` from request attributes; pass both to `SearchController`.
- `tests/Integration/Phase24/SemanticSearchAccessTest.php` — new integration test (FR-010): two-user setup, asserts result sets differ by access-restricted rows.

**Closes**: #1516

**Dependencies**: None (WP01 is independent).

**Acceptance criteria**:
- `SemanticSearchAccessTest` passes.
- `SearchController` receives a non-null `$account` when an authenticated request arrives.
- `accessCheck(false)` is not called anywhere in the search path for an authenticated request.

---

### WP02 — Container-resolved AccessPolicyRegistry

**Scope**: Replace the constructor-heuristic skip with a container-resolved instantiation protocol. Add fail-closed boot assertion (FR-004). Add integration test (FR-011) and boot-failure unit test (FR-012).

**New files**:
- `packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php` — the named L0 resolver protocol (see contracts/).
- `packages/foundation/src/Kernel/Bootstrap/KernelPolicyDependencyResolver.php` — wraps `KernelServicesInterface`; implements `PolicyDependencyResolverInterface`.
- `packages/foundation/src/Kernel/Bootstrap/Exception/PolicyInstantiationException.php` — thrown when a policy's dependencies cannot be resolved (FR-004).

**Files changed**:
- `packages/foundation/src/Kernel/Bootstrap/AccessPolicyRegistry.php` — accept `PolicyDependencyResolverInterface` in constructor; replace heuristic with reflection-driven resolution loop using the resolver; throw `PolicyInstantiationException` on failure (no silent log).
- `packages/foundation/src/Kernel/AbstractKernel.php` — wire `KernelPolicyDependencyResolver` into `AccessPolicyRegistry`.
- `tests/Integration/Phase24/AttachmentPolicyDiscoveryTest.php` — asserts `ParentDelegatedAccessPolicy` is active for `attachment` entities (FR-011).
- `packages/foundation/tests/Unit/Kernel/Bootstrap/AccessPolicyRegistryTest.php` — asserts unresolvable dependency throws `PolicyInstantiationException` at boot (FR-012).
- `docs/specs/access-control.md` — add registry resolver contract section (WP05 also touches this).

**Architectural note**: WP02 must resolve the `EntityAccessHandler` forward-reference cycle (Assumption A2). See `research.md` §Circular-dependency resolution pattern.

**Closes**: #1519

**Dependencies**: None (WP02 is independent of WP01 and WP03).

**Acceptance criteria**:
- `AttachmentPolicyDiscoveryTest` passes without any manual `ServiceProvider::boot()` registration.
- A `#[PolicyAttribute]` class with an unresolvable dependency throws `PolicyInstantiationException` at boot.
- `ConfigEntityAccessPolicy` (array-first-param shape) continues to work unmodified.

---

### WP03 — RecordingEntityQuery test helper

**Scope**: Implement `RecordingEntityQuery` in `packages/entity/testing/`; wire `autoload-dev`; migrate two existing inline stubs.

**New files**:
- `packages/entity/testing/RecordingEntityQuery.php` — implements `EntityQueryInterface`; records `accessCheck()` calls into `list<bool> $accessChecks`; stubs all other methods to return `$this` or `[]`.

**Files changed**:
- `packages/entity/composer.json` — add `"Waaseyaa\\Entity\\Testing\\": "testing/"` under `autoload-dev` (alongside existing `PhpStan` and `Translation` entries).
- Two existing test files that use inline `EntityQueryInterface` anonymous stubs — migrate to `RecordingEntityQuery`.

**Closes**: #1529

**Dependencies**: None (standalone).

**Acceptance criteria**:
- `RecordingEntityQuery` does not appear in `autoload` (production classmap).
- Double-calling `accessCheck(true)->accessCheck(false)` records `[true, false]`.
- Existing tests that used inline stubs pass unchanged after migration.

---

### WP04 — bin/check-getquery-bindings + baseline

**Scope**: Implement the PHP CLI scanner. Generate initial baseline. Wire into `composer verify`. Write self-test (SC-003). Document in `CLAUDE.md`.

**New files**:
- `bin/check-getquery-bindings` — PHP CLI script; scans `packages/*/src/**/*.php`; detects `getQuery()->...->execute()` chains without `setAccount()` or `accessCheck(false)` in the chain; baseline-mode (generates `tools/getquery-bindings-baseline.txt` on first run); exits non-zero on new offenders.
- `tools/getquery-bindings-baseline.txt` — initial baseline (populated by running the script on the current codebase after WP01–WP03 land; sorted path-then-line; inline exemption comments where applicable).
- `tests/Integration/Phase24/GetQueryBindingsGateTest.php` — feeds a synthetic fixture file with an unbound callsite; asserts script exits non-zero (SC-003).

**Files changed**:
- `composer.json` — add `"check-getquery-bindings": "php bin/check-getquery-bindings"` and add `"@check-getquery-bindings"` to the `verify` array.
- `CLAUDE.md` — add "CI gates" section documenting `bin/check-getquery-bindings` and `tools/getquery-bindings-baseline.txt`.

**Closes**: #1528

**Dependencies**: WP01 and WP02 should land first so the baseline reflects the post-fix codebase (otherwise WP01/WP02's fixed callsites appear in the baseline unnecessarily). WP04 can proceed in parallel but the baseline file must be regenerated after WP01–WP02 merge.

**Acceptance criteria**:
- `bin/check-getquery-bindings` completes under 30 s on current repo (NFR-001).
- `GetQueryBindingsGateTest` passes.
- `composer verify` is green with the gate wired.
- Baseline file is plain text, sorted, human-readable.

---

### WP05 — Retro regression tests + M-B.1 issue + wrap-up

**Scope**: Add four regression tests for the previously fixed callsites. File the M-B.1 tracking issue. CHANGELOG entry. Final spec update.

**New files**:
- `packages/path/tests/Unit/PathAliasResolverBindingTest.php` — asserts `PathAliasResolver::resolve()` binds account or explicitly calls `accessCheck(false)` with documented intent (FR-009, retro #1518).
- `packages/user/tests/Unit/AuthControllerFindUserByNameBindingTest.php` — same pattern for `AuthController::findUserByName()` (FR-009, retro #1525).
- `packages/seo/tests/Unit/SitemapGeneratorBindingTest.php` — for `SitemapGenerator::collectFromEntityTypes()` (FR-009, retro #1527).
- `packages/user/tests/Unit/UserBlockServiceBindingTest.php` — for `UserBlockService::isBlocked()` (FR-009, retro #1527).

All four tests use `RecordingEntityQuery` from WP03.

**Files changed**:
- `CHANGELOG.md` — `[Unreleased]` entry covering all five WPs.
- `docs/specs/access-control.md` — finalize registry resolver contract documentation.

**Actions** (not code):
- File GitHub issue: *"M-B.1: drive `getquery-bindings-baseline.txt` to zero"* — link to mission PR.
- Mission merge PR description: `Closes #1516, #1519, #1528, #1529` in footer; reference M-B.1 issue number.

**Dependencies**: WP03 must be merged first (regression tests depend on `RecordingEntityQuery`).

**Acceptance criteria**:
- All four regression tests pass.
- M-B.1 issue exists and is linked from the merge PR.
- `composer verify` green on final merge commit.
- `docs/specs/access-control.md` updated.

---

## Architectural Assumptions (flagged for review)

| ID | Assumption | Impact if wrong | WP |
|---|---|---|---|
| A1 | `SearchRouter` gets `?EntityAccessHandler $accessHandler = null` in constructor; kernel threads the global `EntityAccessHandler` in when registering the router | If the router isn't registered through a service provider with access to `EntityAccessHandler`, a different wiring point must be found | WP01 |
| A2 | `EntityAccessHandler` is not yet available when `discoverAccessPolicies()` runs; `ParentDelegatedAccessPolicy`'s `EntityAccessHandler` dependency requires a lazy forward-reference proxy | If the kernel can provide `EntityAccessHandler` early (e.g. an empty shell that is mutated after discover), the proxy is unnecessary complexity | WP02 |
| A3 | `AuthController` lives in `packages/auth/` or `packages/user/` and uses `EntityQueryInterface` directly (not via a storage abstraction) — confirming the right test location for WP05 | If `AuthController` is in a different package, the WP05 test file path changes | WP05 |
| A4 | The `bin/check-getquery-bindings` script can be a pure-PHP AST/regex scanner (no external deps) and completes under 30 s on ~62 packages | If the codebase is too large for a regex scan, an AST-based approach (nikic/php-parser) may be needed | WP04 |

---

## File Map (new files only)

```
packages/
  foundation/
    src/Kernel/Bootstrap/
      PolicyDependencyResolverInterface.php        [WP02 — new L0 interface]
      KernelPolicyDependencyResolver.php           [WP02 — implementation]
      Exception/
        PolicyInstantiationException.php           [WP02 — boot-fail exception]
    tests/Unit/Kernel/Bootstrap/
      AccessPolicyRegistryTest.php                 [WP02 — boot-failure test]
  entity/
    testing/
      RecordingEntityQuery.php                     [WP03 — autoload-dev helper]
  path/
    tests/Unit/
      PathAliasResolverBindingTest.php             [WP05 — retro #1518]
  user/
    tests/Unit/
      AuthControllerFindUserByNameBindingTest.php  [WP05 — retro #1525]
      UserBlockServiceBindingTest.php              [WP05 — retro #1527]
  seo/
    tests/Unit/
      SitemapGeneratorBindingTest.php              [WP05 — retro #1527]

tests/Integration/Phase24/
  SemanticSearchAccessTest.php                     [WP01 — FR-010]
  AttachmentPolicyDiscoveryTest.php                [WP02 — FR-011]
  GetQueryBindingsGateTest.php                     [WP04 — SC-003]

bin/
  check-getquery-bindings                          [WP04 — PHP CLI script]

tools/
  getquery-bindings-baseline.txt                   [WP04 — initial baseline]
```

---

## WP Dependency Graph

```
WP01 ─────────────────────────────────────────────────────── WP04 (baseline needs fixed codebase)
WP02 ─────────────────────────────────────────────────────── WP04 (baseline needs fixed codebase)
WP03 ─────────────────────────────────────────────────────── WP05 (retro tests use RecordingEntityQuery)
WP01, WP02, WP03 (independent, parallelizable)
WP04 (after WP01 + WP02 preferred)
WP05 (after WP03)
```

---

## Research Commissioned

See `research.md` for resolved decisions on:
- Circular dependency resolution pattern for `EntityAccessHandler` (A2)
- `PolicyDependencyResolverInterface` signature design
- `bin/check-getquery-bindings` scanner approach (regex vs AST)
- `RecordingEntityQuery` method-chain stub completeness

---

## Next Step

Run `/spec-kitty.tasks` to generate work package files from this plan.
