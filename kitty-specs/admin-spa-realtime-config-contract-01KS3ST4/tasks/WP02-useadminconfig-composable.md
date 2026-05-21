---
work_package_id: WP02
title: useAdminConfig() Composable + Coercion Helpers
dependencies:
- WP01
requirement_refs:
- C-002
- C-004
- FR-004
- FR-005
- FR-006
- FR-007
- FR-011
- FR-013
- NFR-002
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T005
- T006
- T007
- T008
- T009
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "746729"
history:
- date: '2026-05-20T23:57:32Z'
  event: created
authoritative_surface: packages/admin/app/composables/
execution_mode: code_change
owned_files:
- packages/admin/app/composables/useAdminConfig.ts
- packages/admin/app/utils/configCoercion.ts
- packages/admin/app/types/AdminConfig.ts
- packages/admin/tests/composables/useAdminConfig.test.ts
- packages/admin/tests/utils/configCoercion.test.ts
- packages/admin/README.md
tags: []
---

# WP02 — `useAdminConfig()` Composable + Coercion Helpers

**Mission**: `admin-spa-realtime-config-contract-01KS3ST4`
**Branch strategy**: Planning base `main` → Merge target `main`. Execution worktree allocated from `lanes.json`.
**Implement command**: `spec-kitty agent action implement WP02 --agent <name>`

---

## Objective

Create the typed runtime-config envelope that eliminates digit-string coercion at every call site. Introduce three new files:
1. `packages/admin/app/utils/configCoercion.ts` — pure coercion helpers (`asBoolean`, `asString`, `asUrl`)
2. `packages/admin/app/types/AdminConfig.ts` — fully typed interface (no `any`/`unknown`)
3. `packages/admin/app/composables/useAdminConfig.ts` — reads `useRuntimeConfig()`, coerces each key, returns `readonly AdminConfig`

Plus unit tests and README documentation. WP03 migrates all call sites to this composable.

**After this WP**: Any consumer can call `useAdminConfig().enableRealtime` and receive a typed `boolean`, regardless of whether the operator set `NUXT_PUBLIC_ENABLE_REALTIME=1`, `=true`, `=yes`, or `=enabled`. Adding a new flag is one line in `useAdminConfig.ts`, not a coercion shim at every call site.

---

## Context

**Nuxt runtime-config coercion behavior** (C-002, do not change):
`nuxt.config.ts` declares:
```typescript
runtimeConfig: {
  public: {
    enableRealtime: process.env.NUXT_PUBLIC_ENABLE_REALTIME ?? '',
    appName: process.env.NUXT_PUBLIC_APP_NAME ?? 'Waaseyaa Admin',
    docsUrl: process.env.NUXT_PUBLIC_DOCS_URL ?? '',
    baseUrl: process.env.NUXT_PUBLIC_BASE_URL ?? '',
    logoUrl: process.env.NUXT_PUBLIC_LOGO_URL,
    auth: {
      registration: process.env.NUXT_PUBLIC_AUTH_REGISTRATION ?? 'open',
      requireVerifiedEmail: process.env.NUXT_PUBLIC_AUTH_REQUIRE_VERIFIED_EMAIL === '1',
    },
  },
}
```
Nuxt's serializer coerces `"1"` to `1` (number). At runtime, `runtimeConfig.public.enableRealtime` may be `1` (number), `"1"` (string), `"true"` (string), or `""` (string) depending on operator config and Nuxt version. The typed envelope handles all cases.

**Constraints**:
- C-002: Do NOT modify `nuxt.config.ts` declared keys or env var names.
- C-004: No new npm dependencies (no zod, no superstruct).
- NFR-002: `useAdminConfig()` result is referentially stable within a component tree's lifecycle.
- NFR-003: `AdminConfig` interface has no `any` or `unknown` fields; TypeScript strict build passes.

---

## Subtask Details

### T005 — Create `configCoercion.ts` with `asBoolean`, `asString`, `asUrl`

