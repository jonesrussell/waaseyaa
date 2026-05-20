# Implementation Plan: Admin SPA — Realtime Config Contract

**Branch**: `main` | **Date**: 2026-05-20 | **Spec**: [spec.md](spec.md)
**Mission**: `admin-spa-realtime-config-contract-01KS3ST4`
**Input**: `kitty-specs/admin-spa-realtime-config-contract-01KS3ST4/spec.md`

**Branch contract (repeated per protocol):**
- Current branch at plan start: `main`
- Planning/base branch: `main`
- Merge target for completed changes: `main`

---

## Summary

Fix two contract-level gaps in the Waaseyaa admin SPA's composable layer:

1. **`useSchema()` dedup race** (#1537) — Two concurrent `fetch()` callers (the page wrapper and `SchemaList.vue` child) both miss the module-level cache on the same tick and both issue a POST. Fix: track an in-flight `Promise<EntitySchema>` per entityType and return it to subsequent callers until resolved. Clear on rejection and on `invalidate()`.

2. **Digit-string env var coercion** (#1538) — Nuxt's runtime-config serializer coerces `"1"` to `1` (number), breaking `=== '1'` comparisons at call sites. Fix: introduce `useAdminConfig()`, a typed envelope composable that coerces each known `runtimeConfig.public.*` key to its target TypeScript type via small pure helper functions (`asBoolean`, `asString`, `asUrl`). Migrate all 14 call-site files to consume `useAdminConfig()` instead of `useRuntimeConfig()` directly.

After merge: exactly one schema POST per entityType per page load, no `String(x) === '1'` patterns in the SPA, TypeScript strict build green, `bin/check-admin-coercion-patterns` gate green, issues #1537 and #1538 auto-closed.

---

## Technical Context

**Language/Version**: TypeScript 5.x, Vue 3, Nuxt 3 (admin SPA in `packages/admin/`)
**Primary Dependencies**: Nuxt 3, Vue 3, Vitest + `@nuxt/test-utils` (unit tests), Playwright (E2E in `packages/admin/e2e/`)
**Storage**: N/A (SPA composable layer; no storage changes)
**Testing**: Vitest for unit tests; Playwright for E2E network-capture spec (already wired)
**Target Platform**: Browser SPA served by Nuxt dev/build
**Project Type**: Frontend SPA (packages/admin/)
**Performance Goals**: Dedup tracker adds ≤1 ms overhead on cache-miss (NFR-001); `useAdminConfig()` coercion runs once per component tree (NFR-002)
**Constraints**: No new npm dependencies (C-004); no changes to `nuxt.config.ts` declared keys (C-002); no changes to `/admin/_surface/{type}/action/schema` HTTP surface (C-001); TypeScript strict build must pass (NFR-003); `composer verify` + `npm run build && npm test && npm run lint` all green (C-005)
**Scale/Scope**: 14 call-site files containing `useRuntimeConfig`/`config.public` references; 4 WPs; ~3–5 days of focused SPA work

---

## Charter Check

Charter present at `.kittify/charter/charter.md` (generated 2026-04-27).

| Gate | Status | Notes |
|------|--------|-------|
| Branch strategy | PASS | `main → main` matches charter Branch Strategy; no feature branch needed for this scope |
| Testing standards | PASS | Unit tests (Vitest) + E2E (Playwright) cover all FR-008..FR-012 requirements |
| Quality gates | PASS | `composer verify` + `cd packages/admin && npm run build && npm test && npm run lint` all gated in C-005 / CI |
| DIR-001 (CHANGELOG.md entry) | PASS | WP04 writes the `[Unreleased]` CHANGELOG entry |
| DIR-002 (no `@dev` / wildcard) | PASS | No composer.json changes in this mission; constraint is N/A for pure SPA work |
| DIR-003 (public-API removal policy) | PASS | No public PHP API changes; `useRuntimeConfig()` direct calls are internal SPA patterns, not versioned public API |
| C-004 (no new npm deps) | PASS | `asBoolean`/`asString` helpers are native TypeScript; no zod/superstruct/etc. |
| No CI hooks bypassed (C-006) | PASS | All WPs commit through normal git flow |

**Post-Phase-0 re-check**: No charter conflicts surfaced. All constraints are satisfiable with native TypeScript helpers and existing Vitest/Playwright infrastructure.

---

## Project Structure

### Documentation (this mission)

```
kitty-specs/admin-spa-realtime-config-contract-01KS3ST4/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
└── tasks.md             # Phase 2 output (/spec-kitty.tasks — NOT created here)
```

### Source Code (repository root)

```
packages/admin/
├── app/
│   ├── composables/
│   │   ├── useSchema.ts                    # EDIT: add in-flight Promise tracker (WP01)
│   │   └── useAdminConfig.ts               # NEW: typed runtime-config envelope (WP02)
│   ├── types/
│   │   └── AdminConfig.ts                  # NEW (or co-located in useAdminConfig.ts) (WP02)
│   ├── utils/
│   │   └── configCoercion.ts               # NEW: asBoolean, asString, asUrl helpers (WP02)
│   ├── middleware/
│   │   └── auth.global.ts                  # EDIT: migrate to useAdminConfig() (WP03)
│   ├── plugins/
│   │   └── admin.ts                        # EDIT: migrate to useAdminConfig() (WP03)
│   ├── components/
│   │   ├── layout/AdminShell.vue           # EDIT: migrate (WP03)
│   │   ├── auth/BrandPanel.vue             # EDIT: migrate (WP03)
│   │   └── schema/SchemaList.vue           # EDIT: remove String(x)==='1', migrate (WP03)
│   └── pages/
│       ├── index.vue                       # EDIT: migrate (WP03)
│       ├── register.vue                    # EDIT: migrate (WP03)
│       ├── login.vue                       # EDIT: migrate (WP03)
│       ├── forgot-password.vue             # EDIT: migrate (WP03)
│       ├── reset-password.vue              # EDIT: migrate (WP03)
│       ├── workflows/[id].vue              # EDIT: migrate (WP03)
│       ├── workflows/index.vue             # EDIT: migrate (WP03)
│       ├── [entityType]/index.vue          # EDIT: migrate (WP03)
│       ├── [entityType]/[id].vue           # EDIT: migrate (WP03)
│       ├── [entityType]/create.vue         # EDIT: migrate (WP03)
│       ├── [entityType]/pipeline.vue       # EDIT: migrate (WP03)
│       └── telescope/codified-context/
│           ├── index.vue                   # EDIT: migrate (WP03)
│           └── [sessionId].vue             # EDIT: migrate (WP03)
├── tests/                                  # Vitest unit tests
│   ├── composables/
│   │   ├── useSchema.test.ts               # EDIT/NEW: FR-008, FR-009, FR-010 (WP01)
│   │   └── useAdminConfig.test.ts          # NEW: FR-011 (WP02)
│   └── utils/
│       └── configCoercion.test.ts          # NEW: asBoolean/asString edge cases (WP02)
├── e2e/
│   └── schema-dedup.spec.ts                # NEW: FR-012 Playwright network capture (WP04)
├── README.md                               # EDIT: add "Runtime config" section (WP02/WP04)
└── nuxt.config.ts                          # NO CHANGE (C-002)
bin/
└── check-admin-coercion-patterns           # NEW: grep gate for String(x)==='1' (WP04)
CHANGELOG.md                                # EDIT: [Unreleased] entry (WP04)
```

**Structure Decision**: Single SPA project (`packages/admin/`). All changes are TypeScript/Vue composable-layer; no PHP, no new packages, no new composer.json entries.

---

## Work Package Outline

### WP01 — `useSchema()` in-flight dedup (closes #1537)

**Scope**: `packages/admin/app/composables/useSchema.ts` + its unit tests
**Touches**: 1 composable file + 1 test file
**Key changes**:
- Add `inflightCache: Map<string, Promise<EntitySchema>>` alongside the existing `schemaCache`
- In `fetch(entityType)`: before issuing POST, check `inflightCache.get(entityType)` and return it if present; after POST resolves, delete from `inflightCache` and write to `schemaCache`; on rejection, delete from `inflightCache` (do not write to `schemaCache`)
- In `invalidate(entityType)`: clear both `schemaCache` and `inflightCache` for that key
- Unit tests: `doesNotDuplicateConcurrentFetches` (FR-008), `clearsInflightOnInvalidate` (FR-009), `doesNotPoisonOnRejection` (FR-010)
**Verification**: `cd packages/admin && npm test` green; TypeScript build passes
**Closes**: #1537 via `Closes #1537` in commit footer

### WP02 — `useAdminConfig()` composable + coercion helpers

**Scope**: `packages/admin/app/composables/useAdminConfig.ts` (new) + `packages/admin/app/utils/configCoercion.ts` (new or co-located) + `packages/admin/app/types/AdminConfig.ts` (new or inline) + unit tests + README section
**Touches**: 3 new files + 1 test file + README edit
**Key changes**:
- `configCoercion.ts`: pure functions `asBoolean(v, defaultVal?: boolean): boolean`, `asString(v, defaultVal: string): string`, `asUrl(v, defaultVal: string): string`. `asBoolean` truthy set: `true`, `1`, `'1'`, `'true'`, `'yes'`, `'on'` (case-insensitive); all others `false` (FR-007)
- `AdminConfig` interface: strictly typed, no `any`/`unknown` fields (NFR-003)
- `useAdminConfig()`: reads `useRuntimeConfig()`, calls coercion helpers for each key, returns `readonly AdminConfig`. Uses `useState()` or `computed()` for referential stability (NFR-002). Exposes: `enableRealtime: boolean`, `appName: string`, `docsUrl: string`, `baseUrl: string`, `auth.registration: string`, `auth.requireVerifiedEmail: boolean`, `logoUrl: string | undefined`
- Unit tests: all truthy/falsy inputs for `asBoolean`, undefined/empty defaults for `asString`/`asUrl`, all registered flags (FR-011)
- `packages/admin/README.md`: add "Runtime config" section explaining `useAdminConfig()` contract and why direct `useRuntimeConfig().public.*` access is prohibited (FR-013)
**Verification**: `cd packages/admin && npm test` green; TypeScript build passes

### WP03 — Call-site migration (closes #1538)

**Scope**: All 14 files in `packages/admin/app/` that currently call `useRuntimeConfig()` or access `config.public.*` directly
**Touches**: 14 existing files
**Call-site inventory** (from grep, 2026-05-20):
| File | References | Migration note |
|------|-----------|----------------|
| `app/plugins/admin.ts` | 1 | `useAdminConfig()` (any public flags used) |
| `app/middleware/auth.global.ts` | 2 | `useAdminConfig().auth.*` |
| `app/components/layout/AdminShell.vue` | 2 | `useAdminConfig().appName` |
| `app/components/auth/BrandPanel.vue` | 2 | `useAdminConfig().appName` |
| `app/components/schema/SchemaList.vue` | 2 | `useAdminConfig().enableRealtime` — remove `String(x)==='1'` |
| `app/composables/useApi.ts` | 1 | `useAdminConfig().baseUrl` |
| `app/pages/index.vue` | 3 | `useAdminConfig().appName`, `useAdminConfig().docsUrl` |
| `app/pages/register.vue` | 4 | `useAdminConfig().logoUrl`, `.auth.*` |
| `app/pages/login.vue` | 3 | `useAdminConfig().logoUrl`, `.auth.*` |
| `app/pages/forgot-password.vue` | 2 | `useAdminConfig().logoUrl` |
| `app/pages/reset-password.vue` | 2 | `useAdminConfig().logoUrl` |
| `app/pages/[entityType]/index.vue` | 2 | `useAdminConfig().appName` |
| `app/pages/[entityType]/[id].vue` | 2 | `useAdminConfig().appName` |
| `app/pages/[entityType]/create.vue` | 2 | `useAdminConfig().appName` |
| `app/pages/[entityType]/pipeline.vue` | 1 | check for public flags |
| `app/pages/workflows/[id].vue` | 2 | `useAdminConfig().appName` |
| `app/pages/workflows/index.vue` | 2 | `useAdminConfig().appName` |
| `app/pages/telescope/codified-context/index.vue` | 2 | `useAdminConfig().appName` |
| `app/pages/telescope/codified-context/[sessionId].vue` | 2 | `useAdminConfig().appName` |
**Total**: 14 files, ~41 reference occurrences

**Key changes**:
- Replace all `useRuntimeConfig()` + `config.public.*` patterns with `useAdminConfig()` destructuring
- Remove `String(config.public.enableRealtime) === '1'` in `SchemaList.vue:19`
- Remove all `as Record<string, unknown>` type casts (replaced by typed `AdminConfig`)
- Verify no `useRuntimeConfig` or `config.public` references remain in `packages/admin/app/**` after migration
**Verification**: `cd packages/admin && npm run build && npm test && npm run lint` all green; `bin/check-admin-coercion-patterns` returns zero matches
**Closes**: #1538 via `Closes #1538` in commit footer

### WP04 — Coercion-pattern CI gate + Playwright regression + wrap-up

**Scope**: `bin/check-admin-coercion-patterns` (new) + `packages/admin/e2e/schema-dedup.spec.ts` (new) + CHANGELOG + README finalization
**Touches**: 2 new files + 1–2 existing files
**Key changes**:
- `bin/check-admin-coercion-patterns`: small bash script; `grep -rn "String(.*) === '1'\|String(.*) === \"1\"" packages/admin/app/`; exits 1 if matches found; documents `// allow-coercion: <reason>` suppression convention for future false positives; wired into admin SPA's CI step (or called from `composer verify`)
- `packages/admin/e2e/schema-dedup.spec.ts`: Playwright spec that boots dev server, visits `/admin/node`, intercepts network requests, asserts exactly one POST to `/admin/_surface/node/action/schema` per fresh visit (FR-012, SC-002)
- `CHANGELOG.md`: add bullets under `[Unreleased]` for #1537 fix (schema dedup) and #1538 fix (typed config envelope)
- `packages/admin/README.md`: finalize "Runtime config" section from WP02 if any polish needed
**Verification**: `cd packages/admin && npm run build && npm test && npm run test:e2e && npm run lint` all green; `bin/check-admin-coercion-patterns` exits 0; `composer verify` passes

---

## Phase 0: Research

All planning questions are answered by the spec (self-contained). Research is minimal; see `research.md` for decisions documented.

**No outstanding NEEDS CLARIFICATION items.**

---

## Complexity Tracking

No charter violations. All gates pass.

---

## Engineering Alignment

| Dimension | Decision |
|-----------|---------|
| In-flight dedup pattern | Module-scoped `Map<string, Promise<EntitySchema>>` — no new abstractions, matches existing `schemaCache` shape |
| Config envelope | New `useAdminConfig()` composable with `useState()`-backed stability; pure `asBoolean`/`asString` helpers in `configCoercion.ts` |
| No new deps | Native TypeScript only (C-004) |
| CI gate | Shell grep script (`bin/check-admin-coercion-patterns`) — spec's recommended approach |
| Branch | `main → main` |
| Closes | #1537 (WP01), #1538 (WP03) |

---

## Branch Contract (final reminder per protocol)

- **Current branch at plan start**: `main`
- **Planning/base branch**: `main`
- **Merge target**: `main`

Next step: run `/spec-kitty.tasks` to generate work packages.
