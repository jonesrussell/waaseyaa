---
work_package_id: WP01
title: 'useSchema() In-Flight Dedup (closes #1537)'
dependencies: []
requirement_refs:
- C-001
- FR-001
- FR-002
- FR-003
- FR-008
- FR-009
- FR-010
- NFR-001
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-admin-spa-realtime-config-contract-01KS3ST4
base_commit: 43e5f6eb9c708d875e4f7798c8ae6bb831eec4d8
created_at: '2026-05-21T00:26:19.580155+00:00'
subtasks:
- T001
- T002
- T003
- T004
shell_pid: "723435"
agent: "claude:opus-4-7:reviewer:reviewer"
history:
- date: '2026-05-20T23:57:32Z'
  event: created
authoritative_surface: packages/admin/app/composables/
execution_mode: code_change
owned_files:
- packages/admin/app/composables/useSchema.ts
- packages/admin/tests/composables/useSchema.test.ts
tags: []
---

# WP01 — `useSchema()` In-Flight Dedup

**Mission**: `admin-spa-realtime-config-contract-01KS3ST4`
**Branch strategy**: Planning base `main` → Merge target `main`. Execution worktree allocated from `lanes.json`.
**Closes**: #1537 (via `Closes #1537` in commit footer)
**Implement command**: `spec-kitty agent action implement WP01 --agent <name>`

---

## Objective

Fix the duplicate-POST race in `useSchema()`. Two concurrent callers (the page wrapper `pages/[entityType]/index.vue:73` and the child `SchemaList.vue:154-156`) both call `fetch()` during the same tick. Both miss the module-level `schemaCache`, both issue a POST to `/admin/_surface/{type}/action/schema`, and both write to the cache. The fix tracks an in-flight `Promise<EntitySchema>` per entityType and returns it to subsequent callers until it settles.

**After this WP**: Two concurrent `useSchema('node').fetch()` calls issue exactly one HTTP request regardless of timing. The dedup tracker clears on rejection (no poison-caching). `invalidate()` clears both the schema cache and any in-flight Promise.

---

## Context

**File**: `packages/admin/app/composables/useSchema.ts`
**Current shape** (lines 1–34, approximate):
```typescript
// Module-level cache
const schemaCache: Map<string, EntitySchema> = new Map()

export function useSchema(entityType: string) {
  async function fetch(): Promise<EntitySchema> {
    if (schemaCache.has(entityType)) {
      return schemaCache.get(entityType)!
    }
    const schema = await $fetch<EntitySchema>(
      `/admin/_surface/${entityType}/action/schema`,
      { method: 'POST' }
    )
    schemaCache.set(entityType, schema)
    return schema
  }

  function invalidate(): void {
    schemaCache.delete(entityType)
  }

  return { fetch, invalidate }
}
```

The race: between the `schemaCache.has()` check and the `schemaCache.set()` call, a second caller also passes the `has()` check and issues its own POST. Both Promises resolve independently; the cache is written twice (benign data duplication) but two network requests are sent (the visible bug).

**Constraints**:
- C-001: No changes to the HTTP endpoint or response shape.
- NFR-001: Dedup tracker adds ≤1 ms overhead on cache-miss (just a Map set/get).
- C-004: No new npm dependencies.

---

## Subtask Details

### T001 — Add `inflightCache` alongside `schemaCache`

**Purpose**: Introduce the in-flight tracker as a module-level `Map<string, Promise<EntitySchema>>`. This is the only new data structure needed.

**Steps**:
1. Open `packages/admin/app/composables/useSchema.ts`.
2. Immediately after the `schemaCache` declaration, add:
   ```typescript
   const inflightCache: Map<string, Promise<EntitySchema>> = new Map()
   ```
3. No other changes in this step — just the declaration.

**File**: `packages/admin/app/composables/useSchema.ts`

**Validation**:
- [ ] `inflightCache` is module-scoped (outside the `useSchema()` function body), matching `schemaCache`.
- [ ] TypeScript compiles (`cd packages/admin && npm run build`).

---

### T002 — Implement in-flight dedup in `fetch()`

**Purpose**: When a `fetch()` call finds no cached schema, it checks `inflightCache` before issuing a new POST. If an in-flight Promise exists, return it. Otherwise, create the Promise, register it in `inflightCache`, wire `.then()` and `.catch()` handlers, and return it.

**Steps**:
1. Rewrite the `fetch()` function body:

