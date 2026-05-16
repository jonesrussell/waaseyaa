---
work_package_id: WP04
title: ContextRegistry + ContextResolver + canonical ContextNames
dependencies: []
requirement_refs:
- FR-035
- FR-036
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T016
- T017
- T018
- T019
- T020
history: []
authoritative_surface: packages/cache/
execution_mode: code_change
owned_files:
- packages/cache/src/ContextRegistry.php
- packages/cache/src/ContextResolver.php
- packages/cache/src/ContextNames.php
- packages/cache/tests/Unit/ContextRegistryTest.php
- packages/cache/tests/Unit/ContextResolverTest.php
- packages/foundation/src/Http/RequestContext.php
- packages/foundation/tests/Unit/Http/RequestContextTest.php
tags: []
agent: "claude:sonnet:python-implementer:implementer"
shell_pid: "21827"
---

## Objective

Build the cache-context architecture half of the cache-layer additions: `ContextRegistry` (canonical-name whitelist), `ContextResolver` (deterministic resolution of context name → canonical string for the current request), `ContextNames` (string constants for the canonical surface). This is consumed by `ListingResolver` (WP05) and `ListingCacheKeyBuilder` (WP06) to make cache keys deterministic and contexts whitelisted.

## Context

- Layer 0 — cache package. Sibling to WP03; can be developed in parallel.
- `RequestContext` (from `packages/foundation`) provides the source-of-truth state (roles, account id, langcode, query params).
- Canonical context vocabulary documented in `contracts/context-architecture.md`.
- Stability: all 3 classes are charter §5.Y stable surface.

## Subtask details

### T016 — `ContextNames` string constants

**Steps:**
1. Create `packages/cache/src/ContextNames.php`:
   ```php
   namespace Waaseyaa\Cache;
   final class ContextNames
   {
       public const USER_ROLES         = 'user.roles';
       public const USER_ID            = 'user.id';
       public const LANGUAGE_CONTENT   = 'language.content';
       public const LANGUAGE_INTERFACE = 'language.interface';
       public const URL_QUERY_PREFIX   = 'url.query.';
   }
   ```

**Files:** `packages/cache/src/ContextNames.php` (new, ~20 lines).

### T017 — `ContextRegistry`

**Purpose:** Whitelist of registered context names. Listing pipeline consults this before resolving — unknown names bypass cache for that resolution.

**Steps:**
1. Create `packages/cache/src/ContextRegistry.php`:
   - `final class ContextRegistry`
   - Internal: `private array $known = []` (`array<non-empty-string, true>`)
   - Constructor: seed with canonical names from `ContextNames` constants. Special handling for `URL_QUERY_PREFIX`: register the literal `'url.query.*'` sentinel; `has()` does prefix-match against `'url.query.'` strings.
   - `public function register(string $name): void`:
     - Validate format: `/^[a-z][a-z0-9_.]*$/` — throws `\InvalidArgumentException` on mismatch
     - Idempotent: re-registering an existing name is a no-op
   - `public function has(string $name): bool`:
     - Returns true if `$name` is in `$known`
     - Returns true if `$name` starts with `'url.query.'` (prefix match — `url.query.<anything>` is canonical)
   - `public function all(): list<non-empty-string>` returns sorted list of known names

**Files:** `packages/cache/src/ContextRegistry.php` (new, ~60 lines).

### T018 — `ContextResolver::resolve()` per canonical name

