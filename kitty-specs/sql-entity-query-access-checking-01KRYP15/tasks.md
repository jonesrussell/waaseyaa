# Tasks: SqlEntityQuery Access Checking

**Mission:** `sql-entity-query-access-checking-01KRYP15`
**Spec:** [spec.md](spec.md) · **Plan:** [plan.md](plan.md) · **Research:** [research.md](research.md) · **Data-model:** [data-model.md](data-model.md) · **Contract:** [contracts/entity-query-interface-additions.md](contracts/entity-query-interface-additions.md) · **Quickstart:** [quickstart.md](quickstart.md)
**Branch:** `main` (planning + merge target). `branch_matches_target: true`.
**Change mode:** `code_change`. No bulk-edit.

## Overview

24 subtasks rolled into 5 work packages. Critical path is strictly
sequential: foundation (exception + interface) → core filter
(SqlEntityQuery rewrite) → call-site sweep (7 packages) → integration
tests → docs + bypass audit + CHANGELOG.

## Execution lanes

```
WP01 ──► WP02 ──► WP03 ──► WP04 ──► WP05
(deps:  none)   (WP01)   (WP02)   (WP03)   (WP04)
```

All five WPs are on the critical path — there's no parallelism in
this mission. Each WP depends on the previous one because the file
under modification (`SqlEntityQuery.php`) is the same load-bearing
class and the sweep / tests / docs all need the filter to be live.

## Subtask index

| ID | Description | WP |
|---|---|---|
| T001 | `MissingQueryAccountException` final class + `forQuery()` factory | WP01 |
| T002 | Add `setAccount(?AccountInterface): static` to `EntityQueryInterface` | WP01 |
| T003 | Unit test: exception factory message + `\RuntimeException` parentage | WP01 |
| T004 | `SqlEntityQuery` internal state (`$account`, `$accessCheckEnabled`) + `setAccount()` impl + real `accessCheck(bool)` | WP02 |
| T005 | Inject `EntityAccessHandler` via lazy DI resolve (no constructor change) | WP02 |
| T006 | `execute()` rewrite: remove stub comment, hydrate page, per-row check, throw on missing-account+enabled, return surviving IDs | WP02 |
| T007 | `count()` returns post-filter cardinality when `accessCheck=true`; pre-filter when `accessCheck=false` | WP02 |
| T008 | `range()` page cursor advances by unfiltered window (FR-007) | WP02 |
| T009 | Unit tests for `SqlEntityQuery`: allow / deny / mixed / count / cursor / missing-account-throws | WP02 |
| T010 | Sweep `packages/oidc/src/ClientRegistry/` (`OidcClientSeeder`, `OidcClientLookup`) | WP03 |
| T011 | Sweep `packages/relationship/src/` (`RelationshipValidator`, `RelationshipDeleteGuardListener`) | WP03 |
| T012 | Sweep `packages/ai-vector/src/` (`SemanticIndexWarmer` keep bypass; `SearchController` bind account) | WP03 |
| T013 | Sweep `packages/graphql/src/Resolver/EntityResolver.php` (drop all 3 `accessCheck(false)`; bind context user) | WP03 |
| T014 | Sweep `packages/genealogy/src/` (6 call sites — classify each as system or user-context) | WP03 |
| T015 | Sweep `packages/workflows/src/DomainValidationListener.php` (system context) | WP03 |
| T016 | Sweep `packages/api/src/JsonApiController.php` (bind `_account` from request) | WP03 |
| T017 | Integration: `ListingFilterTest` (JSON:API index endpoint) | WP04 |
| T018 | Integration: `GraphQLResolverFilterTest` (count + main query both filtered) | WP04 |
| T019 | Integration: `BypassRespectsSystemContextTest` (`accessCheck(false)` returns all) | WP04 |
| T020 | Integration: `AnonymousAccountFilterTest` (Anonymous account filter behaviour) | WP04 |
| T021 | Integration: `AdminBypassCapabilityTest` (bypass-capability holder sees full set) | WP04 |
| T022 | Update `docs/specs/access-control.md` with the new query-layer enforcement; refresh the spec stamp | WP05 |
| T023 | File `docs/security/sql-entity-query-access-check-bypass-audit.md` — line-by-line review of remaining `accessCheck(false)` sites | WP05 |
| T024 | `CHANGELOG.md` `[Unreleased]` bullets (Added / Changed / Security) | WP05 |

