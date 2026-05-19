---
work_package_id: WP03
title: Call-site sweep across 7 packages
dependencies:
- WP02
requirement_refs:
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-sql-entity-query-access-checking-01KRYP15
base_commit: 22337d0fd7ded09e8ac80c88c88b35df0bef8a51
created_at: '2026-05-19T00:35:26.349061+00:00'
subtasks:
- T010
- T011
- T012
- T013
- T014
- T015
- T016
shell_pid: "465581"
agent: "claude:sonnet:implementer:implementer"
history:
- date: '2026-05-18T23:44:03Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/api/src/JsonApiController.php
execution_mode: code_change
owned_files:
- packages/oidc/src/ClientRegistry/OidcClientSeeder.php
- packages/oidc/src/ClientRegistry/OidcClientLookup.php
- packages/relationship/src/RelationshipValidator.php
- packages/relationship/src/RelationshipDeleteGuardListener.php
- packages/ai-vector/src/SemanticIndexWarmer.php
- packages/ai-vector/src/SearchController.php
- packages/graphql/src/Resolver/EntityResolver.php
- packages/genealogy/src/Ssr/GenealogySsrController.php
- packages/genealogy/src/Service/GenealogyFamilyService.php
- packages/genealogy/src/Service/GenealogyPedigreeService.php
- packages/workflows/src/DomainValidationListener.php
- packages/api/src/JsonApiController.php
tags: []
---

# WP03 — Call-site sweep across 7 packages

## Objective

Without this WP, `accessCheck(true)` as the new default would crash every existing controller / resolver / listener that calls `$storage->getQuery()->execute()` without binding an account. WP03 sweeps every consumer:

- For **user-context** call sites (HTTP requests, GraphQL resolvers, agent runtime): bind the request's `_account` (or the GraphQL context user, or the agent's initiator account) via `->setAccount($account)`.
- For **system-context** call sites (background warmers, validators, internal lookups): keep / add `->accessCheck(false)` with a one-line code comment explaining why bypass is correct.

The disposition for each site is documented in [research.md](../research.md) R-004. WP03 implements the disposition; WP05 files the formal audit document.

## Context

- Spec FRs in scope: **FR-008** (sweep call sites; refactor those that forget to thread the account).
- Constraints applied: **C-004** (`accessCheck(false)` preserved as explicit named bypass), **C-006** (null + check enabled MUST throw — making the sweep mandatory before merge).
- Research grounding: R-004 (7 packages, 5 existing `accessCheck(false)` opt-outs).
- Companion deliverable: WP05's `docs/security/sql-entity-query-access-check-bypass-audit.md` — list every remaining `accessCheck(false)` with justification. **WP03 implements; WP05 documents.**

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T010 — `packages/oidc/src/ClientRegistry/`

**Files in scope:**
- `OidcClientSeeder.php:123` — `$ids = $this->storage->getQuery()->...->execute();`
- `OidcClientLookup.php:28` — same pattern

**Classification:** System context. OIDC client registry queries during boot / token issuance — there's no user account to bind, and the registry needs to see all clients to dispatch by `client_id`.

**Steps:**
1. Add `->accessCheck(false)` to each `getQuery()` call before `->execute()`. Place inline:
   ```php
   $ids = $this->storage->getQuery()
       ->accessCheck(false)  // system context: client registry lookup runs pre-auth
       ->condition(...)
       ->execute();
   ```
2. The inline comment explains *why* the bypass is justified. WP05 will catalogue these in the audit doc.

**Validation:**
- [ ] No behaviour change (the queries were already returning all rows under the no-op stub).
- [ ] Existing OIDC tests pass.

---

## Subtask T011 — `packages/relationship/src/`

**Files in scope:**
- `RelationshipValidator.php:272` — validation context
- `RelationshipDeleteGuardListener.php:36`, `:41` — internal delete guard

**Classification:** System context. Both are internal validators that enforce referential integrity; they need to see ALL referenced entities, not just those the current user can view. (A user can't be allowed to violate a foreign-key constraint just because they can't see the related entity.)

**Steps:**
1. Add `->accessCheck(false)` to each call. Inline comment: `// system context: referential-integrity check spans access boundaries`.
2. The DeleteGuardListener listens to entity-delete events; the listener may run inside a system transaction without an `_account`.