**Purpose**: Pure, testable coercion helpers. These are standalone functions — no Nuxt/Vue imports, easily unit-tested with plain Vitest.

**File**: `packages/admin/app/utils/configCoercion.ts` (new)

**Implementation**:
```typescript
/**
 * Coercion helpers for Nuxt runtime-config boundary values.
 *
 * Nuxt's runtime-config serializer may coerce digit-string env vars to numbers.
 * These helpers normalize values to their intended TypeScript types at the
 * useAdminConfig() boundary. Do NOT use string-compare coercions at call sites.
 */

/**
 * Coerces a runtime-config value to boolean.
 *
 * Truthy inputs (case-insensitive): true, 1, '1', 'true', 'yes', 'on'
 * All other inputs → false.
 *
 * @api
 */
export function asBoolean(value: unknown, defaultValue: boolean = false): boolean {
  if (value === undefined || value === null) return defaultValue
  if (typeof value === 'boolean') return value
  if (typeof value === 'number') return value === 1
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase()
    return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on'
  }
  return defaultValue
}

/**
 * Coerces a runtime-config value to string.
 * Returns defaultValue if value is undefined, null, or empty string.
 */
export function asString(value: unknown, defaultValue: string = ''): string {
  if (value === undefined || value === null) return defaultValue
  const str = String(value).trim()
  return str.length > 0 ? str : defaultValue
}

/**
 * Coerces a runtime-config value to a URL string.
 * Trims trailing slashes. Returns defaultValue if empty.
 */
export function asUrl(value: unknown, defaultValue: string = ''): string {
  const str = asString(value, defaultValue)
  return str.endsWith('/') ? str.slice(0, -1) : str
}
```

**Validation**:
- [ ] File exports `asBoolean`, `asString`, `asUrl` as named exports.
- [ ] No Nuxt/Vue imports (pure TypeScript).
- [ ] `asBoolean` handles all truthy/falsy variants listed in FR-007.
- [ ] TypeScript compiles.

---

### T006 — Create `AdminConfig` TypeScript interface

**Purpose**: Define the fully typed shape of the admin runtime config. No `any` or `unknown` fields (NFR-003). This interface is the contract WP03 call sites will depend on.

**File**: `packages/admin/app/types/AdminConfig.ts` (new)

**Implementation**:
```typescript
/**
 * Typed envelope for Waaseyaa admin SPA runtime configuration.
 *
 * Consumers must use useAdminConfig() to obtain this object.
 * Do NOT call useRuntimeConfig().public directly in components, pages, or composables.
 *
 * @api
 */
export interface AdminConfig {
  /** Whether realtime SSE features are enabled. */
  readonly enableRealtime: boolean
  /** Application display name. */
  readonly appName: string
  /** URL to the documentation site. */
  readonly docsUrl: string
  /** Base URL of the API. Trailing slash removed. */
  readonly baseUrl: string
  /** Optional logo URL for brand panels. */
  readonly logoUrl: string | undefined
  readonly auth: {
    /** Registration mode: 'open' | 'invite' | 'closed' */
    readonly registration: string
    /** Whether email verification is required before login. */
    readonly requireVerifiedEmail: boolean
  }
}
```

**Validation**:
- [ ] No `any` or `unknown` fields.
- [ ] All fields match the keys in `nuxt.config.ts`'s `runtimeConfig.public` block.
- [ ] TypeScript compiles.

---

### T007 — Create `useAdminConfig()` composable

**Purpose**: The Nuxt 3 composable that reads `useRuntimeConfig()` and coerces each key to the typed `AdminConfig` shape. Uses `useState()` for referential stability (NFR-002) — repeated calls within one component tree return the same object reference.

**File**: `packages/admin/app/composables/useAdminConfig.ts` (new)

