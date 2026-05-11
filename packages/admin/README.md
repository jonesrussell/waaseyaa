# @waaseyaa/admin

The schema-driven admin SPA for the Waaseyaa framework. **Internal monorepo workspace member — not published to npm.** Treat as an application, not a library.

The canonical reference for architecture, contracts, and integration is **[`docs/specs/admin-spa.md`](../../docs/specs/admin-spa.md)** in the framework repo. This README only covers what you need to operate the package locally.

## Stack

- **Nuxt 4.4.4** (SSR disabled — pure SPA mode)
- **Vue 3.5.34** + Composition API
- **vue-router 5** (required for Volar `sfc-route-blocks` under Nuxt 4)
- **TypeScript 6.x**, **vitest 4.x**, **Playwright 1.59+**

The Nuxt minor version is pinned exactly (`"nuxt": "4.4.4"`) because Nuxt 4.4.5 ships a `@nuxt/vite-builder` regression in dev mode. See the CHANGELOG entry on issue #1419 for the unpin condition.

## Develop

```bash
cd packages/admin
npm install                  # also runs `nuxt prepare`
npm run dev                  # HMR dev server on http://localhost:3000/admin/
npm run dev:wsl              # same, but listens on 0.0.0.0 (use from a Windows browser against WSL)
```

Set `NUXT_BACKEND_URL` if the PHP backend isn't on `http://127.0.0.1:8080`.

## Test & verify

```bash
npm test                     # Vitest unit suite
npm run typecheck            # vue-tsc --noEmit
npm run lint                 # eslint . (0 errors / 61 baseline warnings — see #1411)
npm run test:e2e             # Playwright; uses production preview in CI, dev in local
```

## Build

```bash
npm run build                # Nuxt SPA build → .output/
npm run preview              # Serve the built output locally
npm run build:contracts      # Emit contract types to dist/ (verification gate; no consumer)
```

`build:contracts` is distinct from `typecheck`: it proves the `contracts/` and `adapters/` modules can be *emitted* as standalone `.d.ts` files via `tsconfig.contracts.json` (caught early: any accidental dependency on Nuxt auto-imports, Vue composition API magic, or non-published transitive types). `dist/` is gitignored — the artifact is verification-only and is uploaded for inspection by the `admin/contracts` CI job (14-day retention). No downstream package currently imports `@waaseyaa/admin`, but the gate keeps the contract surface clean for the day one does.

## Bootstrap contract validation

`contracts/bootstrap.schema.json` is the canonical JSON Schema for the SPA's bootstrap payload. The `admin/contracts` CI job validates the schema against a reference payload using `ajv-cli`. Update the schema in lockstep with backend changes in `packages/admin-surface/`.

## i18n

Translations live in `app/i18n/` and are consumed by `useLanguage()`.

- Current locales: `en` (`app/i18n/en.json`), `fr` (`app/i18n/fr.json`)
- To add a locale:
  1. Create `app/i18n/<locale>.json` with the same keys used in `en.json`.
  2. Import and register it in `app/composables/useLanguage.ts` (extend the `Locale` union and the `messages` map).
  3. The topbar language selector renders new locales automatically from `useLanguage().locales`.
  4. Add/update unit tests in `tests/unit/composables/useLanguage.test.ts` and `tests/components/layout/AdminShell.test.ts`.

## Modernization status

This package is the subject of the [Admin SPA Modernization Audit](../../docs/audits/admin-spa-modernization-2026-05-10.md). The audit's Top 5 follow-up missions (M1–M5) are tracked under issues #1411–#1415 in the framework repo.
