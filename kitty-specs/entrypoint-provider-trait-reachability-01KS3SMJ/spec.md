# Entrypoint Provider — Trait-Member Reachability

**Mission:** `entrypoint-provider-trait-reachability-01KS3SMJ`
**Status:** Spec
**Target branch:** `main`
**Closes:** #1501

## Why this mission exists

After Bucket 3 of the Phase 3 dead-code cleanup (PR #1503/#1504 vintage), the dead-code baseline still carries **31 entries against three traits** whose class-level docblock explicitly carries `@api`:

| Trait | Entries | Kind |
|---|---:|---|
| `packages/entity/src/RevisionableEntityTrait.php` | 17 | All `Property … is never read` |
| `packages/testing/src/Traits/InteractsWithApi.php` | 9 | 1 property + 8 method `Unused` |
| `packages/testing/src/Traits/RefreshDatabase.php` | 5 | 1 property + 4 method `Unused` |

Other traits carrying the same `@api` annotation **do** drop from the baseline — proving the `@api`-as-entrypoint mechanism works in the general case. The hole is specific: **class-level `@api` does not propagate to trait members when those members are reported against the trait's file but their reachability flows through using-class composition.**

### Concrete state at mission start

1. `tools/phpstan/WaaseyaaEntrypointProvider.php` implements `shouldMarkPropertyAsRead` / `shouldMarkPropertyAsWritten` (lines 90–100) by calling `isEntrypointClass($property->getDeclaringClass()->getName(), $property->getDeclaringClass())`. The `isEntrypointClass` predicate (line 102) checks: declared providers, entity-mapper namespace, **entity-supporting traits**, policy/middleware attributes, RouteProvider implementor, entity-subclass parent chain, **`@api` PHPDoc on the class itself**.

2. `entitySupportingTraits` is populated by `loadEntitySupportingTraits` (line 220), which walks `packages/*/src/*.php` and `packages/*/src/Entity/*.php` looking for classes that `extends EntityBase|ContentEntityBase` and collecting their `getTraitNames()`. **`RevisionableEntityTrait` SHOULD show up here** — it's `use`d by `Node`, `Article`, and other content entities. The fact that 17 of its properties remain in the baseline means at least one of (a) the scanner misses some content-entity location, (b) shipmonk reports trait-property findings under the trait's `getDeclaringClass()` (the trait itself) rather than the using class, defeating the `entitySupportingTraits` lookup, or (c) there is a transitive trait-of-trait composition issue. The mission's first job is to determine which.

3. `InteractsWithApi` and `RefreshDatabase` are **testing traits** — used by `Waaseyaa\Tests\Integration\*` classes, not by entity subclasses. `loadEntitySupportingTraits` does not scan test directories, so these traits never reach `entitySupportingTraits`. The class-level `@api` PHPDoc on the trait should still fire via `hasApiPhpDoc` (line 148) — but only if `isEntrypointClass` actually gets called with the trait's reflection. Whether that happens depends on the analyzer's model of trait-property ownership (same question as #2).

4. shipmonk's own `ApiPhpDocUsageProvider` is enabled by default (per `vendor/shipmonk/dead-code-detector/rules.neon`). The fact that 31 entries persist despite class-level `@api` means shipmonk's own provider has the same gap our custom provider does. The mission's fix lands in **our** provider — shipmonk is a third-party concern we can't change directly.

## User scenarios

### Primary flow: an entity trait's properties are recognized as used

1. The framework's content entities (e.g. `Node`, `Article`) `use RevisionableEntityTrait`.
2. The trait declares 17 properties — `$revisionId`, `$isCurrentRevision`, `$revisionMetadata`, etc. — hydrated via `ReflectionProperty::setValue` and `ContentEntityBase::set()`.
3. `composer check-dead-code` does not report any of these properties as `never read`. The 17 baseline entries against the trait drop to zero.
4. A developer adding a new property to `RevisionableEntityTrait` does not need to add a baseline entry — the property is automatically considered used because the trait carries `@api`.

### Primary flow: testing traits are recognized as used

1. Integration tests `use InteractsWithApi` or `use RefreshDatabase`.
2. The traits declare methods like `getJson()`, `actingAs()`, `refreshDatabase()` and properties like `$requestHeaders`.
3. `composer check-dead-code` does not report any of these as unused. The 14 baseline entries for the two testing traits drop to zero.

### Recovery flow: a developer adds a trait without `@api`

1. Developer writes a new trait `FooBarTrait` and uses it from a content entity, but forgets the `@api` annotation.
2. `composer check-dead-code` reports the trait's members as dead.
3. The developer adds `@api` to the trait's class-level docblock.
4. Re-running `composer check-dead-code` clears the findings — the same propagation path that works for `RevisionableEntityTrait` now works for `FooBarTrait`.

### Edge cases

- **Trait used by non-entity, non-test class.** `@api` on the trait's own docblock should still mark its members as used. The fix does not require the using class to be an entity subclass.
- **Trait used by a class whose docblock does NOT carry `@api`.** If the trait itself carries `@api`, that's enough. The trait is the API surface; the using class is irrelevant to the trait's own annotation.
- **Trait composed of another trait.** If `OuterTrait` `use`s `InnerTrait` and `OuterTrait` carries `@api`, `InnerTrait`'s members are not implicitly `@api`-marked unless `InnerTrait` itself carries the annotation. This mirrors `@api`'s semantics — it is opt-in, not transitive through composition.
- **`@api` on a method or property docblock, not the class.** Out of scope. This mission addresses **class-level `@api`** that fails to propagate to members. Member-level `@api` is shipmonk's existing concern and already works.
- **A property hydrated only via `ReflectionProperty::setValue` with no AST-visible reads.** This is the `RevisionableEntityTrait::$revisionId` shape. The fix recognizes the trait's `@api` as the signal; no per-property analysis needed.

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | The fix lands in `tools/phpstan/WaaseyaaEntrypointProvider.php`. No vendor patches; no changes to shipmonk's own code. |
| FR-002 | Mandatory | After the fix, `composer check-dead-code` reports **zero** baseline entries for `RevisionableEntityTrait`, `InteractsWithApi`, and `RefreshDatabase`. The baseline file (`phpstan-dead-code-baseline.neon`) is regenerated and contains no entries against those three trait files. |
| FR-003 | Mandatory | The fix is rooted in `@api`-on-trait propagation. Any future trait that carries class-level `@api` automatically has its members recognized as used — no per-trait registration list, no special-casing of these three. |
| FR-004 | Mandatory | The fix preserves all existing entrypoint discovery paths (policies, middleware, providers, mappers, route providers, entity subclasses, controllers, declared service providers, class-level `@api`). No regressions in the existing baseline beyond the targeted 31 entries. |
| FR-005 | Mandatory | Documentation: `CLAUDE.md` § "Dead code audits and intentional scaffolding" is updated to describe the trait-member propagation behavior so future contributors know `@api` on a trait class-doc is sufficient. |
| FR-006 | Mandatory | Unit test for the provider exists at `tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php` (or whatever path the planner picks). The test covers: (a) a fixture trait with class-level `@api` causing its properties to be marked used; (b) a fixture trait without `@api` causing its properties NOT to be marked used; (c) regression coverage for the three traits the mission unblocks. |
| FR-007 | Mandatory | The fix's mechanism is named in the code (a single method on the provider) so the next contributor reading the file can find where trait `@api` propagation lives. |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | The added trait-resolution logic adds ≤ 100 ms to the existing PHPStan run on a clean checkout. Measured by comparing `composer phpstan` wall-clock before and after. |
| NFR-002 | Mandatory | The fix does not add any external dependency to the framework. `WaaseyaaEntrypointProvider` continues to use only PHP stdlib + the shipmonk base class it already extends. |
| NFR-003 | Mandatory | The 31 dropped baseline entries are not re-introduced by the next baseline regeneration (i.e. the fix is durable, not a one-shot baseline edit). Verified by SC-001. |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | The merge commit closes #1501 via `Closes #N` footer. |
| C-002 | Mandatory | No changes to `phpstan-dead-code-baseline.neon` are made except via the regeneration command documented in `CLAUDE.md`. The fix shrinks the baseline by exactly 31 entries (the trait counts above). |
| C-003 | Mandatory | The `bin/check-dead-code` CI gate remains fail-on-new (not warn-only). The mission tightens the entrypoint provider; it does not relax the gate. |
| C-004 | Mandatory | `composer verify` is green on the merge commit. |
| C-005 | Mandatory | No CI hooks bypassed during this mission's PRs. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | Regenerating the baseline at the merge commit produces zero entries against `RevisionableEntityTrait`, `InteractsWithApi`, `RefreshDatabase`. | `vendor/bin/phpstan analyse -c phpstan-dead-code.neon --generate-baseline=phpstan-dead-code-baseline.neon && grep -c -E "(RevisionableEntityTrait\|InteractsWithApi\|RefreshDatabase)" phpstan-dead-code-baseline.neon` returns 0. |
| SC-002 | The provider's unit test passes. | `vendor/bin/phpunit --filter WaaseyaaEntrypointProviderTest` passes (FR-006). |
| SC-003 | The `bin/check-dead-code` CI gate runs and exits zero on the merge commit. | CI status check `verify` passes. |
| SC-004 | A fixture trait with `@api` on the class docblock has its properties recognized as used. | Unit test from FR-006(a) passes. |
| SC-005 | A fixture trait without `@api` continues to have its properties flagged as potentially unused (regression coverage for FR-003's "opt-in, not blanket"). | Unit test from FR-006(b) passes. |
| SC-006 | Issue #1501 closes on merge. | GitHub auto-closes via `Closes #N` footer. |

## Key entities

| Entity | Role | Net change in this mission |
|---|---|---|
| `tools/phpstan/WaaseyaaEntrypointProvider.php` | The custom dead-code-detector entrypoint provider. | Edit: add trait-`@api` propagation method. |
| `phpstan-dead-code-baseline.neon` | The baseline file. | Edit: 31 entries removed via regeneration. |
| `tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php` (new path, planner picks) | Unit test for the provider. | +1 file (or edits to an existing test if one exists). |
| `CLAUDE.md` § "Dead code audits and intentional scaffolding" | Documentation. | Edit: describe trait-member `@api` propagation. |
| `CHANGELOG.md` | `[Unreleased]` entry. | Edit. |

## Assumptions

- The fix is rooted in the propagation of class-level `@api` from a trait to its members. The mission's WP01 is an **investigative WP** that determines exactly which of the three hypotheses (deeper scan paths, trait-vs-using-class declaring-class question, transitive composition) is at play. The implementation in WP02 follows from that determination.
- WP01 may identify that the issue is mixed — e.g. `RevisionableEntityTrait` needs deeper entity-class scanning, while testing traits need a different propagation path. The mission accepts a single fix can have two narrow code paths inside the same `WaaseyaaEntrypointProvider` method, as long as both are driven by class-level `@api` (no per-trait allowlist).
- shipmonk's own `ApiPhpDocUsageProvider` is not extended or replaced. The fix is purely additive in our provider.
- The 31 entries are the only baseline entries the mission targets. Any other entries the regenerated baseline might newly contain (unrelated to the three traits) are out of scope; if regeneration surfaces new dead code elsewhere, that's a separate mission's concern.

## Out of scope

- Member-level `@api` propagation (already shipmonk-native).
- Removing or refactoring any of the three traits themselves.
- Tightening the `bin/check-dead-code` CI gate beyond its current fail-on-new posture.
- Scanning trait-of-trait composition for transitive `@api`.
- Expanding `entrypoint` discovery to new mechanisms (the existing six patterns are not changed; trait `@api` propagation is a refinement of the seventh "class-level @api" path).
- Refactoring the provider's other discovery methods.

## WP outline (for /spec-kitty.plan)

The planner is free to revise. Indicative shape:

- **WP01 — Diagnostic.** Determine which of the three hypotheses produces the 31 entries. Examine shipmonk's call sites that invoke `shouldMarkPropertyAsRead` for trait properties; identify which class `getDeclaringClass()` returns; document the gap precisely in the WP prompt before any code changes. The WP's deliverable is a one-page diagnosis with code line references.
- **WP02 — Provider patch.** Based on WP01's diagnosis, extend `WaaseyaaEntrypointProvider` with the narrowly-scoped fix(es). Single named method; documented purpose. Unit tests (FR-006). Closes #1501.
- **WP03 — Baseline regeneration + verification.** Regenerate `phpstan-dead-code-baseline.neon`. Assert the 31 entries dropped. Run `composer verify` to confirm no new entries surfaced. Document the regeneration in the WP prompt for future reproducibility.
- **WP04 — Wrap-up.** Update `CLAUDE.md` § "Dead code audits and intentional scaffolding". `CHANGELOG.md` entry. Final `composer verify` green.

## References

- Issue #1501 body: enumerates the three traits and their entry counts; cites Bucket 3 of Phase 3 dead-code cleanup as the audit predecessor.
- `tools/phpstan/WaaseyaaEntrypointProvider.php` lines 90–155: the property-resolution path that needs extension.
- `vendor/shipmonk/dead-code-detector/rules.neon`: where shipmonk's own `ApiPhpDocUsageProvider` is enabled.
- `phpstan-dead-code-baseline.neon`: the file whose 31 entries the mission removes.
- CLAUDE.md § "Dead code audits and intentional scaffolding": existing guidance the mission updates.
- Memory: `feedback_regression_tests.md` — always write regression tests when fixing bugs (FR-006 motivation).