**Implementation**:
```typescript
import type { AdminConfig } from '~/types/AdminConfig'
import { asBoolean, asString, asUrl } from '~/utils/configCoercion'

/**
 * Returns typed, coerced admin runtime configuration.
 *
 * Use this composable instead of useRuntimeConfig().public in all admin SPA
 * components, pages, and composables. The coercion layer normalizes Nuxt's
 * digit-string serialization quirks at a single boundary.
 *
 * Referentially stable within a component tree lifecycle (NFR-002).
 *
 * @api
 */
export function useAdminConfig(): Readonly<AdminConfig> {
  return useState<AdminConfig>('waaseyaa-admin-config', (): AdminConfig => {
    const runtimeConfig = useRuntimeConfig()
    const pub = runtimeConfig.public

    return {
      enableRealtime: asBoolean(pub.enableRealtime),
      appName: asString(pub.appName, 'Waaseyaa Admin'),
      docsUrl: asUrl(pub.docsUrl, ''),
      baseUrl: asUrl(pub.baseUrl, ''),
      logoUrl: pub.logoUrl != null ? asString(pub.logoUrl) : undefined,
      auth: {
        registration: asString(pub.auth?.registration, 'open'),
        requireVerifiedEmail: asBoolean(pub.auth?.requireVerifiedEmail),
      },
    }
  }).value
}
```

**Notes**:
- `useState('waaseyaa-admin-config', initializer)` ensures the config is computed once per SSR/CSR lifecycle and the same Ref is returned on subsequent calls. The `.value` unwrap returns the `AdminConfig` object.
- If `logoUrl` is `undefined` in runtime config (operator did not set it), `useAdminConfig().logoUrl` returns `undefined` — consistent with the `AdminConfig` type.
- Do NOT use `computed()` here — `useState()` is idiomatic for Nuxt state that should be stable across composable calls.

**Validation**:
- [ ] File is at `packages/admin/app/composables/useAdminConfig.ts`.
- [ ] `useState` key is `'waaseyaa-admin-config'` (stable, unique).
- [ ] All six top-level `AdminConfig` keys are populated via coercion helpers.
- [ ] Return type is `Readonly<AdminConfig>` (or narrowed equivalent).
- [ ] TypeScript compiles (`cd packages/admin && npm run build`).

---

### T008 — Unit tests for coercion helpers and composable (FR-011)

**Purpose**: Lock in the coercion contract. Tests must cover all truthy inputs for `asBoolean`, all falsy inputs, undefined defaults for `asString`/`asUrl`, and a happy-path test of `useAdminConfig()` field population.

**Files**:
- `packages/admin/tests/utils/configCoercion.test.ts` (new)
- `packages/admin/tests/composables/useAdminConfig.test.ts` (new)

**`configCoercion.test.ts`**:
```typescript
import { describe, it, expect } from 'vitest'
import { asBoolean, asString, asUrl } from '../../app/utils/configCoercion'

describe('asBoolean', () => {
  it.each([true, 1, '1', 'true', 'TRUE', 'True', 'yes', 'YES', 'on', 'ON'])(
    'treats %s as truthy', (input) => {
      expect(asBoolean(input)).toBe(true)
    }
  )
  it.each([false, 0, '', '0', 'false', 'no', 'off', null, undefined, 'random'])(
    'treats %s as falsy', (input) => {
      expect(asBoolean(input)).toBe(false)
    }
  )
  it('returns defaultValue for undefined when defaultValue is true', () => {
    expect(asBoolean(undefined, true)).toBe(true)
  })
})

describe('asString', () => {
  it('returns value when non-empty', () => {
    expect(asString('hello', 'default')).toBe('hello')
  })
  it('returns defaultValue for undefined', () => {
    expect(asString(undefined, 'default')).toBe('default')
  })
  it('returns defaultValue for empty string', () => {
    expect(asString('', 'default')).toBe('default')
  })
  it('returns defaultValue for null', () => {
    expect(asString(null, 'default')).toBe('default')
  })
  it('trims whitespace', () => {
    expect(asString('  hello  ', 'default')).toBe('hello')
  })
})

describe('asUrl', () => {
  it('removes trailing slash', () => {
    expect(asUrl('https://example.com/', '')).toBe('https://example.com')
  })
  it('does not remove non-trailing slash', () => {
    expect(asUrl('https://example.com/path', '')).toBe('https://example.com/path')
  })
  it('returns defaultValue for empty input', () => {
    expect(asUrl('', 'https://default.com')).toBe('https://default.com')
  })
})
```