**Validation:**
- [ ] Relationship integrity tests pass.
- [ ] No new test failures in `packages/relationship/tests/`.

---

## Subtask T012 — `packages/ai-vector/src/`

**Files in scope:**
- `SemanticIndexWarmer.php:282` — background warmer
- `SearchController.php:173`, `:303` — user-facing search

**Classification (mixed):**
- `SemanticIndexWarmer`: system context. KEEP `accessCheck(false)`. Inline comment: `// system context: index-warming job needs to see all entities to build the embedding store`.
- `SearchController`: **USER-FACING — bind the account.** Drop the `accessCheck(false)` and call `->setAccount($this->currentAccount())` (or whatever the controller's account accessor is — read the class first; if it has access to the request, read `_account` from there).

**Steps:**
1. Open `SemanticIndexWarmer.php:282`. Confirm the call. Add inline comment. No functional change.
2. Open `SearchController.php`. Read the constructor to learn how the request / account is plumbed. Find the controller's `_account` access pattern (mirror `JsonApiController`'s pattern if needed). Replace `->accessCheck(false)` with `->setAccount($account)` at lines 173 and 303.
3. If the controller doesn't currently have account access in scope, plumb it through — likely via a constructor-injected `RequestStack` or `SessionMiddleware`-set attribute.

**Validation:**
- [ ] Background warm test (if exists) still passes.
- [ ] Search controller now returns access-filtered results (verified end-to-end in WP04 `EntitySearchTool` integration test).

**Edge case:**
- If `SearchController` is reached without authentication (anonymous public search), the account is `AnonymousUser` (id=0). That's a valid bound account; the access policy decides what anonymous can see.

---

## Subtask T013 — `packages/graphql/src/Resolver/EntityResolver.php`

**Files in scope:**
- `:65` — `$countQuery = $storage->getQuery()->accessCheck(false);`
- `:81` — `$mainQuery = $applier->apply($parsedQuery, $storage->getQuery()->accessCheck(false));`
- `:211` — `$ids = $storage->getQuery()->condition($keys['uuid'], $id)->execute();` — already user-facing, no `accessCheck(false)`, but no `setAccount()` either; needs the account bound.

**Classification:** USER-FACING. All three should respect the user's access.