---

## WP01 — Exception + interface addition

**Priority:** P0 (foundational; everything else depends on these two surfaces).
**Depends on:** —
**Goal:** Ship the new exception class and the interface contract addition so WP02 can call them and consumers (WP03) can rely on them.
**Independent test:** `composer test` covers the exception's named factory + parentage; PHPStan and cs-check pass on the new file + the interface edit.
**Estimated prompt size:** ~210 lines.
**Prompt:** [tasks/WP01-exception-and-interface.md](tasks/WP01-exception-and-interface.md)

**Subtasks:**
- [ ] T001 `MissingQueryAccountException` final class + `forQuery()` factory (WP01)
- [ ] T002 Add `setAccount(?AccountInterface): static` to `EntityQueryInterface` (WP01)
- [ ] T003 Unit test: exception factory message + `\RuntimeException` parentage (WP01)

**Risks:**
- Interface change is technically a breaking change for third-party implementers (today: none known; verify via Composer search). *Mitigation:* the contract addition lands with documentation in CHANGELOG (WP05).

---

## WP02 — `SqlEntityQuery` filter, count, cursor + unit tests

**Priority:** P0 (the core of the mission).
**Depends on:** WP01
**Goal:** Replace `accessCheck()`'s no-op stub with a real per-row filter against `EntityAccessHandler::check('view', ...)`. Implement post-filter `count()`, unfiltered-window cursor parity, and the security-critical throw when `accessCheck=true` and no account is bound.
**Independent test:** `composer test --filter SqlEntityQueryAccessCheck` covers allow / deny / mixed / count / cursor / missing-account paths. All gates green.
**Estimated prompt size:** ~420 lines.
**Prompt:** [tasks/WP02-sql-entity-query-filter.md](tasks/WP02-sql-entity-query-filter.md)

**Subtasks:**
- [ ] T004 `SqlEntityQuery` internal state + `setAccount()` + real `accessCheck(bool)` (WP02)
- [ ] T005 Inject `EntityAccessHandler` via lazy DI resolve (WP02)
- [ ] T006 `execute()` rewrite — hydrate, check, throw, return surviving IDs (WP02)
- [ ] T007 `count()` post-filter cardinality (WP02)
- [ ] T008 `range()` unfiltered-window cursor (WP02)
- [ ] T009 Unit tests covering the matrix (WP02)

**Risks:**
- Performance regression on large pages (NFR-002): *Mitigation:* per-row check is in-memory; the unit test asserts the < 100 ms threshold for a 25-row page.
- Hidden side effect from hydrating before returning: *Mitigation:* the hydration was happening anyway (consumers call `loadMultiple()` next); we're just doing it inside the query.

---

## WP03 — Call-site sweep across 7 packages

**Priority:** P0 (without the sweep, `accessCheck=true` becomes a default that throws because no caller has bound the account yet).
**Depends on:** WP02
**Goal:** Thread the initiator account into every `getQuery()` consumer across `packages/oidc`, `packages/relationship`, `packages/ai-vector`, `packages/graphql`, `packages/genealogy`, `packages/workflows`, `packages/api`. Each existing `accessCheck(false)` is audited: kept for legitimate system contexts, removed for user-facing controllers.
**Independent test:** `composer test` 100% green after the sweep — no controller regresses, no integration test that depended on the old no-op behaviour breaks (and if it does, the broken test was wrong).
**Estimated prompt size:** ~470 lines.
**Prompt:** [tasks/WP03-call-site-sweep.md](tasks/WP03-call-site-sweep.md)

