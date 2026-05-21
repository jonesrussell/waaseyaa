# WP02 Review Cycle 1 — Changes Requested

**Reviewer**: claude:opus-4-7:reviewer
**Verdict**: REJECTED — missed call-site causes hard fatal on every HTTP request through HttpKernel.

---

## Issue 1 (BLOCKER): Missed `AccessPolicyRegistry` call site in `AdminSurfaceServiceProvider`

`AccessPolicyRegistry::__construct()` was changed from `(LoggerInterface)` to `(LoggerInterface, PolicyDependencyResolverInterface)` — a required second argument. The implementer updated `AbstractKernel::discoverAccessPolicies()` but missed a second call site that constructs the registry with the old one-argument signature:

```
packages/admin-surface/src/AdminSurfaceServiceProvider.php:214:
    $registry = new AccessPolicyRegistry(new NullLogger());
```

This is a hard fatal — `ArgumentCountError: Too few arguments to function AccessPolicyRegistry::__construct(), 1 passed [...] and exactly 2 expected`. It crashes kernel boot the moment `AdminSurfaceServiceProvider::routes()` runs (i.e., every HTTP request that hits the builtin route registrar).

**Verified impact**: `./vendor/bin/phpunit --testsuite Integration` results:

| Branch | Tests | Failures |
|---|---|---|
| `kitty/mission-access-fail-closed-completeness-01KS3RJT` (base) | 1197 | 0 |
| `kitty/mission-access-fail-closed-completeness-01KS3RJT-lane-b` (WP02) | 1198 | **17** |

All 17 are HTTP 500s downstream of this fatal:
- 4 × OIDC (`OidcDiscovery/Token/Authorize/JwksIntegrationTest`)
- 4 × Phase13 `InertiaMultipartCsrfIntegrationTest`
- 8 × Phase13 `SsrHttpKernelIntegrationTest`
- 1 × `SurfaceMap\PublicSurfaceVerificationTest`

The implementer's claim of "5/5 tests pass, PHPStan clean" is true only for the WP02-owned tests. The cross-cutting integration suite regresses by 17 cases.

### Fix

Replace the `AdminSurfaceServiceProvider:214` call site with a registry that has a resolver — or, since this provider builds an ad-hoc registry for `routes()`, inject the kernel's already-built `accessHandler` instead of re-running discovery. Two viable shapes:

**(a) Resolver-aware construction (smaller diff, preserves current call shape)** — instantiate `KernelPolicyDependencyResolver` from `KernelServicesInterface` like `AbstractKernel` does, then pass it. Requires `AdminSurfaceServiceProvider` to have access to `KernelServicesInterface`.

**(b) Reuse the kernel-built handler** — `AbstractKernel` already exposes `getAccessHandler()`. `AdminSurfaceServiceProvider::routes()` should consume that instead of re-discovering policies, since duplicate discovery is wasteful and re-introduces the two-phase circular-dep risk inside a route registrar.

Either approach must be backed by a regression test that boots `HttpKernel` end-to-end (not just `AccessPolicyRegistry` in isolation) — the existing `AccessPolicyRegistryTest` and `AttachmentPolicyDiscoveryTest` did not catch this because neither walks `AdminSurfaceServiceProvider::routes()`.

---

## Issue 2 (BLOCKER addendum): Add a global call-site grep to the DoD

The Definition of Done should require:
```bash
grep -rn "new AccessPolicyRegistry" packages/ tests/
```
to confirm every call site uses the new two-argument constructor. This audit would have surfaced the `AdminSurfaceServiceProvider` site in seconds.

Also recommend: run at least one HTTP-kernel integration test (e.g. `tests/Integration/Phase13/SsrHttpKernelIntegrationTest.php` or any OIDC test) before declaring WP02 done. The WP02-scoped tests boot the kernel, but `AttachmentPolicyDiscoveryTest` uses an anonymous kernel subclass with its own `compileManifest()` that does NOT include the `AdminSurfaceServiceProvider` path, so the fatal is invisible.

---

## Issue 3 (NON-BLOCKING design concern, recorded for follow-up): Phase-2 policy holds a stale `EntityAccessHandler` reference

