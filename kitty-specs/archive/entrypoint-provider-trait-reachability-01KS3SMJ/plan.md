# Implementation Plan: Entrypoint Provider — Trait-Member Reachability

**Branch**: `main` | **Date**: 2026-05-20 | **Spec**: `kitty-specs/entrypoint-provider-trait-reachability-01KS3SMJ/spec.md`
**Mission**: `entrypoint-provider-trait-reachability-01KS3SMJ`
**Closes**: #1501

---

## Summary

`WaaseyaaEntrypointProvider` (line 102) calls `isEntrypointClass($property->getDeclaringClass()->getName(), …)` for every trait property reported by shipmonk. When shipmonk reports a trait property, `getDeclaringClass()` returns the **trait itself** — not any using class. The `hasApiPhpDoc` guard at line 148 is therefore called with the trait's own `\ReflectionClass`, which *does* carry `@api`. However, whether that check fires correctly depends on exactly when and how shipmonk invokes `shouldMarkPropertyAsRead`. WP01 confirms the exact failure mechanism before any code changes.

The fix adds a single named method `isTraitWithApiPhpDoc(\ReflectionClass $reflection): bool` that fires for trait-typed declaring classes, reads `$reflection->getDocComment()` for `@api`, and returns `VirtualUsageData` — making trait-member propagation explicit, testable, and documented independent of the class-level path.

**Branch contract (repeated):** current branch `main` → planning base `main` → merge target `main`.

---

## Technical Context

**Language/Version**: PHP 8.5+, `declare(strict_types=1)`  
**Primary Dependencies**: PHPStan 2.x, `shipmonk/dead-code-detector` (third-party, no patches), PHP `ReflectionClass` stdlib  
**Storage**: N/A  
**Testing**: PHPUnit 10.5 — unit tests in `tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php` (new file, no existing test dir)  
**Target Platform**: Linux CI (PHP built-in; also WSL2 dev)  
**Project Type**: Single tool — `tools/phpstan/WaaseyaaEntrypointProvider.php`  
**Performance Goals**: ≤ 100 ms wall-clock overhead added to `composer phpstan` (NFR-001)  
**Constraints**: No new external dependencies (NFR-002). `bin/check-dead-code` gate remains fail-on-new (C-003). No vendor patches.  
**Scale/Scope**: 307-line provider; 31 baseline entries targeted; 4 WPs.

---

## Charter Check

Governance: `software-dev-default`, paradigm `domain-driven-design`, tools `git`+`spec-kitty`, DIR-001/DIR-002/DIR-003.