**Steps:**
1. Create `packages/cache/src/ContextResolver.php`:
   - `final class ContextResolver`
   - Constructor: `__construct(private readonly ContextRegistry $registry, private readonly LoggerInterface $logger = new NullLogger())`
   - Method `public function resolve(string $context, RequestContext $request): string`:
     - If `!$this->registry->has($context)`: log warning + return `''` (caller bypasses cache per FR-035 / R-11)
     - Switch on context name:
       - `'user.roles'` → `sort($request->roles()); return implode(',', $request->roles())` (sort ascending; comma-joined)
       - `'user.id'` → `(string) ($request->accountId() ?? '')` (anonymous → empty)
       - `'language.content'` → `$request->activeLangcode() ?? ''`
       - `'language.interface'` → `$request->interfaceLangcode() ?? ''`
       - starts with `'url.query.'`:
         - Extract param name after prefix
         - `return (string) ($request->getQueryParams()[$param] ?? '')` (URL-decoded once)
     - Default: return `''` (shouldn't reach with proper registry validation)

**Files:** `packages/cache/src/ContextResolver.php` (new, ~80 lines).

**Validation:** Determinism — same `RequestContext` state → same return string across invocations. Test with shuffled role-input order: output unchanged.

### T019 — Unknown context warning-log behavior

**Purpose:** Ensure unknown context names degrade gracefully (cache bypass) instead of failing the listing.

**Steps:**
1. In `ContextResolver::resolve()`, when `!$registry->has($context)`:
   - `$this->logger->warning('Unknown context name in resolver', ['context' => $context])`
   - Return `''` (empty string)
2. Document in class docblock that callers (e.g., `ListingResolver`) check for the empty string and bypass cache writes accordingly.

**Files:** Continued in `ContextResolver.php`.

### T020 — `ContextRegistryTest` + `ContextResolverTest`

**Steps:**
1. Create `packages/cache/tests/Unit/ContextRegistryTest.php`:
   - `seededCanonicalNamesArePresent` (`has('user.roles')` etc.)
   - `urlQueryPrefixMatchesAnyParam` (`has('url.query.page')`, `has('url.query.category')`)
   - `registerAddsNewName`
   - `registerRejectsInvalidFormat` (uppercase, special chars, leading digit → throws)
   - `registerIsIdempotent`
2. Create `packages/cache/tests/Unit/ContextResolverTest.php`:
   - `resolveUserRolesReturnsSortedJoined` (shuffled input → sorted output, deterministic)
   - `resolveUserRolesAnonymousReturnsEmpty`
   - `resolveUserIdAnonymousReturnsEmpty`
   - `resolveLanguageContentReturnsActiveLangcode`
   - `resolveLanguageInterfaceReturnsInterfaceLangcode`
   - `resolveUrlQueryParamReturnsDecoded`
   - `resolveUrlQueryParamMissingReturnsEmpty`
   - `resolveUnknownContextLogsWarningReturnsEmpty` (assert log call via test logger)
   - `resolveDeterministicAcrossInvocations` (same input → same output, twice)

**Files:** Tests (~220 lines total).

## Test strategy

Unit tests only. `RequestContext` is mocked or constructed inline (it's a value object — easy to construct in tests).

## Definition of Done

- [ ] All 5 owned files exist
- [ ] `ContextRegistry` seeds all 4 canonical names + `url.query.*` prefix match
- [ ] `ContextResolver::resolve()` handles every canonical name + unknown-name graceful bypass
- [ ] Unit tests cover all positive + negative cases
- [ ] `composer cs-check` + `composer phpstan` green
- [ ] `bin/check-package-layers` green

## Risks

| Risk | Mitigation |
|---|---|
| `RequestContext` API drift — e.g., `roles()` returns differently than expected | Read current `RequestContext` source in `packages/foundation` and pin the resolver to it; if `RequestContext` lacks any needed accessor, surface as a blocker before coding |
| Determinism violated by `RequestContext` returning unsorted role list | Resolver itself sorts; the test pins this via shuffled-input assertion |
| Future canonical names need to be added → API churn | `ContextRegistry::register()` allows extension packages to declare their own names; the seeded set is the v0.x lower bound |

## Reviewer guidance

- Verify the canonical seeded set matches `contracts/context-architecture.md` (5 entries: 4 names + `url.query.*` prefix).
- Verify `resolveUserRolesReturnsSortedJoined` actually shuffles its input before passing to the resolver — otherwise it's not pinning determinism.
- Verify the unknown-context path logs at `warning` level, not `error` (graceful degradation, not failure).
- Verify the warning log message includes the unknown context name for debugging.

## Implementation command

```bash
spec-kitty agent action implement WP04 --agent <name>
```

## Activity Log

- 2026-05-16T19:24:21Z – claude:sonnet:python-implementer:implementer – shell_pid=17471 – Started implementation via action command
- 2026-05-16T19:31:58Z – claude:sonnet:python-implementer:implementer – shell_pid=17471 – WP04 ready: ContextNames + ContextRegistry + ContextResolver + RequestContext scaffold. 28 unit tests. All quality gates green. (--force used: unrelated WP12 review artifact untracked in kitty-specs/).
- 2026-05-16T19:32:33Z – claude:opus:python-reviewer:reviewer – shell_pid=19637 – Started review via action command
- 2026-05-16T19:41:15Z – claude:sonnet:python-implementer:implementer – shell_pid=21827 – Started implementation via action command
