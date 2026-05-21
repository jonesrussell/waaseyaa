# Tasks: Admin SPA — Realtime Config Contract

**Mission**: `admin-spa-realtime-config-contract-01KS3ST4`
**Generated**: 2026-05-20T23:57:32Z
**Branch strategy**: `main → main`
**Closes**: #1537, #1538

---

## Subtask Index

| ID | Description | WP | Parallel? |
|---|---|---|---|
| T001 | Add `inflightCache: Map<string, Promise<EntitySchema>>` to useSchema.ts | WP01 | No | [D] |
| T002 | Implement in-flight dedup in `fetch()` — return existing Promise if present | WP01 | No | [D] |
| T003 | Clear `inflightCache` on rejection; clear both caches in `invalidate()` | WP01 | No | [D] |
| T004 | Write unit tests FR-008 (no duplicate POSTs), FR-009 (invalidate clears inflight), FR-010 (rejection doesn't poison) | WP01 | [D] |
| T005 | Create `configCoercion.ts` with `asBoolean`, `asString`, `asUrl` helpers (FR-007) | WP02 | [D] |
| T006 | Create `AdminConfig` TypeScript interface (no `any`/`unknown` fields, NFR-003) | WP02 | No | [D] |
| T007 | Create `useAdminConfig()` composable with `useState()`-backed referential stability (NFR-002) | WP02 | No | [D] |
| T008 | Write unit tests for coercion helpers and composable (FR-011) | WP02 | [D] |
| T009 | Add "Runtime config" section to `packages/admin/README.md` (FR-013) | WP02 | [P] |
| T010 | Migrate `app/plugins/admin.ts`, `app/middleware/auth.global.ts`, `app/composables/useApi.ts` | WP03 | No |
| T011 | Migrate all `app/components/**` files (AdminShell.vue, BrandPanel.vue, SchemaList.vue — remove String coerce) | WP03 | [P] |
| T012 | Migrate all `app/pages/**` files (14 page files) | WP03 | [P] |
| T013 | Verify no residual `useRuntimeConfig`/`config.public` references remain in `packages/admin/app/**` | WP03 | No |
| T014 | Create `bin/check-admin-coercion-patterns` CI gate shell script (NFR-004) | WP04 | No |
| T015 | Create `packages/admin/e2e/schema-dedup.spec.ts` Playwright network-capture spec (FR-012) | WP04 | [P] |
| T016 | Add `CHANGELOG.md` `[Unreleased]` entries for #1537 and #1538 | WP04 | [P] |
| T017 | Final verification: full CI suite green (`composer verify` + `npm run build && npm test && npm run test:e2e && npm run lint`) | WP04 | No |

---

## Work Packages

### WP01 — `useSchema()` In-Flight Dedup (closes #1537)

**Goal**: Add an in-flight Promise tracker to `useSchema.ts` so concurrent `fetch()` calls for the same entityType return the same Promise rather than issuing duplicate HTTP requests.
**Priority**: High — regression fix; closes #1537
**Independent test**: `cd packages/admin && npm test` (useSchema unit tests green)
**Prompt file**: `tasks/WP01-useschema-inflight-dedup.md`
**Estimated prompt size**: ~300 lines
**Dependencies**: none

#### Included subtasks

- [x] T001 Add `inflightCache: Map<string, Promise<EntitySchema>>` to useSchema.ts (WP01)
- [x] T002 Implement in-flight dedup in `fetch()` — return existing Promise if present (WP01)
- [x] T003 Clear `inflightCache` on rejection; clear both caches in `invalidate()` (WP01)
- [x] T004 Write unit tests FR-008, FR-009, FR-010 (WP01)

#### Implementation sketch

1. Add module-level `inflightCache: Map<string, Promise<EntitySchema>>` alongside `schemaCache`.
2. In `fetch(entityType)`: check `inflightCache.get(entityType)` first; if present, return it. Otherwise, create the POST Promise, register in `inflightCache`, add `.then()` to write `schemaCache` and delete from `inflightCache`, add `.catch()` to delete from `inflightCache`. Return the registered Promise.
3. In `invalidate(entityType)`: delete from both `schemaCache` and `inflightCache`.
4. Write three Vitest tests using `vi.fn()` or `@nuxt/test-utils` mock for `$fetch`: (a) two concurrent fetches → one POST; (b) invalidate mid-flight → next fetch issues new POST; (c) rejected fetch → next fetch issues fresh POST.

#### Parallel opportunities

- T004 (unit tests) can be written in parallel with T001–T003 once the interface of the change is clear.

#### Risks

- Vitest mock for `$fetch` / `useFetch` in Nuxt context requires `@nuxt/test-utils` setup. If the composable calls Nuxt-specific APIs that resist mocking, the tests may need to use the Playwright E2E path instead. Document the decision in the WP.

---

### WP02 — `useAdminConfig()` Composable + Coercion Helpers

**Goal**: Create the typed runtime-config envelope composable, coercion helpers, and AdminConfig interface. Add unit tests and README documentation.
**Priority**: High — foundational for WP03 migration
**Independent test**: `cd packages/admin && npm test` (configCoercion + useAdminConfig unit tests green)
**Prompt file**: `tasks/WP02-useadminconfig-composable.md`
**Estimated prompt size**: ~350 lines
**Dependencies**: none (independent — can start simultaneously with WP01)

#### Included subtasks

- [x] T005 Create `configCoercion.ts` with `asBoolean`, `asString`, `asUrl` helpers (WP02)
- [x] T006 Create `AdminConfig` TypeScript interface (WP02)
- [x] T007 Create `useAdminConfig()` composable (WP02)
- [x] T008 Write unit tests for coercion helpers and composable (WP02)
- [ ] T009 Add "Runtime config" section to README.md (WP02)

#### Implementation sketch

1. Create `packages/admin/app/utils/configCoercion.ts`: export `asBoolean(v: unknown, def?: boolean): boolean`, `asString(v: unknown, def: string): string`, `asUrl(v: unknown, def: string): string`. `asBoolean` truthy inputs: `true`, `1`, `'1'`, `'true'`, `'yes'`, `'on'` (case-insensitive).
2. Create `packages/admin/app/types/AdminConfig.ts`: `interface AdminConfig` with fully typed fields — `enableRealtime: boolean`, `appName: string`, `docsUrl: string`, `baseUrl: string`, `logoUrl: string | undefined`, `auth: { registration: string; requireVerifiedEmail: boolean }`.
3. Create `packages/admin/app/composables/useAdminConfig.ts`: call `useRuntimeConfig()`, coerce each public key via helpers, use `useState('adminConfig', ...)` for referential stability (NFR-002). Return `readonly AdminConfig`.
4. Write `packages/admin/tests/utils/configCoercion.test.ts` and `packages/admin/tests/composables/useAdminConfig.test.ts`.
5. Edit `packages/admin/README.md`: add "## Runtime config" section.

#### Parallel opportunities

- T005 and T009 are independent of each other and can proceed in parallel with T006/T007.

#### Risks

- `useState()` key collision if the composable is called in SSR vs. CSR contexts — use a stable, unique key like `'waaseyaa-admin-config'`.
- Nuxt's `useRuntimeConfig()` may not be available in Vitest unit tests without `@nuxt/test-utils` wrappers — mock appropriately.

---

### WP03 — Call-Site Migration (closes #1538)

**Goal**: Sweep all 14 files in `packages/admin/app/` that directly consume `useRuntimeConfig()` or `config.public.*`; migrate every reference to `useAdminConfig()`. Remove the `String(x) === '1'` workaround. ~41 occurrences across 14 files.
**Priority**: High — closes #1538; requires WP02
**Independent test**: `cd packages/admin && npm run build && npm test && npm run lint` all green
**Prompt file**: `tasks/WP03-callsite-migration.md`
**Estimated prompt size**: ~420 lines
**Dependencies**: WP02

#### Included subtasks

- [ ] T010 Migrate plugins, middleware, and composable files (WP03)
- [ ] T011 Migrate component files (AdminShell.vue, BrandPanel.vue, SchemaList.vue) (WP03)
- [ ] T012 Migrate all page files (WP03)
- [ ] T013 Verify zero residual references (WP03)

#### Implementation sketch

1. **Group 1 — Plugins/Middleware/Composables** (T010): Edit `app/plugins/admin.ts` (1 ref), `app/middleware/auth.global.ts` (2 refs), `app/composables/useApi.ts` (1 ref). Replace `useRuntimeConfig()` + `config.public.*` with `useAdminConfig()` destructuring.
2. **Group 2 — Components** (T011): Edit `app/components/layout/AdminShell.vue` (2 refs), `app/components/auth/BrandPanel.vue` (2 refs), `app/components/schema/SchemaList.vue` (2 refs — remove `String(config.public.enableRealtime) === '1'`). 
3. **Group 3 — Pages** (T012): Edit all 11 page files (3 root pages + 4 entityType pages + 2 workflow pages + 2 telescope pages). Most are `appName`/`logoUrl`/`auth.*` reads.
4. **Verification** (T013): Run `grep -rn "useRuntimeConfig\|config\.public" packages/admin/app/` — must return zero matches. Run `bin/check-admin-coercion-patterns` — must return zero matches.

#### Parallel opportunities

- T011 and T012 are independent and could be parallelized (different file groups), but since they share the same owned_files glob, a single agent performs all three groups sequentially.

#### Risks

- A page file may destructure `config.public` into a local variable and use that variable elsewhere — search for the variable name too, not just the initial access.
- Verify TypeScript compilation after each group to catch type errors early rather than at the end.

---

### WP04 — CI Gate + Playwright Regression + Wrap-Up

**Goal**: Add `bin/check-admin-coercion-patterns` CI gate, the Playwright network-capture spec (FR-012), CHANGELOG entries, and final verification sign-off.
**Priority**: High — completes NFR-004, SC-002; full CI must be green on merge
**Independent test**: `cd packages/admin && npm run test:e2e` (Playwright spec passes); `bin/check-admin-coercion-patterns` exits 0
**Prompt file**: `tasks/WP04-ci-gate-playwright-wrapup.md`
**Estimated prompt size**: ~320 lines
**Dependencies**: WP01, WP02, WP03

#### Included subtasks

- [ ] T014 Create `bin/check-admin-coercion-patterns` CI gate script (WP04)
- [ ] T015 Create `packages/admin/e2e/schema-dedup.spec.ts` Playwright spec (WP04)
- [ ] T016 Add `CHANGELOG.md` `[Unreleased]` entries (WP04)
- [ ] T017 Final verification — full CI suite (WP04)

#### Implementation sketch

1. **CI gate** (T014): `bin/check-admin-coercion-patterns` — bash script that greps `packages/admin/app/**` for `String(.*) === '1'` and analogous patterns; exits 1 on any match; documents `// allow-coercion: <reason>` inline suppression convention; mark executable (`chmod +x`). Wire into `composer verify` (or document how to call it from CI).
2. **Playwright spec** (T015): `packages/admin/e2e/schema-dedup.spec.ts` — `page.route()` to intercept `/admin/_surface/*/action/schema`; navigate to `/admin/node`; wait for network idle; assert exactly one POST was intercepted. Use `test.beforeEach` to clear any cached state.
3. **CHANGELOG** (T016): Add two bullets under `[Unreleased]` in `CHANGELOG.md`:
   - `fix(admin): deduplicate concurrent useSchema() fetch() calls (#1537)`
   - `fix(admin): typed useAdminConfig() envelope eliminates digit-string coercion workarounds (#1538)`
4. **Final verification** (T017): Run the full local suite: `composer verify`, `cd packages/admin && npm run build && npm test && npm run test:e2e && npm run lint`, `bin/check-admin-coercion-patterns`. All must be green. Commit with `Closes #1537` and `Closes #1538` in footer.

#### Parallel opportunities

- T014 and T015 are independent and can be developed in parallel if multiple agents are available.
- T016 is a trivial edit that can be done alongside T014/T015.

#### Risks

- Playwright E2E requires `nuxt dev` running on port 3000. Verify the `test:e2e` npm script handles dev-server lifecycle (`nuxt dev` + Playwright startup). If not wired, add `webServer` config to `playwright.config.ts`.
- The Playwright spec must account for any auth redirect on `/admin/node` — may need to set up an authenticated session or stub the auth middleware.

---

## Execution Lanes

**Lane A** (can start immediately): WP01, WP02
**Lane B** (after WP02 done): WP03
**Lane C** (after WP01, WP02, WP03 done): WP04

```
[WP01] ─────────────────────────────┐
                                    ├─► [WP04]
[WP02] ──────────► [WP03] ──────────┘
```
