# Research: Admin SPA — Realtime Config Contract

**Mission**: `admin-spa-realtime-config-contract-01KS3ST4`
**Date**: 2026-05-20
**Status**: Complete — no open unknowns

---

## Decision Log

### D-001: In-flight Promise dedup pattern for `useSchema()`

**Decision**: Add a module-scoped `Map<string, Promise<EntitySchema>>` (`inflightCache`) alongside the existing `schemaCache: Map<string, EntitySchema>`. Before issuing a `fetch()` POST, check `inflightCache`; if an entry exists, return it. On resolution, move result to `schemaCache` and delete from `inflightCache`. On rejection, delete from `inflightCache` (do not cache error). `invalidate()` clears both maps.

**Rationale**: The existing `schemaCache` is structurally correct but only prevents duplicate requests once the first has resolved. The race is at request-issue time — both callers check the cache simultaneously on the same microtask tick, both miss, both fire the POST. Tracking the in-flight Promise closes this window with minimal code (< 15 LOC change to `useSchema.ts`). No external library needed.

**Alternatives considered**:
- A shared `ref<boolean>` "loading" flag per entityType: insufficient — a flag cannot be awaited by the second caller.
- A synchronization primitive from a third-party library (e.g., `p-limit`, `async-mutex`): rejected per C-004 (no new deps).
- Moving the cache to a Pinia store: out of scope (the module-level Map is correct architecture; the race is a single-file fix).

---

### D-002: Typed config envelope (`useAdminConfig()`)

**Decision**: A new composable `packages/admin/app/composables/useAdminConfig.ts` that:
1. Calls `useRuntimeConfig()` once.
2. Passes each known `public.*` key through a coercion helper (`asBoolean`, `asString`, `asUrl`).
3. Returns a `readonly AdminConfig` object backed by `useState()` for referential stability (NFR-002).

Coercion helpers live in `packages/admin/app/utils/configCoercion.ts` (pure functions, no Vue imports — independently testable).

**Rationale**: Nuxt's runtime-config serializer is documented to coerce digit-string env vars to numbers. This is a Nuxt-internal behavior we cannot disable (spec Assumption 4). The typed envelope is the idiomatic Nuxt 3 mitigation: centralize coercion once, expose typed fields, let TypeScript enforce call sites.

**Alternatives considered**:
- `zod` schema parsing: type-safe, but adds a dependency (C-004 forbids new deps without strong justification; native TypeScript achieves the same for a handful of known keys).
- `superstruct`: same objection.
- Patching `nuxt.config.ts` to use booleans directly: env vars are strings; Nuxt would still serialize the runtime config. The type annotation in `nuxt.config.ts` does not prevent the serializer coercion.
- Keeping `String(x) === '1'` and documenting it: explicitly rejected by spec (NFR-004) — the workaround is a bug-class indicator, not a fix.

---

### D-003: `asBoolean` truthy set

**Decision**: `true`, `1`, `'1'`, `'true'`, `'yes'`, `'on'` (case-insensitive). All other inputs → `false`.

**Rationale**: FR-007 specifies this set explicitly. The set covers the most common operator patterns for boolean env vars (`=1`, `=true`, `=yes`, `=on`). Case-insensitivity (`'TRUE'`, `'YES'`) is good UX with no security downside for config flags.

---

### D-004: `bin/check-admin-coercion-patterns` implementation approach

**Decision**: Shell grep script (`bash`). Pattern: `grep -rn "String(.*) === '1'\|String(.*) === \"1\"\|String(.*) === \`1\`" packages/admin/app/`. Exits 1 on match. Documents `// allow-coercion: <reason>` inline suppression convention. Called from CI (or added to `composer verify` / npm `check` script).

**Rationale**: Spec Assumption 3 explicitly states the script should be "a small grep-based regex check, not an AST analyzer." Shell grep is sufficient for the stated pattern, zero dependencies, runs in < 1 second.

---

### D-005: Playwright E2E scope (FR-012)

**Decision**: A single spec in `packages/admin/e2e/schema-dedup.spec.ts`. Boots dev server (already wired via `npm run test:e2e`). Visits `/admin/node`. Intercepts all network requests. Asserts exactly one POST matching `*/admin/_surface/node/action/schema` per fresh page load.

**Rationale**: FR-012 is specifically a network-capture assertion — it can only be done reliably at the E2E layer where the actual HTTP stack is exercised. Vitest mocks would not catch a real double-fetch regression in the browser. Playwright is already wired (`packages/admin/e2e/`, `npm run test:e2e`).

---

## Open Questions

None. All spec assumptions are confirmed resolvable within existing stack.