| Gate | Status | Notes |
|---|---|---|
| Spec exists and approved | PASS | `spec.md` present, mission in Spec state |
| Branch contract matches charter | PASS | main → main |
| No external deps added | PASS (design) | stdlib only, confirmed by NFR-002 |
| CI gate not relaxed | PASS (design) | C-003 explicit |
| PR traceable (Closes #1501) | PASS | C-001 explicit |

No violations.

---

## Project Structure

### Documentation (this mission)

```
kitty-specs/entrypoint-provider-trait-reachability-01KS3SMJ/
├── plan.md              ← this file
├── research/
│   └── wp01-diagnosis.md    ← WP01 deliverable (investigative WP)
└── tasks.md             ← Phase 2 output (/spec-kitty.tasks — NOT created here)
```

### Source Code (repository root)

```
tools/
├── phpstan/
│   ├── WaaseyaaEntrypointProvider.php   ← edit: add isTraitWithApiPhpDoc(), extend shouldMarkPropertyAsRead/Written
│   └── tests/
│       └── WaaseyaaEntrypointProviderTest.php   ← new file (FR-006)

phpstan-dead-code-baseline.neon          ← regenerated in WP03 (−31 entries)

CLAUDE.md                                ← edit in WP04 (§ "Dead code audits")
CHANGELOG.md                             ← edit in WP04 ([Unreleased])
```

---

## Work Package Plan

### WP01 — Diagnostic (investigative)

**Goal**: Determine exactly which of the three hypotheses produces the 31 persistent baseline entries. No code changes. The deliverable is a written diagnosis.

**Investigation steps**:

1. Run `vendor/bin/phpstan analyse -c phpstan-dead-code.neon 2>&1 | grep -E "(RevisionableEntityTrait|InteractsWithApi|RefreshDatabase)"` and capture all reported findings (class/property/method names, file paths).

2. Add a temporary `error_log()` probe inside `shouldMarkPropertyAsRead` and `shouldMarkPropertyAsWritten` that dumps `$property->getDeclaringClass()->getName()` and `$property->getDeclaringClass()->isTrait()` to stderr, then re-run PHPStan. This answers: "Does shipmonk call our provider with the trait as declaringClass, or does it substitute the using class?"

3. Inspect `hasApiPhpDoc` against each of the three traits' `\ReflectionClass` instances directly in a one-off PHP script (`php -r "…"`) to confirm the `@api` tag is present and the regex fires — ruling out a docblock parsing issue.

4. For `RevisionableEntityTrait` specifically: check whether `loadEntitySupportingTraits` actually populates `RevisionableEntityTrait` in its output by adding a temporary `var_dump(array_keys($this->entitySupportingTraits))` in the constructor. The scanner at lines 230–233 only walks `packages/*/src/*.php` and `packages/*/src/Entity/*.php`. If `Node`/`Article` live deeper (e.g. `packages/node/src/Entity/Node.php`), they ARE covered by the second glob. Confirm they are found.

5. For `InteractsWithApi` and `RefreshDatabase`: these are test-directory traits. `loadEntitySupportingTraits` does not scan `tests/`. Confirm they are absent from `entitySupportingTraits`. Then confirm whether `isEntrypointClass` is even called with them — and if so, whether `hasApiPhpDoc` returns `true` for their `\ReflectionClass`.

**Hypothesis matrix** (to be resolved):

| Hypothesis | Probe | Expected finding |
|---|---|---|
| (a) Scanner miss: `RevisionableEntityTrait` not in `entitySupportingTraits` | Step 4 | If absent → scanner gap in `loadEntitySupportingTraits` |
| (b) `declaringClass` is the trait itself, bypassing `entitySupportingTraits` lookup | Step 2 | `isTrait()=true`, FQCN is trait → lookup misses |
| (c) `hasApiPhpDoc` fires but provider never called for trait members | Step 2+3 | No probe output → shipmonk skips our provider for traits |
| (d) Mixed: (b) for `RevisionableEntityTrait`, (c) for testing traits | Steps 2+3+4 | Likely — two different root causes |

**Deliverable**: `kitty-specs/entrypoint-provider-trait-reachability-01KS3SMJ/research/wp01-diagnosis.md` containing:
- Exact findings from PHPStan for all 31 entries (trait name, member name, kind)
- Probe output (declaringClass names + isTrait() values)
- Confirmed hypothesis (one of a/b/c/d above)
- Precise code lines in `WaaseyaaEntrypointProvider.php` that need changing
- Design instruction for WP02 (single paragraph describing the fix method signature and call site)

**Key file**: `tools/phpstan/WaaseyaaEntrypointProvider.php` lines 90–100 (`shouldMarkPropertyAsRead`/`Written`), 102–138 (`isEntrypointClass`), 148–155 (`hasApiPhpDoc`), 220–273 (`loadEntitySupportingTraits`).

---

### WP02 — Provider patch

**Goal**: Implement the fix described in WP01's diagnosis. Add unit tests. Closes #1501.

**Pre-condition**: WP01 diagnosis delivered and verified.

**Expected implementation** (subject to WP01 findings):

Based on the provider's existing structure, the most likely fix is a new `isTraitWithApiPhpDoc` method called from `shouldMarkPropertyAsRead`/`Written` (and possibly `shouldMarkMethodAsUsed`) that:

```php
private static function isTraitWithApiPhpDoc(\ReflectionClass $declaringClass): bool
{
    return $declaringClass->isTrait() && self::hasApiPhpDoc($declaringClass);
}
```

And in `shouldMarkPropertyAsRead` (line 90), add before the `isEntrypointClass` call:

```php
if (self::isTraitWithApiPhpDoc($property->getDeclaringClass())) {
    return VirtualUsageData::withNote('Waaseyaa trait @api entrypoint');
}
```

Same addition to `shouldMarkPropertyAsWritten` and `shouldMarkMethodAsUsed`.

If WP01 reveals hypothesis (a) (scanner miss) instead of (b), the fix is in `loadEntitySupportingTraits` — add recursive scan of `packages/*/src/**/*.php` or widen the glob. WP02 implements whichever path WP01 confirms; the trait-`@api` path covers both testing traits regardless.

**Unit test file**: `tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php`

Test cases required (FR-006):

- **(a)** Fixture trait with class-level `@api` → `shouldMarkPropertyAsRead` returns non-null `VirtualUsageData`
- **(b)** Fixture trait without `@api` → `shouldMarkPropertyAsRead` returns `null`
- **(c)** Regression: `RevisionableEntityTrait`, `InteractsWithApi`, `RefreshDatabase` — real reflection instances — each causes `shouldMarkPropertyAsRead` to return non-null

Note: The provider's constructor requires `$projectRoot`. Tests should pass `__DIR__ . '/../../..'` (resolving to repo root) so `loadDeclaredProviders`/`loadEntitySupportingTraits` exercise real package manifests. For fixture-trait cases, anonymous `ReflectionClass` objects via temp files or inline `eval()` stubs.

**Files changed**:
- `tools/phpstan/WaaseyaaEntrypointProvider.php` — add `isTraitWithApiPhpDoc`, update callers
- `tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php` — new

---

### WP03 — Baseline regeneration + verification

**Goal**: Regenerate `phpstan-dead-code-baseline.neon` and confirm the 31 entries dropped.

**Steps**:

1. Run: `vendor/bin/phpstan analyse -c phpstan-dead-code.neon --generate-baseline=phpstan-dead-code-baseline.neon`
2. Assert: `grep -cE "(RevisionableEntityTrait|InteractsWithApi|RefreshDatabase)" phpstan-dead-code-baseline.neon` returns `0`
3. Assert baseline shrinks by exactly 31 entries (count before vs after; record both counts)
4. Run `composer verify` — must exit 0
5. Run `vendor/bin/phpunit --filter WaaseyaaEntrypointProviderTest` — must pass (SC-002)

**Files changed**:
- `phpstan-dead-code-baseline.neon` — regenerated (−31 entries, no new entries)

---

### WP04 — Wrap-up

**Goal**: Documentation and changelog. Final `composer verify` green.

**Steps**:

1. Edit `CLAUDE.md` § "Dead code audits and intentional scaffolding" to add:
   > **Traits with `@api`**: If a trait carries a class-level `@api` docblock, all its properties and methods are automatically recognized as used by `WaaseyaaEntrypointProvider::isTraitWithApiPhpDoc`. No per-trait registration is needed. This applies to entity-supporting traits (e.g. `RevisionableEntityTrait`) and testing traits (e.g. `InteractsWithApi`, `RefreshDatabase`) alike.

2. Add `CHANGELOG.md` `[Unreleased]` bullet:
   > fix(dead-code): trait members with class-level `@api` now recognized as used by `WaaseyaaEntrypointProvider`; removes 31 false-positive baseline entries for `RevisionableEntityTrait`, `InteractsWithApi`, `RefreshDatabase` (#1501)

3. Run `composer verify` — final green confirmation.

**Files changed**:
- `CLAUDE.md` (§ "Dead code audits and intentional scaffolding")
- `CHANGELOG.md` (`[Unreleased]`)

---

## WP Dependency Chain

```
WP01 (diagnostic) → WP02 (provider patch) → WP03 (baseline regen) → WP04 (wrap-up)
```

All WPs sequential. WP02 cannot begin until WP01's `research/wp01-diagnosis.md` is committed and approved.

---

## Success Criteria Cross-Reference

| SC | WP | Verification |
|---|---|---|
| SC-001 (0 baseline entries for three traits) | WP03 step 2 | `grep -cE "…" phpstan-dead-code-baseline.neon` = 0 |
| SC-002 (unit test passes) | WP02/WP03 step 5 | `phpunit --filter WaaseyaaEntrypointProviderTest` |
| SC-003 (CI gate exits 0) | WP03 step 4 | `composer verify` |
| SC-004 (fixture trait with @api → marked used) | WP02 test (a) | unit test |
| SC-005 (fixture trait without @api → not marked used) | WP02 test (b) | unit test |
| SC-006 (issue #1501 closes) | WP02 commit footer | `Closes #1501` |

---

## Branch Contract (final)

**Current branch at plan start**: `main`
**Planning/base branch**: `main`
**Merge target for completed changes**: `main`
**Branch matches target**: `true`

All WP commits land directly on `main` via the spec-kitty lane/PR workflow.
