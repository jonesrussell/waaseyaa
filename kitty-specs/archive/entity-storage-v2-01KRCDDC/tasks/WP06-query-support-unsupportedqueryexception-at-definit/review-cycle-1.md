# WP06 — Review cycle 1

**Verdict**: APPROVE

## Summary

WP06 delivers the definition-time query-support contract (`UnsupportedQueryException`, `UnsupportedListingException`, `DefinitionValidator`) plus the "Fail-fast guarantee" subsection in `contracts/field-storage-backend.md`. Implementation is clean, scoped, and consistent with prior WPs.

## Boot wire-up decision (the critical question)

The implementer did not wire `DefinitionValidator::validateAll()` into `AbstractKernel::boot()`. That file is outside WP06's declared `owned_files`. **Accepted as scope discipline** for these reasons:

1. **Tests exercise `validateAll()` end-to-end.** All four integration tests construct `DefinitionValidator` with a real `EntityTypeManager` + real `BackendResolver` + a real `BackendRegistrar` (built via `TestBackendProvider`), then call `validateAll()`. This is the same call-shape a kernel boot path will use — the validator is reachable and behaviour-verified, not literally dead code.
2. **`@api` annotation is correctly applied** to both exception classes and (implicit via class-level docblock + `final class` semantics) the validator. Dead-code audit currently shows only pre-existing unmatched baseline patterns in `foundation/Kernel/AbstractKernel.php`, unrelated to WP06.
3. **Boot wire-up is owned by a follow-up WP.** WP12 (depends on WP04, WP06, WP09) is the natural home for full kernel integration that activates multi-backend routing end-to-end. WP07 (`storage coordinator + repository`, depends on WP05) is the *earliest* WP where a call-site is plausible if the team wants production fail-fast sooner; mission-level orchestration may also surface a dedicated wiring WP. Either way, the boot wire-up MUST land before the mission is considered complete — otherwise the FR-021 fail-fast guarantee remains aspirational in production.

**Action item for the orchestrator/next planner**: name the WP that owns the `DefinitionValidator::validateAll()` invocation in `AbstractKernel::boot()` (after `BackendRegistrar::build()`, before `discoverAndRegisterProviders()`). The change is ~3 lines and bounded.

## Acceptance criteria checklist

| # | Criterion | Result |
|---|---|---|
| 1 | `UnsupportedQueryException` with public readonly `$backendId`/`$fieldId`/`$reason`, `\LogicException` base, `@api` | ✅ |
| 2 | `UnsupportedListingException` ADR 015 stub, identical shape, `@api`, unused-OK by `@api` | ✅ |
| 3 | `DefinitionValidator` iterates types → fields → backend → `supportsQuery()`, throws on false, no fallback | ✅ |
| 4 | Boot integration | ⚠ Deferred to integration WP (see decision above) |
| 5 | `Query/EntityQuery.php` untouched | ✅ (not in commit stat) |
| 6 | 4 tests cover indexed+supported, indexed+rejected, exception shape, non-indexed skipped — all call `validateAll()` end-to-end | ✅ |
| 7 | Contract `Fail-fast guarantee` subsection appended to `contracts/field-storage-backend.md` | ✅ |
| 8 | Namespace `Waaseyaa\EntityStorage`, L1, `@api` on public symbols | ✅ |
| 9 | No `psr/log`, no `Illuminate\*`, no service locators, `declare(strict_types=1)`, `final class` | ✅ |
| 10 | Scope discipline — nothing from spec §1.2/§2.2 non-goals | ✅ |

## Gate spot-checks

- `composer cs-check` — clean (no files to fix).
- `composer phpstan` — `[OK] No errors` (1224 files analysed).
- `composer check-composer-policy` — `OK: Composer policy checks passed`.
- `bin/check-package-layers` — clean (no upward edges).
- `bin/audit-dead-code` — 2 pre-existing unmatched-baseline findings in `foundation/Kernel/AbstractKernel.php` (`fieldRegistry` never-read); **unrelated to WP06**, warn-only.
- `./vendor/bin/phpunit packages/entity-storage/tests/Integration/Query/` — 4/4 passing.
- `./vendor/bin/phpunit packages/entity-storage/tests/` — 415 tests / 945 assertions OK (2 warnings about abstract test class + missing coverage driver, both pre-existing and benign).

## Test end-to-end exercise

Verified: every test constructs `DefinitionValidator($manager, $resolver)` and invokes `$validator->validateAll()`. The validator is reachable end-to-end through the same chain a production kernel would use — `BackendRegistrar::build()` → `BackendResolver` → `DefinitionValidator::validateAll()`. Tests are not calling `supportsQuery()` directly.

## Notes

- The scope decision to skip kernel wire-up is documented inline in the commit body and in the validator class docblock. This satisfies traceability for the follow-up planner.
- `UnsupportedListingException` is reserved by class-level `@api` annotation; dead-code audit will not flag it. Good forward-compat hygiene.
- `LogicException` (vs `RuntimeException`) is the correct choice — these are programmer/config errors caught at boot, not transient runtime conditions.

## Outcome

Approved. WP06 is mergeable into the mission branch. The boot wire-up is a known follow-up — owning WP must be identified before mission acceptance.