```typescript
async function fetch(): Promise<EntitySchema> {
  // 1. Cache hit — synchronous return (NFR-001)
  if (schemaCache.has(entityType)) {
    return schemaCache.get(entityType)!
  }

  // 2. In-flight hit — return the existing Promise (FR-001)
  const inflight = inflightCache.get(entityType)
  if (inflight !== undefined) {
    return inflight
  }

  // 3. Cache miss — issue the POST and register in inflightCache
  const promise = $fetch<EntitySchema>(
    `/admin/_surface/${entityType}/action/schema`,
    { method: 'POST' }
  ).then((schema) => {
    schemaCache.set(entityType, schema)
    inflightCache.delete(entityType)  // clean up after resolution
    return schema
  }).catch((err: unknown) => {
    inflightCache.delete(entityType)  // FR-002: clear on rejection
    throw err                          // re-throw so callers see the error
  })

  inflightCache.set(entityType, promise)
  return promise
}
```

**Important**: The `.then()` / `.catch()` handlers are chained on the same `promise` that goes into `inflightCache`. The returned Promise from this function IS the same Promise stored in the cache, so all concurrent callers share the same resolution.

**File**: `packages/admin/app/composables/useSchema.ts`

**Validation**:
- [ ] `inflightCache.get(entityType)` is checked before the POST is issued.
- [ ] The chained `.then()` deletes from `inflightCache` and writes to `schemaCache`.
- [ ] The chained `.catch()` deletes from `inflightCache` and re-throws.
- [ ] TypeScript compiles without errors.

---

### T003 — Clear `inflightCache` on `invalidate()`

**Purpose**: FR-003 requires that `invalidate()` clears both the schema cache and any in-flight Promise for that entityType. Without this, `invalidate()` followed by `fetch()` while a stale Promise is still registered would return the stale result.

**Steps**:
1. Update the `invalidate()` function:

```typescript
function invalidate(): void {
  schemaCache.delete(entityType)
  inflightCache.delete(entityType)  // FR-003: clear in-flight on invalidate
}
```

**File**: `packages/admin/app/composables/useSchema.ts`

**Validation**:
- [ ] `inflightCache.delete(entityType)` is present in `invalidate()`.
- [ ] A `fetch()` call immediately after `invalidate()` issues a fresh POST (verified by test T004b).

---

### T004 — Unit tests: FR-008, FR-009, FR-010

**Purpose**: Three regression tests that lock in the dedup contract so future changes cannot re-introduce the race.

**File**: `packages/admin/tests/composables/useSchema.test.ts` (create or add to existing)

**Test setup pattern** (Vitest + Nuxt test utils mock):
```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest'

// Mock $fetch — Nuxt composable, may need mockNuxtImport from @nuxt/test-utils
// Or mock at module level if the composable uses the global $fetch

let fetchCallCount: number
let fetchResolver: (schema: EntitySchema) => void
let fetchRejecter: (err: Error) => void

beforeEach(() => {
  fetchCallCount = 0
  // Clear module-level caches between tests by calling invalidate() or by
  // re-importing. If caches are truly module-scoped, use vi.resetModules().
})
```

**FR-008 test — doesNotDuplicateConcurrentFetches**:
```typescript
it('doesNotDuplicateConcurrentFetches: two concurrent fetch() calls issue exactly one HTTP request', async () => {
  let resolveSchema!: (s: EntitySchema) => void
  const mockFetch = vi.fn().mockImplementation(() =>
    new Promise<EntitySchema>((resolve) => { resolveSchema = resolve })
  )
  // wire mockFetch as $fetch ...

  const composable = useSchema('node')
  const p1 = composable.fetch()
  const p2 = composable.fetch()

  resolveSchema({ type: 'node', fields: [] })
  const [s1, s2] = await Promise.all([p1, p2])

  expect(mockFetch).toHaveBeenCalledTimes(1)          // one HTTP request
  expect(s1).toBe(s2)                                  // same reference
})
```

**FR-009 test — clearsInflightOnInvalidate**:
```typescript
it('clearsInflightOnInvalidate: invalidate() during in-flight causes next fetch() to issue a new request', async () => {
  let resolveFirst!: (s: EntitySchema) => void
  let callCount = 0
  const mockFetch = vi.fn().mockImplementation(() =>
    new Promise<EntitySchema>((resolve) => {
      callCount++
      resolveFirst = resolve
    })
  )
  // wire mockFetch ...

  const composable = useSchema('node')
  const p1 = composable.fetch()         // first request in-flight
  composable.invalidate()               // clear mid-flight
  const p2 = composable.fetch()         // should issue second request

  expect(callCount).toBe(2)
  resolveFirst({ type: 'node', fields: [] })
  // p1 may resolve or reject depending on timing — the key assertion is callCount
})
```