The two-phase algorithm passes the *preliminary* `EntityAccessHandler` to phase-2 constructors via `KernelPolicyDependencyResolver::setPreliminaryHandler()`, then returns a *new* final handler built from `$allPolicies` (registry line 98). The kernel binds the final handler, but `ParentDelegatedAccessPolicy` (and any future phase-2 policy) retains a reference to the preliminary one — a different object that only knows about phase-1 policies.

For attachment specifically this is safe today: attachments delegate only to parent entity types (node, taxonomy, …) whose policies are all phase-1 (no `EntityAccessHandler` dep). But the design is fragile:

- If a future phase-2 policy delegates to another phase-2 policy, the second policy is invisible to the first.
- Any later `addPolicy()` call on the kernel-bound handler (tests, runtime registration) is invisible to phase-2 policies.

Fixes worth considering:
- Return the preliminary handler (mutated by appending phase-2 policies via `addPolicy()`) rather than building a fresh one. That way the reference held by phase-2 policies points at the same object the kernel binds.
- Or document the invariant clearly in `KernelPolicyDependencyResolver` and the registry: "phase-2 policies must not depend on other phase-2 policies."

This is non-blocking for #1519 closure — flagging it as a footgun for the spec/follow-up rather than a defect to fix in cycle 2. Pick one if cycle 2 is in-scope; otherwise file a follow-up issue.

---

## Issue 4 (NIT, NON-BLOCKING): Duplicate exception-handling layer

`AccessPolicyRegistry::resolveParameters()` (lines 109–120) catches `PolicyInstantiationException` only to re-throw it unmodified, then catches `\Throwable` to wrap. The first arm is dead code — a plain `catch (\Throwable $e)` would suffice with an `instanceof` guard, or simply let `PolicyInstantiationException` propagate without a `try` at all and wrap unexpected throwables at a higher boundary.

Not a defect; tighten if cycle 2 happens.

---

## What's correct

These were verified and pass:

- `PolicyDependencyResolverInterface` is PSR-11-clean — no Symfony/PSR-11 imports in its signature (NFR-005 satisfied).
- `PolicyInstantiationException` is typed (not generic `\RuntimeException` at the call sites), names policy class and parameter, extends `\RuntimeException`.
- `discover()` no longer log-and-continues on instantiation failures; the only `continue` is for `class_exists() === false` (autoload race), which the prompt explicitly preserved.
- Resolver Rule 1 (array) correctly preserves `ConfigEntityAccessPolicy` array-first-param shape.
- Resolver Rule 3/4 (nullable, defaulted) correctly handles `GenealogyContentAccessPolicy` and `AgentRunAccessPolicy` (the two pre-existing nullable-defaulted policies in the codebase).
- 1,215/1,215 access+foundation+attachment tests pass.
- 5/5 WP02 tests pass.
- `ProviderRegistryKernelServices` companion change (adding `EntityTypeManagerInterface` as a resolvable key) is correct and necessary — `ParentDelegatedAccessPolicy` types its dep as `EntityTypeManagerInterface`, not the concrete class. Without it, the resolver would have thrown `PolicyInstantiationException` for this policy. Side effects on existing callers are nil — this is an additive widening of the keys map, not a behavior change for the concrete-class key.

---

## Required next steps for cycle 2

1. Fix `AdminSurfaceServiceProvider:214` (see Issue 1).
2. Add a regression test that boots `HttpKernel` end-to-end and hits an admin route (e.g., reuse one of the failing Phase13 tests as the verification gate).
3. Re-run `./vendor/bin/phpunit --testsuite Integration` and confirm 0 new failures vs. the mission base branch.
4. Optional (Issue 3 follow-up): document the phase-2-cannot-depend-on-phase-2 invariant, or switch to `addPolicy()`-on-preliminary so all references stay consistent.

Once cycle 2 lands, also recommend grepping for any other call sites that may have similar signature mismatches (`grep -rn "new AccessPolicyRegistry"` ⇒ should show only the two updated kernel/admin-surface sites and the test fixtures).
