# Data Model: Admin SPA — Realtime Config Contract

**Mission**: `admin-spa-realtime-config-contract-01KS3ST4`
**Date**: 2026-05-20

---

## TypeScript Types

### `AdminConfig` (new)

```typescript
// packages/admin/app/composables/useAdminConfig.ts (or types/AdminConfig.ts)

export interface AdminConfig {
  readonly enableRealtime: boolean
  readonly appName: string
  readonly docsUrl: string
  readonly baseUrl: string
  readonly logoUrl: string | undefined
  readonly auth: {
    readonly registration: string   // e.g. 'admin' | 'open' | 'invite'
    readonly requireVerifiedEmail: boolean
  }
}
```

**Constraints**:
- No `any` or `unknown` fields (NFR-003)
- All fields have documented defaults (FR: "absent flag returns the documented default")

### `asBoolean` helper signature

```typescript
// packages/admin/app/utils/configCoercion.ts

/**
 * Coerces a runtime-config value to a boolean.
 * Truthy: true, 1, '1', 'true', 'yes', 'on' (case-insensitive).
 * All other inputs → false.
 * @api
 */
export function asBoolean(value: unknown, defaultVal = false): boolean

/**
 * Coerces a runtime-config value to a string, using defaultVal if absent/null/undefined.
 */
export function asString(value: unknown, defaultVal: string): string

/**
 * Coerces a runtime-config value to a URL string.
 * Strips trailing slash. Falls back to defaultVal if absent.
 */
export function asUrl(value: unknown, defaultVal: string): string

/**
 * Coerces a runtime-config value to a string | undefined.
 * Returns undefined if value is absent/null/empty string.
 */
export function asOptionalString(value: unknown): string | undefined
```

### `useSchema` module-level state (updated)

```typescript
// packages/admin/app/composables/useSchema.ts (existing, edited)

// Existing:
const schemaCache: Map<string, EntitySchema> = new Map()

// Added (WP01):
const inflightCache: Map<string, Promise<EntitySchema>> = new Map()
```

**Invariants**:
- A key in `inflightCache` means a POST is currently in-flight for that entityType
- A key in `schemaCache` means the result is available synchronously
- `invalidate(entityType)` removes from both maps
- On rejection: key removed from `inflightCache`; `schemaCache` not written

---

## State Transitions: `useSchema(entityType).fetch()`

```
[Cache hit in schemaCache]  ──────────────────────────────────→  return cached schema (sync)
[In-flight hit in inflightCache] ──────────────────────────────→  return existing Promise
[Cache miss + no in-flight]  →  create Promise  →  store in inflightCache
                                                          │
                                    ┌─────────────────────┴──────────────────────┐
                                    ▼ resolve                                     ▼ reject
                             write to schemaCache                     delete from inflightCache
                             delete from inflightCache                (next fetch issues fresh request)
                             return schema
```

---

## No API Contract Changes

- `/admin/_surface/{type}/action/schema` endpoint: unchanged (C-001)
- `nuxt.config.ts` declared keys: unchanged (C-002)
- No new PHP packages or PHP-side changes

---

## File Count Summary

| Category | Count |
|----------|-------|
| New TypeScript files | 3 (useAdminConfig.ts, configCoercion.ts, AdminConfig.ts — may be 2 if types are co-located) |
| Edited composables | 1 (useSchema.ts) |
| Edited call-site files | 14 |
| New test files | 3 (useSchema.test.ts additions, useAdminConfig.test.ts, configCoercion.test.ts) |
| New E2E spec | 1 (schema-dedup.spec.ts) |
| New bin script | 1 (check-admin-coercion-patterns) |
| Edited docs/config | 2 (README.md, CHANGELOG.md) |
| **Total files touched** | **~25** |