**FR-010 test — doesNotPoisonOnRejection**:
```typescript
it('doesNotPoisonOnRejection: a rejected fetch() does not prevent a subsequent fresh request', async () => {
  let rejectFirst!: (err: Error) => void
  let resolveSecond!: (s: EntitySchema) => void
  let callCount = 0
  const mockFetch = vi.fn().mockImplementation(() =>
    new Promise<EntitySchema>((resolve, reject) => {
      callCount++
      if (callCount === 1) { rejectFirst = reject }
      else { resolveSecond = resolve }
    })
  )
  // wire mockFetch ...

  const composable = useSchema('node')
  const p1 = composable.fetch()
  rejectFirst(new Error('network error'))
  await expect(p1).rejects.toThrow('network error')

  const p2 = composable.fetch()          // must issue a fresh request
  resolveSecond({ type: 'node', fields: [] })
  await expect(p2).resolves.toEqual({ type: 'node', fields: [] })

  expect(callCount).toBe(2)              // two requests, not one
})
```

**Note on mocking `$fetch` in Nuxt composables**: If `$fetch` is resolved via Nuxt's auto-import and cannot be easily vi.fn()-mocked at module level, use `mockNuxtImport('$fetch', ...)` from `@nuxt/test-utils/runtime`. If Vitest + nuxt environment is required, ensure the test file has `@vitest-environment nuxt` pragma at top (or in vitest config).

**Validation**:
- [ ] All three tests pass (`cd packages/admin && npm test`).
- [ ] Each test assertion matches the corresponding FR (one POST, fresh POST after invalidate, fresh POST after rejection).
- [ ] TypeScript build passes.

---

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- Execution worktrees are allocated per computed lane from `lanes.json` after `finalize-tasks`.
- This WP is in **Lane A** (no dependencies — can start immediately).
- Commit footer must include: `Closes #1537`

---

## Definition of Done

- [ ] `inflightCache: Map<string, Promise<EntitySchema>>` exists at module scope in `useSchema.ts`.
- [ ] `fetch()` returns the in-flight Promise if one exists for the given entityType.
- [ ] `fetch()`'s `.catch()` handler deletes from `inflightCache` and re-throws.
- [ ] `invalidate()` deletes from both `schemaCache` and `inflightCache`.
- [ ] Three unit tests pass: `doesNotDuplicateConcurrentFetches`, `clearsInflightOnInvalidate`, `doesNotPoisonOnRejection`.
- [ ] `cd packages/admin && npm run build` exits 0 (TypeScript strict-clean).
- [ ] `cd packages/admin && npm test` exits 0.
- [ ] Commit footer includes `Closes #1537`.
- [ ] No changes to the HTTP endpoint or its response shape (C-001).

---

## Risks

| Risk | Mitigation |
|---|---|
| `$fetch` in Nuxt composables resists vi.fn() mocking | Use `mockNuxtImport` from `@nuxt/test-utils/runtime`; if still blocked, move to Playwright E2E for the network-capture assertion |
| Module-level cache leaks between tests | Call `invalidate()` in `beforeEach`, or use `vi.resetModules()` to get fresh module state |
| `Promise` chaining creates a different reference than what's stored in `inflightCache` | Ensure the `.then().catch()` chain IS the value stored — do not store the raw `$fetch(...)` call and return the chained version separately |

---

## Reviewer Guidance

1. Confirm `inflightCache` is module-scoped (same level as `schemaCache`), not created inside `useSchema()`.
2. Confirm the Promise stored in `inflightCache` is the SAME reference that is returned to the caller — both callers must literally share the same Promise object.
3. Confirm `invalidate()` has TWO `delete()` calls.
4. Run `cd packages/admin && npm test` and verify all three new test names appear in output with ✓.
5. Run `cd packages/admin && npm run build` and verify zero TypeScript errors.

## Activity Log

- 2026-05-21T00:26:21Z – claude:sonnet:implementer:implementer – shell_pid=707133 – Assigned agent via action command
- 2026-05-21T00:40:11Z – claude:sonnet:implementer:implementer – shell_pid=707133 – inflightCache added at module scope; clears on rejection (FR-002) and invalidate (FR-003); 3 dedup tests pass (doesNotDuplicateConcurrentFetches, clearsInflightOnInvalidate, doesNotPoisonOnRejection); build clean; 11/11 tests pass
- 2026-05-21T00:41:00Z – claude:opus-4-7:reviewer:reviewer – shell_pid=723435 – Started review via action command