**Steps:**
1. Read `EntityResolver.php` to understand how it gets the GraphQL context user. GraphQL resolvers in this framework typically have access to a context array containing the authenticated account.
2. At line 65: drop `accessCheck(false)`; bind the context user: `->setAccount($context->getAccount())` (exact accessor depends on the framework's GraphQL context shape).
3. At line 81: same — drop `accessCheck(false)`; bind. The `$applier->apply(...)` wrapper passes through.
4. At line 211: add `->setAccount($context->getAccount())` to the chain.
5. **Behavior change is intended.** Before: GraphQL count leaked cardinality information. After: count reflects access-filtered cardinality. Integration test in WP04 (`GraphQLResolverFilterTest`) pins this.

**Validation:**
- [ ] `packages/graphql/tests/` green.
- [ ] If any existing test asserts a specific count that's now smaller because of filtering, the test was relying on the bug — fix the test fixture so the asserted user has access to the rows it expects to count.

---

## Subtask T014 — `packages/genealogy/src/`

**Files in scope:**
- `Ssr/GenealogySsrController.php:152`, `:160` — SSR user-facing
- `Service/GenealogyFamilyService.php:27` — service used by SSR
- `Service/GenealogyPedigreeService.php:31`, `:49`, `:225` — pedigree service

**Classification:** Mixed.
- SSR controllers: **user-facing** — bind `_account` from the request.
- Services: depends on whether they're called from a request context or from a background job. Read each carefully.

**Steps:**
1. SSR controller call sites (`:152`, `:160`): bind `->setAccount($account)` where `$account` is read from the request attributes (mirror the `_account` pattern from CLAUDE.md gotcha — controllers MUST use `_account`, not `account`).
2. Services: trace the call graph one level up. If the service is invoked from an HTTP context, accept `?AccountInterface $account` as a method parameter and thread it through. If it's invoked from a background context (e.g. a CLI command or worker), thread `accessCheck(false)` with a comment.
3. If a service is invoked from BOTH contexts, prefer the parameter-driven design: `($account === null) ? accessCheck(false) : setAccount($account)`. Document inline.

**Validation:**
- [ ] Genealogy SSR test suite green.
- [ ] If genealogy has integration tests, they pass with the new filtering.

---

## Subtask T015 — `packages/workflows/src/DomainValidationListener.php`

**Files in scope:**
- `:133` — `$ids = $nodeStorage->getQuery()->...`

**Classification:** System context. Workflow validation listener runs at entity-save time and needs to see the full workflow definition state to validate transitions.

**Steps:**
1. Add `->accessCheck(false)` with inline comment: `// system context: workflow validator runs inside save transaction; needs unrestricted read`.

**Validation:**
- [ ] Workflow tests green.

---

## Subtask T016 — `packages/api/src/JsonApiController.php`

**Files in scope:**
- `:52` — count query in index endpoint
- `:63` — main query in index endpoint
- `:450` — show-related-resources query

**Classification:** USER-FACING. The JSON:API controller is the primary HTTP surface for entity listings. Every call site here MUST bind the request's authenticated account.

**Steps:**
1. Open `JsonApiController.php`. Find how the controller currently reads the authenticated account. Per CLAUDE.md gotcha: `_account` (not `account`) — that's the canonical request attribute key set by `SessionMiddleware`.
2. At each of the 3 call sites, add `->setAccount($request->attributes->get('_account'))` to the chain. Place it after `->getQuery()` and before any conditions / sorts.
3. If the controller already has a helper method that returns the current account (e.g. `$this->currentAccount($request)`), use that.
4. Anonymous requests: `_account` will resolve to `AnonymousUser` (id=0) per the framework's session-middleware contract; the access policy handles anonymous from there.

**Validation:**
- [ ] `packages/api/tests/` green.
- [ ] `tests/Integration/` tests that hit `JsonApiController` endpoints pass with the new filter.

---

## Definition of Done

- [ ] T010..T016 checkboxes flipped — every named file edited.
- [ ] No `getQuery()` call site across the 7 packages remains without either `->setAccount($account)` (user-context) or an explicit `->accessCheck(false)` (system-context) with an inline justification comment.
- [ ] All gates green: cs-check, phpstan, layers, dead-code, composer-policy.
- [ ] Existing test suites for each affected package pass.

## Risks & mitigations

1. **A consumer's account is hard to access at the call site.** *Mitigation:* if a service is reached from multiple contexts, accept `?AccountInterface $account` as a method parameter and let callers decide. Don't try to be clever with global state.
2. **Behavior change in GraphQL count.** *Mitigation:* this is the *intended* outcome of the mission — leaking unfiltered cardinality was the bug. WP04 locks the new behaviour in tests.
3. **Tests fail because they relied on the bug.** *Mitigation:* fix the test's fixture (give the asserting account access to the entities it expects to see). If the test was genuinely asserting "anonymous can see everything", that's wrong — the test was masking the bug.
4. **A new `getQuery()` call site lands during this WP** (e.g. another mission lands in parallel). *Mitigation:* grep `grep -rn -- "->getQuery()" packages/` at the end of WP03 to verify the sweep is complete. Update if needed.

## Reviewer guidance

- For each sub-package edit, verify the classification matches research.md R-004:
  - oidc / relationship / workflows: system bypass with comment.
  - graphql / api / ai-vector SearchController: bind account, drop bypass.
  - ai-vector SemanticIndexWarmer: keep bypass with comment.
  - genealogy: per-call-site judgment; verify each.
- Run `grep -rn "accessCheck(false)" packages/ | grep -v "/tests/"` after the sweep. Every remaining occurrence should have an adjacent inline comment explaining why the bypass is justified. No bare `accessCheck(false)` calls.
- Run `grep -rn -- "->getQuery()" packages/` and audit each result: it should either chain to `accessCheck(false)` or `setAccount(...)`. If neither, that's a bug.

## Implementation command

```
spec-kitty agent action implement WP03 --agent <name>
```

## Activity Log

- 2026-05-19T00:35:28Z – claude:sonnet:implementer:implementer – shell_pid=465581 – Assigned agent via action command
