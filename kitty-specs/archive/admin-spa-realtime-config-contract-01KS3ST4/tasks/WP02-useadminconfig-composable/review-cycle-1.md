---
affected_files: []
cycle_number: 1
mission_slug: admin-spa-realtime-config-contract-01KS3ST4
reproduction_command:
reviewed_at: '2026-05-21T00:51:04Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP02
---

# WP02 Review Feedback (M-F, cycle 1)

## Verdict: Changes requested

The implementation is largely correct and well-tested, but it ships a known-buggy duplicate file that creates an auto-import collision risk. One small change required before approval.

## What works (do not undo)

- **`app/composables/configCoercion.ts`** is correct. The `length > 1` guard on `asUrl` properly preserves bare `/`. `asBoolean` is case-insensitive (`'TRUE'`, `'YES'`, `'ON'` all pass via `.toLowerCase()`). `asString` fallback for null/undefined works.
- **`app/composables/useAdminConfig.ts`** uses `useState('waaseyaa-admin-config', factory).value` for referential stability — NFR-002 satisfied (the factory only runs once per key, so repeated calls return the same object reference).
- **`app/types/AdminConfig.ts`** has no `any`/`unknown` fields; all 5 spec keys present plus a sensible optional `logoUrl`. Fields are typed correctly (booleans where boolean, strings where string).
- **Tests**: 243/243 passing. asBoolean case-insensitivity covered (`'TRUE'`, `'YES'`, `'ON'`). asUrl null+fallback covered. asString fallback covered. Total 16+ test cases for the coercion helpers.
- **README "Runtime config" section**: explains WHY (Nuxt digit-string coercion) and HOW (use `useAdminConfig()` not `useRuntimeConfig()`), and references NFR-002. Discoverable for future contributors.
- **Build**: `npm run build` succeeds; `npm run lint` has 0 new errors.

## Required change

**Delete `packages/admin/app/utils/configCoercion.ts`.**

This is a duplicate of `app/composables/configCoercion.ts` that:

1. **Ships the original bug.** Its `asUrl` lacks the `length > 1` guard, so `asUrl('/', '/')` returns `''` (strips the bare root path). This is the very bug your commit message says was fixed in the composables/ copy.
2. **Creates an auto-import collision.** Nuxt 3 auto-imports from both `app/utils/` and `app/composables/`. Two files exporting the same names (`asBoolean`, `asString`, `asUrl`) means future code that uses these helpers via auto-import (no explicit `import` statement) could resolve to either implementation depending on Nuxt's resolution order. The build currently succeeds because `useAdminConfig.ts` uses an explicit import path, but the next consumer who relies on auto-import is a latent foot-gun.
3. **Your commit `085c8f2f8` literally says** "prior-agent artifact, superseded by composables/configCoercion.ts" — i.e. you knew it was redundant. The right move is to delete it, not commit it.

After deleting, re-run:
```bash
cd packages/admin
npm test    # confirm 243/243 still
npm run build
npm run lint
```

Then `spec-kitty agent action implement WP02 ...` and push.

## No other side effects

Diff is otherwise tight to the expected 5 files + README (8 if you count the test, the package-lock.json reverted noise from WP01, and the superseded utils/ file). Once the duplicate is removed, the diff will be exactly right.