**`useAdminConfig.test.ts`**:
```typescript
import { describe, it, expect } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'

// Mock useRuntimeConfig to return controlled values
mockNuxtImport('useRuntimeConfig', () => () => ({
  public: {
    enableRealtime: 1,         // number coerced by Nuxt serializer
    appName: 'Test App',
    docsUrl: 'https://docs.example.com/',
    baseUrl: 'https://api.example.com/',
    logoUrl: undefined,
    auth: {
      registration: 'open',
      requireVerifiedEmail: '1',  // digit-string scenario
    },
  },
}))

describe('useAdminConfig', () => {
  it('coerces enableRealtime from number 1 to boolean true', async () => {
    const { useAdminConfig } = await import('../../app/composables/useAdminConfig')
    const config = useAdminConfig()
    expect(config.enableRealtime).toBe(true)
  })

  it('removes trailing slash from docsUrl and baseUrl', async () => {
    const { useAdminConfig } = await import('../../app/composables/useAdminConfig')
    const config = useAdminConfig()
    expect(config.docsUrl).toBe('https://docs.example.com')
    expect(config.baseUrl).toBe('https://api.example.com')
  })

  it('coerces requireVerifiedEmail from digit-string to boolean', async () => {
    const { useAdminConfig } = await import('../../app/composables/useAdminConfig')
    const config = useAdminConfig()
    expect(config.auth.requireVerifiedEmail).toBe(true)
  })

  it('returns undefined for unset logoUrl', async () => {
    const { useAdminConfig } = await import('../../app/composables/useAdminConfig')
    const config = useAdminConfig()
    expect(config.logoUrl).toBeUndefined()
  })
})
```

**Note**: `mockNuxtImport` requires the test file to run in Nuxt environment. If not already configured, check `vitest.config.ts` in `packages/admin` for the `@nuxt/test-utils` environment setup.

