# Access Fail-Closed Completeness

**Mission:** `access-fail-closed-completeness-01KS3RJT`
**Status:** Spec
**Target branch:** `main`
**Predecessor:** `sql-entity-query-access-checking-01KRYP15` (flipped `accessCheck()` to fail-closed by default)
**Closes:** #1516, #1519, #1528, #1529
**Retro-covers (regression tests):** #1518, #1525, #1527

## Why this mission exists

The predecessor mission flipped `SqlEntityQuery::accessCheck(true)` to fail-closed in v0.1.0-alpha.181. That landed the *runtime guarantee* — any caller hitting an entity query without binding an account or explicitly opting out throws `MissingQueryAccountException`. The runtime guarantee surfaced **three structural gaps** the predecessor mission did not close:

1. **Router-level account threading.** Some routers construct controllers without passing the request's authenticated account. The controller then falls through to `accessCheck(false)` (or, post-alpha.181, throws). For semantic search (#1516), this means access-restricted rows leak to any authenticated user. The fix is not "patch one router" — it is to make account-threading the default path so authors cannot forget.

2. **Policy auto-instantiation.** The access-policy registry's discovery heuristic only auto-instantiates policies whose first constructor parameter is typed `array`. Any policy with injected services — `ParentDelegatedAccessPolicy` (#1519), and every future cross-package policy — is silently logged as an error and skipped. The registry returns "no policy for this entity type," falling through to default-deny semantics, but only because of an unrelated default. **Authors writing valid policies have no signal that their work is dead.** The fix is a container-resolved registry plus a fail-closed boot assertion.

3. **No CI guard.** Three closed bug-class regressions shipped between alpha.181 and triage (2026-05-20): #1518 (`PathAliasResolver`), #1525 (`AuthController::findUserByName`), #1527 (`SitemapGenerator` + `UserBlockService`). Each was caught only when production threw 500s. The pattern — `$storage->getQuery()->condition(...)->execute()` without `setAccount()` or `accessCheck(false)` — has no static guard. The fix is `bin/check-getquery-bindings`: a baseline-mode CI gate (modeled on the dead-code baseline), plus a tracking follow-up mission to drive the baseline to zero.

The mission's contract: after merge, no future mission can ship a new unbound `getQuery()` callsite, no future policy with injected dependencies can fall off the registry silently, and the semantic-search leak is closed with a regression test that asserts row-level filtering for an authenticated user.

## User scenarios

### Primary flow: an authenticated semantic search respects per-row access

1. A platform consumer (e.g. Minoo) has two users — `viewer-a` with read access to nodes tagged `public` only, and `viewer-b` with read access to all nodes including `internal`.
2. The system has indexed both `public` and `internal` nodes into the vector store.
3. `viewer-a` issues `GET /api/search?q=climate&type=node` with their session.
4. The response contains only nodes both (a) semantically matching the query and (b) where `viewer-a` has the `view` ability — `internal` nodes are filtered out at the row level.
5. `viewer-b` issuing the same query receives a superset including the `internal` matches.

### Recovery flow: a developer adds a new policy and gets immediate signal if it cannot be wired

1. A developer adds `ParentDelegatedAccessPolicy` (or any new policy class) carrying `#[PolicyAttribute(entityType: 'foo')]` with a constructor requiring framework services.
2. They do not write a manual binding in their package's `ServiceProvider::boot()`.
3. On `composer verify` (and on dev-server boot), the kernel attempts to resolve the policy's constructor arguments from the container.
4. If resolution succeeds, the policy is active. If it fails (e.g. an unresolvable dependency), **kernel boot fails with an actionable error naming the policy class and the missing dependency** — not a silent log.

### Recovery flow: a developer regresses a getQuery binding

1. A developer adds a service helper using `$storage->getQuery()->condition('foo', $bar)->execute()` without `setAccount()` or `accessCheck(false)`.
2. They push the branch and open a PR.
3. The CI `verify` job runs `bin/check-getquery-bindings`.
4. The script reports the new callsite against the baseline and exits non-zero. CI fails.
5. The developer fixes the callsite (either `setAccount($request->attributes->get('_account'))` or an explicit `accessCheck(false)` with a justification comment), or — if the callsite is legitimately exempt — appends it to the baseline file with an inline comment explaining why.

### Edge cases

