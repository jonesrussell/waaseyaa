# Tasks: Access Fail-Closed Completeness (M-B)

**Mission**: `access-fail-closed-completeness-01KS3RJT`
**Generated**: 2026-05-20T23:30:18Z
**Branch**: `main` → `main`
**Closes**: #1516, #1519, #1528, #1529
**Retro-covers (regression tests)**: #1518, #1525, #1527

---

## Subtask Index

| ID | Description | Parallel |
|---|---|---|
| T001 | Add `?EntityAccessHandler $accessHandler` param to `SearchRouter` constructor | — | [D] |
| T002 | Thread `_account` from request attributes into `SearchController` in `SearchRouter::handle()` | — | [D] |
| T003 | Verify kernel/service-provider wires `EntityAccessHandler` into `SearchRouter` at registration time | — | [D] |
| T004 | Write `SemanticSearchAccessTest` (FR-010): two-user kernel integration test | — | [D] |
| T005 | Create `PolicyDependencyResolverInterface` in `packages/foundation/src/Kernel/Bootstrap/` | — |
| T006 | Create `KernelPolicyDependencyResolver` implementing the interface | — |
| T007 | Create `PolicyInstantiationException` in `packages/foundation/src/Kernel/Bootstrap/Exception/` | — |
| T008 | Rewrite `AccessPolicyRegistry::discover()` with two-phase container-resolved loop | — |
| T009 | Wire `KernelPolicyDependencyResolver` into `AbstractKernel::discoverAccessPolicies()` | — |
| T010 | Write `AttachmentPolicyDiscoveryTest` (FR-011): `ParentDelegatedAccessPolicy` active for `attachment` | — |
| T011 | Write `AccessPolicyRegistryTest` boot-failure unit test (FR-012) | — |
| T012 | Create `packages/entity/testing/RecordingEntityQuery.php` implementing `EntityQueryInterface` | [D] |
| T013 | Add `Waaseyaa\Entity\Testing` PSR-4 entry to `packages/entity/composer.json` `autoload-dev` | [D] |
| T014 | Identify and migrate existing inline `EntityQueryInterface` anonymous stubs | [D] |
| T015 | Write `bin/check-getquery-bindings` PHP CLI scanner | — | [D] |
| T016 | Run `--generate-baseline`; commit `tools/getquery-bindings-baseline.txt` | — | [D] |
| T017 | Wire `@check-getquery-bindings` into `composer.json` verify array | — | [D] |
| T018 | Write `GetQueryBindingsGateTest` (SC-003) | — | [D] |
| T019 | Add "CI gates" section to `CLAUDE.md` | — | [D] |
| T020 | Write `PathAliasResolverBindingTest` (FR-009 / retro #1518) | [P] |
| T021 | Write `AuthControllerFindUserByNameBindingTest` (FR-009 / retro #1525) | [P] |
| T022 | Write `SitemapGeneratorBindingTest` (FR-009 / retro #1527) | [P] |
| T023 | Write `UserBlockServiceBindingTest` (FR-009 / retro #1527) | [P] |
| T024 | Add `[Unreleased]` CHANGELOG entry | — |
| T025 | Finalize `docs/specs/access-control.md` with registry resolver contract section | — |
| T026 | File GitHub issue "M-B.1: drive getquery-bindings-baseline.txt to zero" | — |

---

## Work Packages

---

### WP01 — SearchRouter account threading

**Goal**: Thread `_account` from the request through `SearchRouter::handle()` into `SearchController`, closing the semantic-search access leak (#1516).

**Priority**: High — closes a live data-leak.
**Estimated prompt size**: ~320 lines
**Independent test**: `SemanticSearchAccessTest` passes with two-user setup.
**Dependencies**: None
**Prompt file**: `tasks/WP01-search-router-account-threading.md`

**Included subtasks**:

- [x] T001 Add `?EntityAccessHandler $accessHandler = null` param to `SearchRouter` constructor
- [x] T002 Thread `_account` from request attributes into `SearchController` in `SearchRouter::handle()`
- [x] T003 Verify kernel/service-provider wires `EntityAccessHandler` into `SearchRouter` at registration time
- [x] T004 Write `SemanticSearchAccessTest` (FR-010): two-user kernel integration test

**Implementation sketch**:
1. Add `?EntityAccessHandler $accessHandler = null` to `SearchRouter::__construct()`.
2. In `SearchRouter::handle()`, read `$account = $request->attributes->get('_account')` using `instanceof AccountInterface` guard.
3. Pass `account:` and `accessHandler:` to `new SearchController(...)`.
4. Locate where `SearchRouter` is constructed in the kernel/service-provider; thread `EntityAccessHandler` in.
5. Write integration test: boot kernel, create two accounts with different policies, assert search returns different row sets.

**Risks**:
- `SearchRouter` is currently in `packages/foundation/` (L0) but `EntityAccessHandler` is in `packages/access/` (L1). The import already exists in `AccessPolicyRegistry`, so L0 to L1 dep is established. Confirm no new layer violation before committing.
- The kernel/service provider wiring may require changes beyond `SearchRouter.php`. Investigate `FoundationServiceProvider` or equivalent.

---

### WP02 — Container-resolved AccessPolicyRegistry

**Goal**: Replace the silent-skip constructor heuristic in `AccessPolicyRegistry` with a container-resolved protocol; make boot fail loudly on unresolvable policy dependencies (#1519).

**Priority**: High — `ParentDelegatedAccessPolicy` is currently dead code.
**Estimated prompt size**: ~430 lines
**Independent test**: `AttachmentPolicyDiscoveryTest` and `AccessPolicyRegistryTest` pass.
**Dependencies**: None
**Prompt file**: `tasks/WP02-container-resolved-access-policy-registry.md`

**Included subtasks**:

- [ ] T005 Create `PolicyDependencyResolverInterface` in `packages/foundation/src/Kernel/Bootstrap/`
- [ ] T006 Create `KernelPolicyDependencyResolver` implementing the interface
- [ ] T007 Create `PolicyInstantiationException` in `packages/foundation/src/Kernel/Bootstrap/Exception/`
- [ ] T008 Rewrite `AccessPolicyRegistry::discover()` with two-phase container-resolved loop
- [ ] T009 Wire `KernelPolicyDependencyResolver` into `AbstractKernel::discoverAccessPolicies()`
- [ ] T010 Write `AttachmentPolicyDiscoveryTest` (FR-011)
- [ ] T011 Write `AccessPolicyRegistryTest` boot-failure unit test (FR-012)

**Implementation sketch**:
1. Create the three new files per the contracts/ spec.
2. Rewrite `AccessPolicyRegistry::discover()` using two-phase algorithm: phase 1 instantiates policies not depending on `EntityAccessHandler`; phase 2 instantiates deferred policies using the phase-1 handler.
3. Replace the bare `new AccessPolicyRegistry($this->logger)` call in `AbstractKernel` with the resolver-injected version.
4. Write integration test booting kernel, asserting the attachment policy returns `ParentDelegatedAccessPolicy`.
5. Write unit test for boot-fail: register a policy with unresolvable dep, assert `PolicyInstantiationException`.

**Parallel opportunities**: T005, T006, T007 can be written in parallel (new files, no cross-dep). T008, T009 depend on T005 through T007 existing.

**Risks**:
- The two-phase resolution pattern for the `EntityAccessHandler` circular dep (Assumption A2) is nuanced. See contracts/PolicyDependencyResolverInterface.md.
- `KernelServicesInterface::get()` must be available during `discoverAccessPolicies()`. Confirm ordering in `AbstractKernel`.

---

### WP03 — RecordingEntityQuery test helper

**Goal**: Extract the shared `RecordingEntityQuery` stub into `packages/entity/testing/`; wire `autoload-dev`; migrate any existing inline `EntityQueryInterface` stubs (#1529).

**Priority**: High — the retro regression tests in the wrap-up package depend on this helper.
**Estimated prompt size**: ~260 lines
**Independent test**: `RecordingEntityQuery` instantiates correctly; existing tests that migrated away from inline stubs still pass.
**Dependencies**: None
**Prompt file**: `tasks/WP03-recording-entity-query-test-helper.md`

**Included subtasks**:

- [x] T012 Create `packages/entity/testing/RecordingEntityQuery.php` implementing `EntityQueryInterface`
- [x] T013 Add `"Waaseyaa\\Entity\\Testing\\": "testing/"` to `packages/entity/composer.json` `autoload-dev`
- [x] T014 Identify and migrate existing inline `EntityQueryInterface` anonymous stubs

**Implementation sketch**:
1. Create `packages/entity/testing/RecordingEntityQuery.php` exactly per the contract in contracts/RecordingEntityQuery-contract.md.
2. Edit `packages/entity/composer.json`: add the `autoload-dev` PSR-4 entry (preserve existing `PhpStan` and `Translation` entries).
3. Grep for `implements EntityQueryInterface` or `EntityQueryInterface` anonymous stubs in `packages/*/tests/`; migrate to `new RecordingEntityQuery()`.
4. Run `./vendor/bin/phpunit packages/entity/tests/` and relevant package tests to confirm no regressions.

**Parallel opportunities**: T012 and T013 are fully parallel. T014 depends on T012 existing.

**Risks**:
- PSR-4 sub-namespace ordering: the new `Waaseyaa\\Entity\\Testing\\` root must be listed before the existing `Waaseyaa\\Entity\\Testing\\Translation\\` entry. PSR-4 more-specific prefix wins regardless of order, but the composer policy check requires sorted keys.
- Must confirm `RecordingEntityQuery` never appears under `autoload` (production classmap).

---

### WP04 — bin/check-getquery-bindings + baseline

**Goal**: Implement the PHP CI scanner for unbound `getQuery()->...->execute()` callsites; generate initial baseline; wire into `composer verify`; document in `CLAUDE.md` (#1528).

**Priority**: High — closes the CI guard gap that let three bugs ship.
**Estimated prompt size**: ~380 lines
**Independent test**: `GetQueryBindingsGateTest` passes; `composer verify` green with gate wired.
**Dependencies**: None
**Prompt file**: `tasks/WP04-check-getquery-bindings-gate.md`

**Included subtasks**:

- [x] T015 Write `bin/check-getquery-bindings` PHP CLI scanner
- [x] T016 Run `--generate-baseline`; commit `tools/getquery-bindings-baseline.txt`
- [x] T017 Wire `@check-getquery-bindings` into `composer.json` verify array
- [x] T018 Write `GetQueryBindingsGateTest` (SC-003)
- [x] T019 Add "CI gates" section to `CLAUDE.md`

**Implementation sketch**:
1. Write `bin/check-getquery-bindings` as a PHP CLI script using `RecursiveDirectoryIterator` to scan `packages/*/src/**/*.php`. Use a sliding-window regex to detect `getQuery()...->execute()` chains missing `setAccount(` or `->accessCheck(false)`. Support `--generate-baseline` and `--verify` flags. Sort output by path then line.
2. Run `php bin/check-getquery-bindings --generate-baseline` on the current codebase; commit `tools/getquery-bindings-baseline.txt`.
3. Add the script alias and wire it into the `verify` array in `composer.json` per contracts/getquery-bindings-baseline-format.md.
4. Write `GetQueryBindingsGateTest`: create a temp PHP fixture file with an unbound callsite; assert the script exits non-zero.
5. Update `CLAUDE.md` with a "CI gates" section (C-003).

**Note on baseline timing**: The baseline is most accurate when generated after the search router and policy registry fixes land. If this package is implemented before those fixes, the baseline must be regenerated after those packages merge.

**Risks**:
- Regex multi-line chain detection is fragile. Use sliding window of 15 lines; document known limitation.
- Baseline entries must all have inline comments; entries without comments cause CI to fail.

---

### WP05 — Retro regression tests + M-B.1 issue + wrap-up

**Goal**: Add four regression tests for the previously fixed callsites; file M-B.1 tracking issue; update CHANGELOG and `docs/specs/access-control.md`.

**Priority**: High — closes the retro coverage gap; required for merge.
**Estimated prompt size**: ~350 lines
**Independent test**: All four regression tests pass. `composer verify` green on final merge commit.
**Dependencies**: WP03, WP04
**Prompt file**: `tasks/WP05-retro-regression-tests-and-wrapup.md`

**Included subtasks**:

- [ ] T020 Write `PathAliasResolverBindingTest` (FR-009 / retro #1518)
- [ ] T021 Write `AuthControllerFindUserByNameBindingTest` (FR-009 / retro #1525)
- [ ] T022 Write `SitemapGeneratorBindingTest` (FR-009 / retro #1527)
- [ ] T023 Write `UserBlockServiceBindingTest` (FR-009 / retro #1527)
- [ ] T024 Add `[Unreleased]` CHANGELOG entry
- [ ] T025 Finalize `docs/specs/access-control.md` with registry resolver contract section
- [ ] T026 File GitHub issue for the getquery-bindings-baseline follow-up mission

**Implementation sketch**:
1. For each retro test: locate the class under test, find the `getQuery()` callsite, write a unit test that injects a `RecordingEntityQuery` stub, runs the production method, and asserts either `$query->boundAccount !== null` or `$query->accessChecks === [false]` (for system-context callers).
2. Confirm package namespaces: `PathAliasResolver` in `packages/path/`, `AuthController` in `packages/auth/` or `packages/user/`, `SitemapGenerator` in `packages/seo/`, `UserBlockService` in `packages/user/`.
3. Update `CHANGELOG.md` under `[Unreleased]` with bullets for all five work packages.
4. Add the registry resolver contract section to `docs/specs/access-control.md`.
5. File the follow-up GitHub issue and reference its number in the merge commit body.

**Parallel opportunities**: T020 through T023 are fully parallel (different packages, no shared state).

**Risks**:
- Grep for `findUserByName` before writing the test file location — Assumption A3 (AuthController package) needs verification.
- Some callsites use `accessCheck(false)` (system-context) rather than `setAccount()`. Assert the actual production pattern, not an assumed one.

---

## Execution Lanes

| Lane | WPs | Strategy |
|---|---|---|
| Lane 1 | WP01 | Parallel (independent) |
| Lane 2 | WP02 | Parallel (independent) |
| Lane 3 | WP03 | Parallel (independent) |
| Lane 4 | WP04 | Parallel (independent; baseline regeneration note above) |
| Lane 5 | WP05 | After WP03 and WP04 approved |

The first four work packages are independent and safe for parallel lanes. The wrap-up package requires the test helper (WP03) and the CI gate baseline (WP04) to be in place first.
