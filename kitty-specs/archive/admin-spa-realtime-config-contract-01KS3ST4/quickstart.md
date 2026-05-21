# Quickstart: Admin SPA — Realtime Config Contract

**Mission**: `admin-spa-realtime-config-contract-01KS3ST4`
**Date**: 2026-05-20

---

## Implementer Setup

```bash
# From repo root — no worktrees needed (main → main)
cd /home/jones/dev/waaseyaa

# Install admin SPA deps if needed
cd packages/admin && npm install

# Run unit tests
npm test

# Run E2E tests (requires dev server on :3000)
npm run test:e2e

# TypeScript build check
npm run build

# Lint
npm run lint

# Check coercion patterns (WP04 gate — not yet present until WP04)
bin/check-admin-coercion-patterns
```

---

## WP01 Implementer Notes

**File**: `packages/admin/app/composables/useSchema.ts`

Add `inflightCache` alongside `schemaCache`:

```typescript
const schemaCache: Map<string, EntitySchema> = new Map()
const inflightCache: Map<string, Promise<EntitySchema>> = new Map()
```

In `fetch(entityType)`:
1. Check `schemaCache` first (existing cache-hit path — unchanged)
2. Check `inflightCache` — if hit, return the existing Promise
3. Create the POST Promise, store in `inflightCache`
4. `.then()`: write to `schemaCache`, delete from `inflightCache`, return schema
5. `.catch()`: delete from `inflightCache`, re-throw

In `invalidate(entityType)`:
- `schemaCache.delete(entityType)`
- `inflightCache.delete(entityType)`

**Test file**: `packages/admin/tests/composables/useSchema.test.ts` (or nearest existing test file)

---

## WP02 Implementer Notes

**New file**: `packages/admin/app/utils/configCoercion.ts`

```typescript
export function asBoolean(value: unknown, defaultVal = false): boolean {
  if (value === true || value === 1) return true
  if (typeof value === 'string') {
    return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase())
  }
  return defaultVal
}
```

**New file**: `packages/admin/app/composables/useAdminConfig.ts`

Use `useState('admin-config', ...)` for Nuxt SSR-safe, referentially stable state (NFR-002). Call once, memoize.

```typescript
export function useAdminConfig(): Readonly<AdminConfig> {
  return useState<AdminConfig>('admin-config', () => {
    const rc = useRuntimeConfig()
    return {
      enableRealtime: asBoolean(rc.public.enableRealtime),
      appName: asString(rc.public.appName, 'Waaseyaa'),
      docsUrl: asUrl(rc.public.docsUrl, '/docs'),
      baseUrl: asUrl(rc.public.baseUrl, '/'),
      logoUrl: asOptionalString(rc.public.logoUrl),
      auth: {
        registration: asString((rc.public.auth as any)?.registration, 'admin'),
        requireVerifiedEmail: asBoolean((rc.public.auth as any)?.requireVerifiedEmail),
      },
    }
  }).value
}
```

Note: `rc.public.auth` is an object in `nuxt.config.ts` — the implementer should check the exact runtime shape and remove any `any` cast by typing the auth sub-object properly (NFR-003).

---

## WP03 Implementer Notes

**Pattern to replace** in all 14 call-site files:

```typescript
// BEFORE
const config = useRuntimeConfig()
const appName = config.public.appName as string

// AFTER
const { appName } = useAdminConfig()
```

```typescript
// BEFORE (SchemaList.vue:19)
const realtimeEnabled = String(config.public.enableRealtime) === '1'

// AFTER
const { enableRealtime } = useAdminConfig()
```

After migration, verify:
```bash
# Should return no matches
grep -rn "useRuntimeConfig\|config\.public" packages/admin/app/ --include="*.ts" --include="*.vue"
```

---

## WP04 Implementer Notes

**`bin/check-admin-coercion-patterns`** (new, chmod +x):

```bash
#!/usr/bin/env bash
set -euo pipefail

pattern='String(.*) === .1.'
matches=$(grep -rn "$pattern" packages/admin/app/ --include="*.ts" --include="*.vue" 2>/dev/null || true)

if [[ -n "$matches" ]]; then
  echo "ERROR: Coercion pattern found in packages/admin/app/:"
  echo "$matches"
  echo ""
  echo "Replace with useAdminConfig(). See packages/admin/README.md."
  echo "If this is a legitimate exception, add: // allow-coercion: <reason>"
  exit 1
fi

echo "check-admin-coercion-patterns: PASS (0 matches)"
exit 0
```

**Playwright spec** `packages/admin/e2e/schema-dedup.spec.ts`:
- Use `page.route()` or `page.on('request', ...)` to intercept POSTs to `**/admin/_surface/*/action/schema`
- Navigate to `/admin/node`
- Wait for page load
- Assert `requests.filter(r => r.method() === 'POST').length === 1`

---

## Verification Checklist (WP04)

- [ ] `cd packages/admin && npm run build` — TypeScript strict clean
- [ ] `cd packages/admin && npm test` — all unit tests pass
- [ ] `cd packages/admin && npm run test:e2e` — Playwright schema-dedup spec passes
- [ ] `cd packages/admin && npm run lint` — no lint errors
- [ ] `bin/check-admin-coercion-patterns` — exits 0
- [ ] `composer verify` — full PHP + SPA gate passes
- [ ] `grep -rn "useRuntimeConfig\|config\.public" packages/admin/app/` — zero results
- [ ] `grep -rn "String(.*) === '1'" packages/admin/app/` — zero results
- [ ] CHANGELOG.md has `[Unreleased]` entries for #1537 and #1538
- [ ] `packages/admin/README.md` has "Runtime config" section
