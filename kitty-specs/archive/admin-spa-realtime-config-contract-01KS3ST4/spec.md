# Admin SPA — Realtime Config Contract

**Mission:** `admin-spa-realtime-config-contract-01KS3ST4`
**Status:** Spec
**Target branch:** `main`
**Closes:** #1537, #1538

## Why this mission exists

Two admin SPA bugs from the post-#1535 follow-on are not isolated incidents — each reveals a **contract-level gap** in how the SPA composables and runtime config are consumed. A `String(x) === '1'` workaround in `SchemaList.vue` (and the matching workaround that issue #1538 references) and a `String(config.public.enableRealtime)` coercion guard already exist in the SPA. Those workarounds are bug-class indicators, not real fixes. The next contributor will write the same untyped string-compare pattern and hit the same coercion bug.

### Concrete state at mission start

1. **`useSchema()` deduplication race (#1537).** `packages/admin/app/composables/useSchema.ts:7` declares a module-level `schemaCache: Map<string, EntitySchema>`. `fetch()` checks the cache on entry (line 15) and returns immediately on hit. But if **two callers** call `fetch()` concurrently before either has populated the cache, both miss, both fire the POST to `/admin/_surface/{type}/action/schema`, both write to the cache. Two callers exist in production: `pages/[entityType]/index.vue` (line 73, `onMounted`) and the child `SchemaList.vue` (line 154–156, `onMounted`). Each fresh visit to `/admin/{entityType}` issues two schema POSTs that race. The cache is structurally correct; the race is at request issue time. The fix is to dedup at the request level — track an in-flight `Promise<EntitySchema>` per entityType.

2. **Digit-string env var coercion (#1538).** `packages/admin/nuxt.config.ts` declares `enableRealtime: process.env.NUXT_PUBLIC_ENABLE_REALTIME ?? ...`. Nuxt's runtime-config serializer sees a string value `"1"` and **coerces it to the number `1`** at runtime, so `config.public.enableRealtime` is `1`, not `"1"`. The downstream check `config.public.enableRealtime === '1'` then silently evaluates to `false`. PR #1535 patched the symptom with `String(config.public.enableRealtime) === '1'` in `SchemaList.vue:19`, but `nuxt.config.ts` shows the same fragile shape applied to **`requireVerifiedEmail`** (`process.env.NUXT_PUBLIC_AUTH_REQUIRE_VERIFIED_EMAIL === '1'`) and any future digit-string flag is a regression-in-waiting. The fix is a single typed envelope: a `useAdminConfig()` composable that reads from `useRuntimeConfig()`, coerces each known flag to its target TypeScript type, and exposes a typed `AdminConfig` object the SPA consumes everywhere. No more `String(x) === '1'` at call sites.

The mission's contract: after merge, `useSchema()` never issues a duplicate POST for the same entity type, regardless of how many concurrent `fetch()` callers exist. Every admin SPA runtime-config consumer reads from `useAdminConfig()` (or its typed counterpart for non-public config), and `String(x) === '1'` does not appear anywhere in the SPA. Adding a new boolean flag is a one-line edit in the typed envelope, not a coercion gauntlet at every call site.

## User scenarios

### Primary flow: a fresh page load issues exactly one schema fetch

1. User navigates to `/admin/node`.
2. The page wrapper (`pages/[entityType]/index.vue`) mounts and calls `useSchema('node').fetch()`.
3. The child `SchemaList.vue` mounts during the same tick and also calls `useSchema('node').fetch()`.
4. The composable's request-dedup logic sees the in-flight `Promise` from call #2 and returns it to call #3 instead of issuing a second POST.
5. Network panel shows **one** request to `/admin/_surface/node/action/schema`. Both callers receive the same schema, the cache populates once, and subsequent navigations within the SPA hit the cache.

### Primary flow: setting a realtime flag works regardless of value shape

1. Operator runs `NUXT_PUBLIC_ENABLE_REALTIME=1 npm run build`.
2. The build serializes the env var into runtime config; the Nuxt serializer coerces `"1"` to `1` (number) — unchanged behavior.
3. The new `useAdminConfig()` composable reads `runtimeConfig.public.enableRealtime`, sees the number, and normalizes via a single coercion rule (`Boolean(x) || x === '1' || x === 'true' || x === 1`).
4. `useAdminConfig().enableRealtime` returns `true` — a typed `boolean`, regardless of whether the env var was passed as `1`, `'1'`, `'true'`, `1`, or `true`.
5. `SchemaList.vue` and other consumers read `useAdminConfig().enableRealtime` directly. No string compares, no coercion shims at the call site.

### Recovery flow: a developer adds a new digit-string flag without bugs

1. Developer adds `NUXT_PUBLIC_ENABLE_FOO` to `nuxt.config.ts`.
2. They register it once in `useAdminConfig()`'s coercion table: `enableFoo: asBoolean(runtimeConfig.public.enableFoo)`.
3. They consume it as `useAdminConfig().enableFoo` everywhere.
4. The TypeScript compiler enforces consumers cannot accidentally string-compare against the typed boolean.
5. Whether the operator sets `NUXT_PUBLIC_ENABLE_FOO=1`, `=true`, `=yes`, or `=enabled`, the developer's coercion rule determines behavior — explicit, testable, single source.

### Edge cases

- **`useSchema()` invalidate during in-flight request.** A caller calls `invalidate('node')` while a fetch is mid-flight. The next `fetch('node')` call gets a fresh request, not the stale Promise. The dedup tracker is keyed by entityType and cleared on invalidate.
- **Concurrent fetches for different entity types.** `fetch('node')` and `fetch('user')` are independent; each tracks its own in-flight Promise.
- **Fetch rejection.** If the in-flight Promise rejects, the next caller does NOT receive the rejected Promise — the dedup tracker clears on rejection, and the next call issues a fresh request. This avoids permanent-failure caching.
- **Runtime config absent flag.** A consumer reads `useAdminConfig().someFlag` for a flag the operator did not set. The coercion table returns the documented default. No `undefined` leaks to consumers.
- **Non-public config.** The pattern extends to non-public runtime config (`runtimeConfig.foo` without `.public`) only if a need surfaces. The mission's WP scope is public config; non-public is a follow-up.

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | `useSchema(entityType)` deduplicates concurrent `fetch()` calls for the same entityType: a second `fetch()` invoked while an in-flight Promise exists returns that Promise instead of issuing a new HTTP request. |
| FR-002 | Mandatory | The dedup tracker is module-scoped, keyed by entityType, and clears entries on Promise rejection (so a failed fetch does not poison subsequent calls). |
| FR-003 | Mandatory | `useSchema(entityType).invalidate()` clears both the cached schema and any in-flight Promise for that entityType. A subsequent `fetch()` issues a fresh request. |
| FR-004 | Mandatory | A new composable `useAdminConfig()` exists at `packages/admin/app/composables/useAdminConfig.ts`. It reads from `useRuntimeConfig()` and returns a typed `AdminConfig` object. Field-level coercion uses helper functions (`asBoolean`, `asString`, `asUrl`, etc., as needed). |
| FR-005 | Mandatory | All currently-defined `runtimeConfig.public.*` keys in `nuxt.config.ts` (`enableRealtime`, `appName`, `docsUrl`, `baseUrl`, `auth.registration`, `auth.requireVerifiedEmail`) are exposed via `useAdminConfig()` with their documented target types. |
| FR-006 | Mandatory | Every existing SPA call site that reads `config.public.*` directly (including `SchemaList.vue:19`, `nuxt.config.ts` internal `=== '1'` checks if any, and any other `useRuntimeConfig` consumer) is migrated to `useAdminConfig()`. The `String(x) === '1'` workaround pattern is removed. |
| FR-007 | Mandatory | `asBoolean()` accepts the following truthy inputs: `true`, `1`, `'1'`, `'true'`, `'yes'`, `'on'` (case-insensitive). All other inputs are `false`. Documented in the helper's JSDoc. |
| FR-008 | Mandatory | Unit test: `useSchemaTest::doesNotDuplicateConcurrentFetches` asserts that two concurrent `fetch()` calls for the same entityType issue exactly one HTTP request and both resolve to the same schema. |
| FR-009 | Mandatory | Unit test: `useSchemaTest::clearsInflightOnInvalidate` asserts FR-003. |
| FR-010 | Mandatory | Unit test: `useSchemaTest::doesNotPoisonOnRejection` asserts FR-002's rejection-clearing. |
| FR-011 | Mandatory | Unit test: `useAdminConfigTest` covers each registered flag's coercion: `asBoolean` with all truthy inputs, all falsy inputs, undefined defaults; `asString` with empty / undefined fallbacks; etc. |
| FR-012 | Mandatory | Integration test (or Playwright spec) under `packages/admin/e2e/` boots the dev server, visits `/admin/node`, and asserts via network capture that exactly one schema POST is issued per fresh visit. |
| FR-013 | Mandatory | Documentation: `packages/admin/README.md` (or equivalent) gains a "Runtime config" section explaining the `useAdminConfig()` contract and the rationale for not consuming `useRuntimeConfig()` directly. |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | The dedup tracker adds zero measurable overhead to `useSchema().fetch()` on cache-hit (still synchronous return); ≤ 1 ms overhead on cache-miss (just a Map set/get). |
| NFR-002 | Mandatory | `useAdminConfig()` is composable-cached such that repeated calls within one component tree do not re-run coercion. The composable's return value is referentially stable within a single component tree's lifecycle. |
| NFR-003 | Mandatory | TypeScript build (`cd packages/admin && npm run build`) succeeds with strict type-checking. The `AdminConfig` type does not have any `any` or `unknown` fields. |
| NFR-004 | Mandatory | No `String(x) === '1'` or analogous coercion patterns appear in `packages/admin/app/**/*.{ts,vue}` after this mission. Verified by a `bin/check-admin-coercion-patterns` script (or equivalent CI step) added in WP04. |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | No changes to the public surface of the `/admin/_surface/{type}/action/schema` HTTP endpoint or its response shape. The fix is composable-side only. |
| C-002 | Mandatory | No changes to `nuxt.config.ts`'s declared keys — the env var contract operators rely on is preserved. The fix layers a typed envelope over the existing runtime config. |
| C-003 | Mandatory | The merge commit closes #1537 and #1538 via `Closes #N` footer. |
| C-004 | Mandatory | The mission preserves the SPA's existing Nuxt 3 + Vue 3 + TypeScript stack. No new dependencies (no zod, no superstruct) unless WP01 demonstrates the framework itself's typing is insufficient — and even then the planner consults before adding. Native TypeScript + a small custom helper file is the expected shape. |
| C-005 | Mandatory | `composer verify` plus `cd packages/admin && npm run build && npm test && npm run lint` are all green on the merge commit. |
| C-006 | Mandatory | No CI hooks bypassed during this mission's PRs. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | Two concurrent `useSchema('node').fetch()` calls issue exactly one HTTP request. | Unit test `useSchemaTest::doesNotDuplicateConcurrentFetches` passes (FR-008). |
| SC-002 | A fresh `/admin/node` page load issues exactly one schema POST. | Playwright spec under `packages/admin/e2e/` asserts via network capture (FR-012). |
| SC-003 | `NUXT_PUBLIC_ENABLE_REALTIME=1` enables realtime SSE regardless of digit-string coercion. | Unit test `useAdminConfigTest::asBooleanTruthyInputs` passes (FR-011). |
| SC-004 | No `String(x) === '1'` patterns remain in `packages/admin/app/`. | `bin/check-admin-coercion-patterns` returns zero matches (NFR-004). |
| SC-005 | TypeScript build is strict-clean. | `cd packages/admin && npm run build` passes (NFR-003). |
| SC-006 | `composer verify` is green on the merge commit. | CI status check `verify` passes. |
| SC-007 | Issues #1537 and #1538 close on merge. | GitHub auto-closes via `Closes #N` footer. |

## Key entities

| Entity | Role | Net change in this mission |
|---|---|---|
| `packages/admin/app/composables/useSchema.ts` | Schema fetch composable. | Edit: add in-flight Promise tracker; clear on invalidate + rejection. |
| `packages/admin/app/composables/useAdminConfig.ts` (new) | Typed runtime-config envelope. | +1 file. |
| `packages/admin/app/types/AdminConfig.ts` (or co-located) | The typed `AdminConfig` shape. | +1 file or inline in composable. |
| Coercion helpers (`asBoolean`, `asString`, etc.) | Small pure functions for boundary coercion. | +1 file or co-located. |
| All call sites consuming `useRuntimeConfig().public.*` | Migrated to `useAdminConfig()`. | Edits across `packages/admin/app/**`. |
| `bin/check-admin-coercion-patterns` (new) | CI gate for residual coercion patterns. | +1 file (small shell or PHP script that greps the SPA). |
| Unit tests (3+ files) + Playwright spec (1 file) | New regression coverage. | +4 files (or edits). |
| `packages/admin/README.md` | Documentation for `useAdminConfig()` contract. | Edit. |
| `CHANGELOG.md` | `[Unreleased]` entry. | Edit. |

## Assumptions

- The framework's existing test infrastructure (Vitest with `@nuxt/test-utils`) supports the concurrency tests in FR-008..FR-010. If a test pattern is needed that Vitest cannot express, the planner uses Playwright in `packages/admin/e2e/` instead — both are already wired.
- The list of `runtimeConfig.public.*` keys in `nuxt.config.ts` does not grow during this mission's lifetime. If new keys are added in parallel work, WP03's migration sweep covers whatever is present at the time of WP03's execution.
- The `bin/check-admin-coercion-patterns` script is a small grep-based regex check, not an AST analyzer. It is documented as best-effort — if it ever surfaces a false positive (a legitimate `String(x) === '1'` outside the SPA's runtime-config consumption surface), the planner adds an inline `// allow-coercion: <reason>` comment to suppress and documents the convention.
- Nuxt's runtime-config behavior (digit-string coercion to numbers) is a Nuxt-internal serializer behavior we cannot disable. The typed envelope is the framework-level fix; we do not attempt to patch Nuxt itself.

## Out of scope

- Refactoring the `/admin/_surface/*` HTTP surface or schema response shape.
- Server-side schema caching changes.
- Migrating non-public runtime config consumers (currently none in the SPA, AFAICT).
- Adding new public runtime-config keys (none added by this mission).
- Replacing Nuxt's runtime config with a different config system.
- Refactoring `useSchema()`'s cache semantics beyond the dedup race fix.
- E2E coverage for every page that consumes `useAdminConfig()` — the FR-012 test covers the regression-prone path; broader coverage is a follow-up.

## WP outline (for /spec-kitty.plan)

The planner is free to revise. Indicative shape:

- **WP01 — `useSchema()` dedup.** Add the in-flight Promise tracker. Wire invalidate + rejection clearing. Unit tests (FR-008..FR-010). Closes #1537.
- **WP02 — `useAdminConfig()` composable + helpers.** Define the typed envelope. Implement coercion helpers (`asBoolean`, `asString`, others as needed). Unit tests (FR-011). Documentation (FR-013).
- **WP03 — Call-site migration.** Sweep `packages/admin/app/**` for every `useRuntimeConfig()` and `config.public.*` consumer; migrate to `useAdminConfig()`. Remove `String(x) === '1'` workarounds. Closes #1538.
- **WP04 — Coercion-pattern CI gate + Playwright regression.** Add `bin/check-admin-coercion-patterns` and wire it into the admin SPA's CI step. Add the Playwright network-capture spec (FR-012). Wrap-up: `CHANGELOG.md`, `packages/admin/README.md` finalization. Full verification.

## References

- Issue #1537 body: identifies the dual `onMounted` race in `pages/[entityType]/index.vue:73` + `SchemaList.vue:154-156`.
- Issue #1538 body: identifies the Nuxt runtime-config digit-string coercion and the `String(...) === '1'` workaround landed in #1535.
- `useSchema.ts:7-34` — the current cache + fetch + invalidate surface.
- `nuxt.config.ts` `runtimeConfig.public` block — the keys WP03 migrates.
- `SchemaList.vue:16-19` — the existing workaround the mission removes.
- CLAUDE.md § "Admin SPA: Nuxt 3 + Vue 3 + TypeScript. Composables in `packages/admin/app/composables/`" (gotcha source).
- Memory: `feedback_modern_php_rules.md` adapted to TypeScript-side reasoning — typed boundaries, no implicit coercion at consumption sites (FR-004 motivation).
- Memory: `feedback_regression_tests.md` — always write regression tests (FR-008..FR-012).