**Subtasks:**
- [ ] T010 Sweep `packages/oidc/src/ClientRegistry/` (WP03)
- [ ] T011 Sweep `packages/relationship/src/` (WP03)
- [ ] T012 Sweep `packages/ai-vector/src/` (WP03)
- [ ] T013 Sweep `packages/graphql/src/Resolver/EntityResolver.php` (WP03)
- [ ] T014 Sweep `packages/genealogy/src/` (WP03)
- [ ] T015 Sweep `packages/workflows/src/DomainValidationListener.php` (WP03)
- [ ] T016 Sweep `packages/api/src/JsonApiController.php` (WP03)

**Risks:**
- A consumer can't statically know the account at call time (e.g. a background context that runs ad-hoc): *Mitigation:* such call sites either get an explicit `accessCheck(false)` (documented in the WP05 audit) or thread a service account through. WP03 captures the disposition for each site.
- Behavior change in GraphQL count queries: *Mitigation:* this is the *intended* outcome — leaking unfiltered cardinality is the bug we're fixing. Integration tests in WP04 lock the new behavior.

---

## WP04 — Integration tests

**Priority:** P1 (proves the end-to-end contract holds across consumers).
**Depends on:** WP03
**Goal:** Five integration tests that exercise the filter through three real consumer paths (JSON:API, GraphQL, agent runtime via `EntitySearchTool`) plus the bypass and anonymous edge cases.
**Independent test:** All five tests green against `DBALDatabase::createSqlite()`.
**Estimated prompt size:** ~310 lines.
**Prompt:** [tasks/WP04-integration-tests.md](tasks/WP04-integration-tests.md)

**Subtasks:**
- [ ] T017 `ListingFilterTest` (JSON:API index) (WP04)
- [ ] T018 `GraphQLResolverFilterTest` (WP04)
- [ ] T019 `BypassRespectsSystemContextTest` (WP04)
- [ ] T020 `AnonymousAccountFilterTest` (WP04)
- [ ] T021 `AdminBypassCapabilityTest` (WP04)

**Risks:**
- Tests over-assert SQL shape: *Mitigation:* assert on observable behaviour (which rows are returned, what counts) — not on internal query shape.
- Fixture brittleness: *Mitigation:* tests use minimal-shape policies that compare account ID against a fixture-bound owner field; no role hierarchy.

---

## WP05 — Stub removal, docs, bypass audit

**Priority:** P2 (wrap-up).
**Depends on:** WP04
**Goal:** Update the access-control spec, file the bypass-audit document classifying every remaining `accessCheck(false)` call site, and add the CHANGELOG bullets.
**Independent test:** Drift detector quiet; new audit doc renders with one classification per remaining site; CHANGELOG `[Unreleased]` shows Added / Changed / Security sections.
**Estimated prompt size:** ~240 lines.
**Prompt:** [tasks/WP05-docs-and-bypass-audit.md](tasks/WP05-docs-and-bypass-audit.md)

**Subtasks:**
- [ ] T022 Update `docs/specs/access-control.md` (WP05)
- [ ] T023 File `docs/security/sql-entity-query-access-check-bypass-audit.md` (WP05)
- [ ] T024 `CHANGELOG.md` `[Unreleased]` bullets (WP05)

**Risks:**
- Audit doc bit-rots if more `accessCheck(false)` calls are added without updating it: *Mitigation:* the doc names the grep command (`grep -rn "accessCheck(false)" packages/`) for future audits; a follow-up could add a CI grep to enforce.

---

## MVP scope

**Minimum viable filter:** WP01 + WP02. That gives `SqlEntityQuery::accessCheck(true)` real teeth. Without WP03, calling code that hasn't been updated will hit `MissingQueryAccountException` on `execute()` — that's actually the *correct* fail-closed behaviour, but it would also break every existing controller / resolver until WP03 lands.

**MVP for actually deployable**: WP01 + WP02 + WP03. WP04 + WP05 verify and document but aren't strictly load-bearing.

## Quality gates (every WP)

`composer test` · `composer cs-check` · `composer phpstan` · `bin/check-package-layers` · `bin/check-dead-code` · `bin/check-composer-policy`.

## Next step

`spec-kitty agent mission finalize-tasks --mission sql-entity-query-access-checking-01KRYP15 --json` to parse dependencies into WP frontmatter, compute lanes, and commit the manifest.