- **Mid-mission new offender.** A WP introduces a new unbound `getQuery()` callsite. CI rejects it; the WP author must fix it within the WP, not defer.
- **Baseline ambiguity on rename.** A file move changes a baseline entry's hash. The CI script normalizes path-relative entries; if the baseline contains stale paths after a rename, the WP author regenerates the baseline and commits it in the same change.
- **Policy with optional dependencies.** A policy declares a nullable constructor parameter (e.g. `?LoggerInterface $logger = null`). The container-resolved registry must respect the default — not require the optional service to be bound.
- **Policy with circular dependency.** A policy depends on a service that itself depends on the policy registry. The registry's resolver must detect this and fail boot with a precise error, not infinite-loop.
- **Test helper double-binding.** A test constructs `RecordingEntityQuery` and accidentally calls `accessCheck()` more than once. The recorded `list<bool>` faithfully reflects the call sequence; PHPUnit assertions on the list catch double-binding bugs.

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | `SearchRouter::handle()` passes the request's authenticated account (`$request->attributes->get('_account')`) into `SearchController` such that `SearchController` executes its entity queries with `setAccount()` bound to that account. |
| FR-002 | Mandatory | `AccessPolicyRegistry::discover()` resolves each discovered policy class's constructor arguments via a typed resolver protocol that the kernel provides. The resolver supports type-hinted service dependencies (interfaces and concrete classes), array entity-types arrays (existing `ConfigEntityAccessPolicy` shape), nullable parameters with defaults, and scalar parameters with defaults. |
| FR-003 | Mandatory | `ParentDelegatedAccessPolicy` is auto-instantiated by `AccessPolicyRegistry` at kernel boot without manual `ServiceProvider::boot()` registration. |
| FR-004 | Mandatory | If any class carrying `#[PolicyAttribute]` cannot be auto-instantiated by the registry (e.g. an unresolvable dependency, a constructor exception), kernel boot **fails** with an exception identifying the policy class and the failing dependency. There is no silent skip. |
| FR-005 | Mandatory | `bin/check-getquery-bindings` exists as a PHP script invokable from `composer verify` that scans all PHP files under `packages/*/src/` for unbound `getQuery()->...->execute()` callsites (no `setAccount()` and no `accessCheck(false)` in the chain). |
| FR-006 | Mandatory | On first run after this mission lands, the script generates `tools/getquery-bindings-baseline.txt` capturing all current unbound callsites (path + line + canonical excerpt). |
| FR-007 | Mandatory | On subsequent runs, the script exits non-zero if any unbound callsite exists that is not present in the baseline. New callsites cause CI failure. |
| FR-008 | Mandatory | `Waaseyaa\Entity\Testing\RecordingEntityQuery` exists in `packages/entity/testing/` (autoload-dev only, never shipped to consumers as runtime code), implements `EntityQueryInterface`, records every `accessCheck()` call into an inspectable `list<bool>`, and stubs every other interface method to return `$this` or a benign empty result so tests can chain calls without bespoke anonymous stubs. |
| FR-009 | Mandatory | Regression tests exist that prove the previously fixed callsites stay bound: one test asserting `PathAliasResolver::resolve()` calls `setAccount()` (or `accessCheck(false)` with documented intent); one for `AuthController::findUserByName()`; one for `SitemapGenerator::collectFromEntityTypes()`; one for `UserBlockService::isBlocked()`. Each test uses the shared `RecordingEntityQuery` helper. |
| FR-010 | Mandatory | An integration test exists that boots the kernel, invokes the semantic search endpoint as two distinct authenticated users with different per-row access, and asserts the result set differs by the access-restricted rows (FR-001 regression coverage). |
| FR-011 | Mandatory | An integration test exists that asserts `ParentDelegatedAccessPolicy` is the policy returned by `EntityAccessHandler` for `Attachment` entities at runtime (FR-003 regression coverage). |
| FR-012 | Mandatory | A boot-time test asserts that registering a `#[PolicyAttribute]` class whose constructor has an unresolvable dependency causes kernel boot to throw, not silently log (FR-004 regression coverage). |
| FR-013 | Mandatory | A new tracking issue is filed before mission merge, named *"M-B.1: drive `getquery-bindings-baseline.txt` to zero"*, linking to this mission's PR. The merge PR's description references it. |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | `bin/check-getquery-bindings` completes in under 30 seconds on a clean checkout of the framework repo (current size: ~62 packages). |
| NFR-002 | Mandatory | Container-resolved policy instantiation adds no measurable regression to kernel boot time (p95 baseline + container resolution ≤ 5 ms per policy class, measured by an existing kernel-boot benchmark or a new one added in WP05). |
| NFR-003 | Mandatory | The baseline file `tools/getquery-bindings-baseline.txt` is committed as plain text, sorted deterministically (path then line), suitable for human review and `git diff`. |
| NFR-004 | Mandatory | `RecordingEntityQuery`'s method-chain stub adds no measurable overhead to existing access-policy unit tests (p95 test duration unchanged within 2%). |
| NFR-005 | Mandatory | The container resolver protocol does not import any Symfony-specific container types in its public signature; it accepts a typed resolver interface (Waaseyaa's own contract, PSR-11-compatible or equivalent). |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | All changes preserve the 7-layer architecture. `AccessPolicyRegistry` lives in `packages/foundation/` (L0); the policy registry's resolver protocol is defined in L0 or L1, never higher. |
| C-002 | Mandatory | `RecordingEntityQuery` lives in `packages/entity/testing/` (not `packages/entity/src/`) and is registered under `autoload-dev`, so consumer production installs never see the symbol. Per the existing CLAUDE.md gotcha about test helpers under `autoload`. |
| C-003 | Mandatory | The CI gate baseline file is documented in `CLAUDE.md` under a new "CI gates" section so future contributors discover it; entries in the baseline carry an inline comment explaining the exemption rationale where one exists. |
| C-004 | Mandatory | The mission's merge commit closes #1516, #1519, #1528, #1529 via a `Closes #N` footer. Issues #1518, #1525, #1527 remain closed; this mission adds regression tests, it does not reopen them. |
| C-005 | Mandatory | No existing public API on `AccessPolicyInterface`, `EntityAccessHandler`, `Gate`, or `SqlEntityQuery` is broken. The registry's resolver protocol is additive; existing policies (`ConfigEntityAccessPolicy`, content-entity policies) continue to work without source changes. |
| C-006 | Mandatory | No CI hooks (`.github/workflows/*`, lefthook, husky) are skipped or bypassed during this mission's PRs. The new CI gate is wired into `composer verify` and runs in the existing CI workflow. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | Authenticated semantic search returns only access-allowed rows for the requesting account. | Integration test in `tests/Integration/Phase??/SemanticSearchAccessTest.php` passes with two-user setup (FR-010). |
| SC-002 | `ParentDelegatedAccessPolicy` is active in production paths. | Integration test in `tests/Integration/Phase??/AttachmentPolicyDiscoveryTest.php` passes (FR-011). |
| SC-003 | A new unbound `getQuery()` callsite fails CI. | Intentional fixture test: a sample file with an unbound callsite is fed to `bin/check-getquery-bindings` and the script exits non-zero (asserted in `tests/Integration/Phase??/GetQueryBindingsGateTest.php`). |
| SC-004 | All four retro regression tests pass. | `vendor/bin/phpunit --filter "PathAliasResolverBindingTest\|AuthControllerFindUserByNameBindingTest\|SitemapGeneratorBindingTest\|UserBlockServiceBindingTest"` exits 0. |
| SC-005 | `composer verify` is green on the merge commit. | CI status check `verify` passes on the merging PR. |
| SC-006 | Issues #1516, #1519, #1528, #1529 close on merge. | GitHub auto-closes via the merge commit's `Closes #N` footer. |
| SC-007 | M-B.1 tracking issue exists at merge time. | A GitHub issue titled *"M-B.1: drive `getquery-bindings-baseline.txt` to zero"* is open and linked from the merge commit body. |
| SC-008 | A new policy with injected dependencies works out of the box. | The `ParentDelegatedAccessPolicy` integration test (SC-002) is the canonical demonstration; no additional `ServiceProvider::boot()` binding is needed. |

## Key entities

| Entity | Role | Net change in this mission |
|---|---|---|
| `AccessPolicyRegistry` | Foundation kernel bootstrap class. Discovers & instantiates policies. | Major edit: replace constructor heuristic with container-resolved instantiation. |
| Container resolver protocol (new interface) | Typed L0/L1 contract the registry uses to resolve policy constructor args. | +1 file (new interface). |
| `SearchRouter` | L0 router that constructs `SearchController` for `/api/search`. | Edit: thread `_account` from request into controller. |
| `SearchController` | L5 AI vector controller. | Edit: accept account; bind on entity queries. |
| `bin/check-getquery-bindings` | New CI script. PHP CLI. Scans for unbound `getQuery()->...->execute()`. | +1 file. |
| `tools/getquery-bindings-baseline.txt` | Plain-text baseline file. Initial population from current offenders. | +1 file. |
| `Waaseyaa\Entity\Testing\RecordingEntityQuery` | Shared test stub implementing `EntityQueryInterface`. | +1 file (under `packages/entity/testing/`). |
| Integration tests | Two new tests: semantic-search-access, attachment-policy-discovery. | +2 files in `tests/Integration/Phase??/`. |
| Boot-time test | Asserts unresolvable policy dependency fails boot. | +1 test file. |
| Retro regression tests | Four small tests proving prior fixes stay bound. | +4 test files. |
| Documentation | `CLAUDE.md` "CI gates" section; `docs/specs/access-control.md` updates for registry contract. | Edit. |
| `CHANGELOG.md` | `[Unreleased]` entry. | Edit. |

## Assumptions

- The DI container is available at the point where `AccessPolicyRegistry::discover()` runs (after service providers register). If not, a small kernel refactor stages discovery after the container is fully built. The mission accepts this as part of WP02 scope.
- `EntityQueryInterface` has a stable enough surface area that one shared stub covers the testing patterns the four retro callsites need. If a callsite needs a method the stub doesn't expose, WP03 adds it to the stub rather than reverting to bespoke anonymous classes.
- The current CI surface (`composer verify` + GitHub Actions workflow) is the right wiring point for the new gate. No new workflow file is required; the gate is one more step in the existing `verify` script.
- Per `feedback_pr_traceability_signals.md`, the M-B.1 follow-up issue is filed manually after PR merge; this mission's spec only requires that it *be filed before merge or as an immediate next step*. The merge commit can reference its number.
- Container-resolved instantiation does not need to support setter injection or property injection; constructor injection only. Future cases that need setter injection can add it; YAGNI.

## Out of scope

- Driving `getquery-bindings-baseline.txt` to zero — explicit M-B.1 follow-up mission.
- Refactoring `EntityQueryInterface` itself.
- Field-level access changes (covered by `FieldAccessPolicyInterface`, not in this mission's surface).
- Refactoring other routers besides `SearchRouter` to use a base-class account-threading helper — if such a helper emerges from WP01, it is documented but not retrofitted to other routers in this mission. Retrofit is a follow-up.
- Auditing config-entity vs content-entity policy split.
- Multi-tenant or cross-account access semantics (single-account-per-request model unchanged).

## WP outline (for /spec-kitty.plan)

The planner is free to revise. Indicative shape:

- **WP01 — SearchRouter account threading.** Thread `_account` from request through `SearchRouter::handle()` into `SearchController`. Add the integration test from FR-010. Closes #1516.
- **WP02 — Container-resolved AccessPolicyRegistry.** Replace the constructor heuristic with a typed resolver protocol (NFR-005). Add the boot-time fail-closed assertion (FR-004). Add the `ParentDelegatedAccessPolicy` integration test from FR-011. Add the boot-failure unit test from FR-012. Closes #1519.
- **WP03 — `RecordingEntityQuery` test helper.** Extract the shared stub into `packages/entity/testing/`. Wire `autoload-dev`. Migrate the two existing inline stubs from `AuthControllerTest` and `SitemapGeneratorTest` to use it. Closes #1529.
- **WP04 — `bin/check-getquery-bindings` + baseline.** Implement the scanner. Generate initial baseline. Wire into `composer verify`. Write the gate's own integration test (SC-003). Document under "CI gates" in `CLAUDE.md`. Closes #1528.
- **WP05 — Retro regression tests + M-B.1 issue filing + wrap-up.** Add the four regression tests (FR-009). File the M-B.1 follow-up issue (SC-007). `CHANGELOG.md` entry. Update `docs/specs/access-control.md` to document the new registry resolver contract.

## References

- Predecessor mission: `kitty-specs/sql-entity-query-access-checking-01KRYP15/` (state: 5/5 approved, awaiting close-out per `feedback_stuck_approved_mission_closeout.md`).
- Closed bug-class issues: #1518, #1525, #1527 (regression-tested in WP05).
- CLAUDE.md gotchas referenced: "Request attribute is `_account` not `account`" (under HTTP, auth, request lifecycle); "Never put classes that extend dev-only deps under `autoload`" (under Layers, packages, namespaces); the `_data` / autoload-dev rule.
- Memory: `feedback_modern_php_rules.md` (April 2026): typed interfaces only, no service locators, no class-string lookups, contract tests for every extension point.
- Memory: `feedback_regression_tests.md`: always write regression tests when fixing bugs or finding brittle tests.
- Architecture: `docs/specs/access-control.md` (will be updated in WP05).
- Existing CI baseline precedent: `phpstan-dead-code-baseline.neon` + `bin/check-dead-code` — landed warn-only first, flipped to fail-on-new in PR #1504 after baseline dropped 1,341 → 66.