**Validation**:
- [ ] All tests pass (`cd packages/admin && npm test`).
- [ ] `asBoolean` truthy/falsy coverage matches FR-007.
- [ ] `useAdminConfig` tests cover the digit-string scenario (the actual #1538 bug).

---

### T009 — Add "Runtime config" section to `packages/admin/README.md`

**Purpose**: FR-013 — document the `useAdminConfig()` contract so contributors know why direct `useRuntimeConfig()` access is prohibited and how to add new flags.

**File**: `packages/admin/README.md` (edit — add section)

**Content to add** (find the appropriate place in the existing README, e.g., after "Composables" section or before "Development"):

```markdown
## Runtime config

The admin SPA wraps all Nuxt runtime config access behind the `useAdminConfig()` composable
(`app/composables/useAdminConfig.ts`). **Do not call `useRuntimeConfig().public.*` directly**
in components, pages, or composables.

### Why

Nuxt's runtime-config serializer coerces digit-string env vars (e.g., `NUXT_PUBLIC_ENABLE_REALTIME=1`)
to numbers at build time. Consuming code that compares `=== '1'` silently fails. `useAdminConfig()`
normalizes every key to its intended TypeScript type at a single boundary.

### Consuming config

```typescript
// ✅ Correct
const { enableRealtime, appName } = useAdminConfig()

// ❌ Wrong — may receive a number where boolean is expected
const runtimeConfig = useRuntimeConfig()
const enabled = runtimeConfig.public.enableRealtime === '1'  // broken
```

### Adding a new flag

1. Add the env var to `nuxt.config.ts`'s `runtimeConfig.public` block (existing keys preserved — C-002).
2. Add the typed field to `app/types/AdminConfig.ts`.
3. Add the coercion line to `useAdminConfig.ts`: e.g., `enableFoo: asBoolean(pub.enableFoo)`.
4. Consume it everywhere as `useAdminConfig().enableFoo`.

### Coercion helpers

`app/utils/configCoercion.ts` exports:
- `asBoolean(v, defaultValue?)` — truthy: `true`, `1`, `'1'`, `'true'`, `'yes'`, `'on'` (case-insensitive)
- `asString(v, defaultValue)` — trims whitespace, returns default for empty/null/undefined
- `asUrl(v, defaultValue)` — same as `asString` but removes trailing slash

### CI gate

`bin/check-admin-coercion-patterns` (added in WP04) fails CI if any `String(x) === '1'` pattern
appears in `packages/admin/app/**`. Add `// allow-coercion: <reason>` inline to suppress a false positive.
```

**Validation**:
- [ ] "Runtime config" section exists in `packages/admin/README.md`.
- [ ] Section explains WHY and HOW (not just WHAT).
- [ ] "Adding a new flag" recipe is present.

---

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- This WP is in **Lane A** (no dependencies — can start simultaneously with WP01).
- The three new files (`configCoercion.ts`, `AdminConfig.ts`, `useAdminConfig.ts`) must be committed before WP03 begins.

---

## Definition of Done

- [ ] `packages/admin/app/utils/configCoercion.ts` exists and exports `asBoolean`, `asString`, `asUrl`.
- [ ] `packages/admin/app/types/AdminConfig.ts` exists with fully typed `AdminConfig` interface (no `any`/`unknown`).
- [ ] `packages/admin/app/composables/useAdminConfig.ts` exists and returns `Readonly<AdminConfig>`.
- [ ] `asBoolean` handles all truthy inputs from FR-007.
- [ ] Unit tests in `tests/utils/configCoercion.test.ts` and `tests/composables/useAdminConfig.test.ts` all pass.
- [ ] `cd packages/admin && npm run build` exits 0 (strict TypeScript clean).
- [ ] `cd packages/admin && npm test` exits 0.
- [ ] "Runtime config" section added to `packages/admin/README.md`.
- [ ] No changes to `nuxt.config.ts` declared keys (C-002).

---

## Risks

| Risk | Mitigation |
|---|---|
| `useState()` in non-Nuxt test context throws | Mock `useState` in test setup or use `mockNuxtImport('useState', ...)` |
| `runtimeConfig.public` shape mismatch at TypeScript level | Check the existing `app.d.ts` or `nuxt.config.ts` augmentation; update the `AdminConfig` interface to match exactly |
| `logoUrl` is not in `runtimeConfig.public` type | Check nuxt.config.ts — if missing, add it to the type augmentation (not to the env var contract) |

---

## Reviewer Guidance

1. Confirm `asBoolean('YES')` returns `true` and `asBoolean('0')` returns `false` (case normalization).
2. Confirm `useAdminConfig()` does NOT call `useRuntimeConfig()` on every invocation — it should use `useState()` so the computation runs once.
3. Verify `AdminConfig` has no `any` or `unknown` fields (`npm run build` strict check covers this).
4. Read the README section and confirm a new contributor would understand how to add a flag without reverting to direct `useRuntimeConfig()` calls.

## Activity Log

- 2026-05-21T00:43:32Z – claude:sonnet:implementer:implementer – shell_pid=728329 – Started implementation via action command
- 2026-05-21T00:48:10Z – claude:sonnet:implementer:implementer – shell_pid=728329 – useAdminConfig + asBoolean/asString/asUrl + README runtime-config section
- 2026-05-21T00:48:50Z – claude:opus-4-7:reviewer:reviewer – shell_pid=746729 – Started review via action command
