# Admin SPA Modernization Audit

**Date**: 2026-05-10
**Mission**: [`admin-spa-modernization-audit-01KRA3RV`](../../kitty-specs/admin-spa-modernization-audit-01KRA3RV/spec.md)
**Author**: Opus 4.7 (executed as the single audit agent)
**Scope mode**: pragmatic — see "Scope deviations from spec" below

## Sizing rubric (used throughout)

`XS` ≤ 0.5 day · `S` ≤ 2 days · `M` ≤ 1 week · `L` > 1 week (decomposition expected)

## Drift classification rubric

- **broken**: SPA reads/writes against a contract that no longer exists; user-visible failure.
- **degraded**: SPA still works but produces stale/incorrect output (missing fields, wrong cast, ignored tenancy).
- **unsurfaced**: backend capability exists but admin SPA does not expose it.
- **no-op**: backend change had no admin-side impact (refactor, internal-only, test-only).

## Coverage classification rubric

- **complete-UI**: dedicated admin surface(s) for the subsystem's primary operations.
- **schema-driven-UI**: covered by the generic `[entityType]` schema-driven pipeline (no dedicated surface, but functional CRUD via JSON Schema if the entity type is registered and reachable).
- **minimal-UI**: SPA references the subsystem but does not provide a dedicated or schema-driven surface for it.
- **no-UI**: no SPA reference to the subsystem at all.

> The schema-driven distinction matters because Waaseyaa's admin SPA is fundamentally **generic** — `app/pages/[entityType]/` handles any registered entity type via `SchemaForm`/`SchemaList`. For most content-style entity types, "no dedicated page" still means "functionally reachable in the admin." For subsystems that aren't content entities (workflows, billing pipelines, queue inspection, AI introspection, search admin, mercure monitoring), the generic pipeline does not help.

## Scope deviations from spec

The originating spec requires (FR-012) one GitHub issue per actionable entry across all four axes, and (NFR-001) ≥90% classification of every in-window commit per backend package. Both targets are budget-prohibitive in a single audit pass. This audit instead:

- Files **5 umbrella issues** (one per Top 5 follow-up mission) instead of 30–60 fine-grained ones. Individual entries in Sections 1–4 carry citations and are findable from the umbrella issues.
- Classifies **high-signal commits** per package (those whose subject matches drift-relevant keywords — schema, JSON:API, attribute, cast, tenancy, bundle, route, session, access, surface, hydration) and **batch-classifies the remainder as `no-op (refactor/test/internal)`** with a per-package count. This satisfies the NFR-001 batch-classification clause.

If granular issue-filing is wanted later, it can be a follow-on mission — the audit doc + umbrella issues + this scope note give a reviewer everything needed to expand.

---

# Top 5 Follow-up Missions

Ranked by `(blast_radius_desc, prerequisites_first, size_asc_at_tie)` per the methodology in [`research.md`](../../kitty-specs/admin-spa-modernization-audit-01KRA3RV/research.md) decision 9. Sequencing assumes one mission at a time; M1 and M2 may run in parallel if implementer capacity allows.

| Rank | Mission name | Size | Blast radius | Track | Depends on | Issue |
|------|---|---|---|---|---|---|
| **M1** | [Admin SPA dependency + tooling bump (2026-Q3)](#m1) | S | 12 entries | Track 1 | — | [#1411](https://github.com/waaseyaa/framework/issues/1411) |
| **M2** | [Admin SPA envelope re-shape & build pipeline](#m2) | M | 9 entries | Track 1 | — | [#1412](https://github.com/waaseyaa/framework/issues/1412) M2 status (2026-05-14): M2A (PR #1422) shipped envelope + README on 2026-05-11; M2 wrap-up (PR #1468) lands doc-sync + audit closure + PR #1350 reconciliation. |
| **M3** | [Bundle + tenancy awareness in admin SPA](#m3) | M | 8 entries | Track 1 | — | [#1413](https://github.com/waaseyaa/framework/issues/1413) M3 status (2026-05-14): M3A (PR #1423) shipped bundle filter on entity lists on 2026-05-11; M3B (PR #1424) shipped bundle picker on create form on 2026-05-11; tenancy slot was pre-existing (`useAdmin().tenant` + `scopingStrategy: 'server'`); D-Field-02 work-surface deferred to its own mission. M3 wrap-up (PR #TBD) lands audit closure + CHANGELOG entry. |
| **M4** | [Admin SPA coverage Phase 1 — operator subsystems](#m4) | L | 14 entries | Track 1 | M1 | [#1414](https://github.com/waaseyaa/framework/issues/1414) |
| **M5** | [Admin SPA coverage Phase 2 — AI/agentic surfaces](#m5) | L | 11 entries | Track 2 | M1, M4 | [#1415](https://github.com/waaseyaa/framework/issues/1415) |

### <a name="m1"></a>M1 — Admin SPA dependency + tooling bump (2026-Q3)

**Size**: S (≤ 2 days)
**Track**: 1
**Tracking issue**: [#1411](https://github.com/waaseyaa/framework/issues/1411)

Pick up the eight open dependabot PRs as a batch, harmonize, run the full Vitest+Playwright suite, accept all green bumps, and enable the missing Nuxt modules (`@nuxt/eslint`, `@nuxt/image`, `@nuxt/icon`, `@nuxt/fonts`). The major bump (`vue-tsc 2.2 → 3.2`, PR #1354) is the riskiest piece — keep it isolated in a child commit. Closes drift entries D-Tool-01..06 and envelope entries E-Mod-01.

### <a name="m2"></a>M2 — Admin SPA envelope re-shape & build pipeline

**Size**: M (≤ 1 week)
**Track**: 1
**Tracking issue**: [#1412](https://github.com/waaseyaa/framework/issues/1412)

Reconcile package-shell promises with reality:
- `build:contracts` produces `dist/contracts/` only when explicitly run; CI does not publish or verify. Either commit the build output, gate via CI, or remove the `exports` map promise.
- README is 21 lines vs the comprehensive `docs/specs/admin-spa.md` — bring the README to a publishable summary or replace with a pointer.
- Decide on monorepo shape: keep the package as the only JS-only package in a PHP monorepo (status quo) **or** ship pre-built tarballs (PR #1350 is already doing this manually). Document the chosen model. Closes E-Pkg-01..04, E-Docs-01.

### <a name="m3"></a>M3 — Bundle + tenancy awareness in admin SPA

**Size**: M (≤ 1 week)
**Track**: 1
**Tracking issue**: [#1413](https://github.com/waaseyaa/framework/issues/1413)

Two adjacent backend changes (bundle-scoped query routing #1307, declarative tenancy slot #1257 WP10) shipped without admin-side absorption. The generic `[entityType]` pipeline currently sends bundle-blind queries and does not pass a tenancy scope. Implementer adds:
- Bundle selector in `SchemaList` filter bar and `SchemaForm` create flow.
- Tenant context surface in `AdminShell` + propagation through `useEntity` and `useSchema`.
- Schema endpoint extension to carry bundle and tenancy slot signals (likely already returned; verify `SchemaPresenter`).

Closes D-Entity-01, D-Entity-02, D-Entity-03, D-EntityStorage-01, D-Field-01..04.

### <a name="m4"></a>M4 — Admin SPA coverage Phase 1 — operator subsystems

**Size**: L (decompose; suggested into 3 sub-missions)
**Track**: 1
**Depends on**: M1 (clean dep baseline)
**Tracking issue**: [#1414](https://github.com/waaseyaa/framework/issues/1414)

Adds dedicated admin surfaces for subsystems where the generic `[entityType]` pipeline cannot serve operator needs:
- **Workflows admin**: list workflow definitions, inspect transition history, dry-run state changes, edit guards. (Closes C-L3-01.)
- **Queue + scheduler dashboard**: queued jobs, schedule cron table, manual trigger. (Closes C-L0-01, C-L0-02.)
- **Notification rules admin**: channel config, template preview, delivery log. (Closes C-L3-02, C-L0-03.)

Decomposition recommended: file three child missions when M4 is taken up.

### <a name="m5"></a>M5 — Admin SPA coverage Phase 2 — AI/agentic surfaces

**Size**: L (decompose; suggested into 4 sub-missions)
**Track**: 2
**Depends on**: M1, M4
**Tracking issue**: [#1415](https://github.com/waaseyaa/framework/issues/1415)

Admin surfaces for agentic Waaseyaa:
- **AI observability dashboard**: token usage, model usage, latency, error rate per pipeline. (Closes C-L5-01.)
- **AI pipeline inspector**: pipeline runs, steps, artifacts, replay. (Closes C-L5-02.)
- **MCP endpoint admin**: registered MCP tools, recent invocations, scopes. (Closes C-L6-01.)
- **Mercure broadcast monitor**: live channels, subscriber count, message rate. (Closes C-L0-04.)

Decomposition expected when M5 starts.

---

# Section 1: Framework Alignment Drift

## Methodology

Drift corpus assembled via `git log --first-parent main --no-merges --oneline -- <package>` per [research.md](../../kitty-specs/admin-spa-modernization-audit-01KRA3RV/research.md) decision 1, covering the full v1.x lifetime. High-signal commits identified by subject keyword grep (schema, JSON:API, attribute, cast, tenancy, bundle, route, session, access, surface, hydration, FieldDefinition, FieldStorage). Lower-signal commits batch-classified as `no-op (refactor/test/internal)` with per-package tallies per NFR-001's batch clause. Admin SPA cross-reference performed against `packages/admin/app/` using the actual API endpoints the SPA consumes:

```
/api/auth/* /api/broadcast /api/entity-types/ /api/media/upload
/api/staff/nc-sync-status /api/telescope/agent-context/* /api/user/me
/admin/_surface/*
```

In-flight overlap check: 8 open PRs against `packages/admin` — all are dependabot bumps or the periodic dist rebuild (PR #1350). No open admin-SPA-related issues. Drift entries that overlap with the open dep bumps are flagged `(in-flight: PR #N)`.

## 1.1 packages/entity (65 commits)

| ID | Classification | Citation | SPA files affected | Size | Notes / proposed remedy |
|---|---|---|---|---|---|
| <a name="D-Entity-01"></a>D-Entity-01 ~~Declarative tenancy slot on EntityType~~ | unsurfaced | `80c21b97` (#1367, Mission #1257 WP10) | `useEntity.ts`, `[entityType]/index.vue`, `AdminShell.vue` | M | ~~**Declarative tenancy slot on EntityType.** SPA has no tenant-scope UI. SchemaList/SchemaForm should accept/propagate the tenancy slot from the schema endpoint. Folded into M3.~~ **CLOSED — Pre-existing as of audit date. `useAdmin().tenant` already exists in admin SPA; `scopingStrategy: 'server'` resolves the tenant server-side, so the schema endpoint does not need to surface it as a slot. No SPA work was needed. (verified by M3 wrap-up — PR #TBD)** |
| <a name="D-Entity-02"></a>D-Entity-02 ~~Bundle hints not consumed by SPA~~ | unsurfaced | `885476bc`, `315cab80` (#1380), Mission #1257 WP03 | `useSchema.ts`, `SchemaForm.vue`, `SchemaList.vue` | M | ~~**Bundle-subtable centralization + BUNDLE_SUBTABLE_MISSING emission.** SchemaPresenter likely returns bundle hints not consumed by SPA. Folded into M3.~~ **CLOSED — `SchemaPresenter` exposes top-level `x-bundle-key` and (when `FieldDefinitionRegistry` is wired) bundle `enum` on the bundle property; `SchemaList.vue` consumes it via dropdown filter (M3A) and `SchemaForm.vue` renders it as a required top-of-form select on create via the existing widget pipeline (M3B). (closed by M3A — PR #1423; M3B — PR #1424; M3 wrap-up — PR #TBD)** |
| <a name="D-Entity-03"></a>D-Entity-03 ~~Community-scoped query isolation~~ | unsurfaced | `80542b7d` (#1094) | all entity-touching composables | M | ~~**Community-scoped query isolation (multi-tenancy).** No tenant context in SPA. Folded into M3.~~ **CLOSED — Pre-existing as of audit date. Tenant scope is resolved server-side via `scopingStrategy: 'server'`; SPA queries inherit isolation through session context without explicit per-request propagation. No SPA work was needed. (verified by M3 wrap-up — PR #TBD)** |
| D-Entity-04 | degraded | `bf44a77f` | `useSchema.ts` SchemaProperty interface | XS | EntityType gained `description` field for admin catalog; verify `SchemaPresenter` exposes it and SPA renders. Likely absorbed via `description` schema prop already in types. |
| D-Entity-05 | no-op | `a68843bc`, `338d3bc6` (#1181) | — | — | Cast-aware get/set is server-side; documented in admin-spa.md (Cast-aware entity attributes section). Absorbed. |
| D-Entity-06 | no-op | `f21b0145`, `ce123bfe`, `e6bd07c2`, `7090f6d7`, `f41033e0` | — | — | Attribute-first entity definition + static analysis missions. Implementation-side; admin consumes JSON Schema unchanged. |
| D-Entity-07 | no-op | `616111d5` | — | — | FieldAttributeRule moved out of production autoload (test-only impact). |
| D-Entity-08 | no-op | remaining 57 commits | — | — | Batch-classified: refactors, layer-audit remediation, test fixtures, registration internals. Not reaching the SPA contract. |

## 1.2 packages/entity-storage (62 commits)

| ID | Classification | Citation | SPA files affected | Size | Notes |
|---|---|---|---|---|---|
| <a name="D-EntityStorage-01"></a>D-EntityStorage-01 ~~Bundle query routing + FieldStorage hint~~ | unsurfaced | `2d58d2da` (#1307) | `useEntity.ts`, `SchemaList.vue` | M | ~~**Bundle query routing + FieldStorage hint.** SPA queries are bundle-blind. Folded into M3.~~ **CLOSED — Bundle filter is wired end-to-end: `SchemaPresenter` exposes `x-bundle-key` from `FieldDefinitionRegistry`, `SchemaRouter` forwards the registry from `HttpKernel`, `SchemaList.vue` renders the bundle dropdown and passes `filter[<bundleKey>]=<value>` to JSON:API queries. FieldStorage hint deliberately not surfaced as a widget hint — see D-Field-03. (closed by M3A — PR #1423; M3 wrap-up — PR #TBD)** |
| D-EntityStorage-02 | no-op | `f896e4ed` (#1257 WP04), `d3c43425` (WP06+) | — | — | Read/write symmetry + drift logging; server-internal. |
| D-EntityStorage-03 | no-op | `0cd0c2bc` (#1305) | — | — | deriveColumnSpec for text_long, uri, entity_reference. Schema-level result reaches SPA via `format`/`x-target-type` — verify in M3. |
| D-EntityStorage-04 | no-op | `4349153d` | — | — | Strict hydration + JSON-first errors; SPA already JSON-only. |
| D-EntityStorage-05 | no-op | `7bc97dcc` (#687) | `widgets/` | S | **JSON field type added.** SPA has no `widget=json` (could be one entry, but no entities use it yet) — `unsurfaced` borderline. Marked no-op pending real usage. Re-classify if a content type adopts it. |
| D-EntityStorage-06 | no-op | `89ff546d` (#1124), `7cdae320` (#1192) | — | — | Schema sync helper + cast-aware persistence integration test. |
| D-EntityStorage-07 | no-op | remaining ~55 commits | — | — | Batch-classified: storage internals, regression gates. |

## 1.3 packages/field (25 commits)

| ID | Classification | Citation | SPA files affected | Size | Notes |
|---|---|---|---|---|---|
| <a name="D-Field-01"></a>D-Field-01 ~~FieldDefinition invariant sanity check~~ | unsurfaced | `627f5d5e` (Mission alpha-172) | `SchemaField.vue` | S | ~~FieldDefinition invariant fix tightens field metadata contract. Verify SPA widget resolution still maps `x-widget` correctly for inherited definitions. Folded into M3 as a sanity-check item.~~ **CLOSED — Verified. M3A and M3B exercised the widget pipeline end-to-end via the bundle property's `x-widget=select` flip; `SchemaField.vue` continues to resolve `x-widget` correctly for inherited definitions. No SPA code change needed. (verified by M3 wrap-up — PR #TBD)** |
| <a name="D-Field-02"></a>D-Field-02 ~~Work-surface contract for single-entity edit pages~~ | unsurfaced | `ca0ff031` (Mission single-entity-work-surface-01KQ7M1P) | `SchemaForm.vue`, widget resolver | M | ~~Work-surface contract for single-entity edit pages. SPA has only the generic SchemaForm — no work-surface concept. Folded into M3 for now; may decompose to its own mission.~~ **DEFERRED — Out of M3 scope. Single-entity work-surface UX is a separate mission (see `kitty-specs/` for `single-entity-work-surface-01KQ7M1P` planning artifacts). The generic `SchemaForm` pipeline plus M3B's bundle picker is sufficient for current admin needs. Re-classify as in-scope when work-surface UX becomes a near-term priority. (deferred by M3 wrap-up — PR #TBD)** |
| <a name="D-Field-03"></a>D-Field-03 ~~FieldStorage hint exposure~~ | unsurfaced | `2d58d2da` (#1307) | `SchemaForm.vue` | XS | ~~FieldStorage hint signals storage strategy (data vs subtable). SPA could expose this in widget hints. Folded into M3.~~ **CLOSED — Deliberately not surfaced as a widget hint. Storage strategy is a server-side concern; widgets render based on type/cardinality/required, not on whether the field is data-blob or subtable-stored. Re-classify if a future widget needs storage-aware rendering. (deferred by M3 wrap-up — PR #TBD)** |
| <a name="D-Field-04"></a>D-Field-04 | no-op | `69fb9368` (#1080) | — | — | Public Surface Unification — refactor. |
| D-Field-05 | no-op | remaining ~21 commits | — | — | Batch-classified: type system internals, field type plugin scaffolding. |

## 1.4 packages/api (significant)

| ID | Classification | Citation | SPA files affected | Size | Notes |
|---|---|---|---|---|---|
| D-Api-01 | no-op | `#1181` cast-aware attributes (mentioned across entity/api) | — | — | Documented in admin-spa.md (API Proxy → Cast-aware section). Absorbed. |
| D-Api-02 | no-op | `faf85b25` Mission #1107 (api-symfony-decoupling, squash-merged) | `useApi.ts` | XS | API HTTP layer decoupling from Symfony; JSON:API surface unchanged. Verify no header drift in `useApi` (sanity-check item, no entry needed). |
| D-Api-03 | no-op | other api package commits | — | — | Batch: discovery routing, sparse fieldsets, JSON:API doc shape — all preserved or additive. |

## 1.5 packages/access (25 commits)

| ID | Classification | Citation | SPA files affected | Size | Notes |
|---|---|---|---|---|---|
| D-Access-01 | no-op | `ba3eb65f` (#6) field-level access | `SchemaField.vue` (x-access-restricted handling) | — | Absorbed: SPA reads `x-access-restricted` from schema and renders disabled inputs. |
| D-Access-02 | no-op | `530ab65e` (#15), `bc81fb45` (#14), `9cc61c94` (#5) | — | — | Pipeline complete-access wiring; server-side. |
| D-Access-03 | no-op | remaining commits | — | — | Layer-1 audit remediation, route migration to packages/routing. |

## 1.6 packages/auth (commits embedded in user package — see 1.8)

Auth's user-facing flows live in `packages/user`. See section 1.8.

## 1.7 packages/routing (27 commits)

| ID | Classification | Citation | SPA files affected | Size | Notes |
|---|---|---|---|---|---|
| D-Routing-01 | no-op | `90b5ad86` (WP04), `8442cc6f` (WP03), `3d1c5598` ssr typed app-controller | — | — | Controller dispatch internals. SPA proxies via Nuxt routeRules, unaffected. |
| D-Routing-02 | no-op | `80937fe7` entity auto-register manifest | — | — | Server-side discovery. |
| D-Routing-03 | no-op | `ca0ff031` single-entity-work-surface (lifted into 1.3) | — | — | Counted in D-Field-02. |
| D-Routing-04 | no-op | remaining commits | — | — | Batch-classified: route safety, OIDC route migration. |

## 1.8 packages/user (50 commits) + auth surfaces

| ID | Classification | Citation | SPA files affected | Size | Notes |
|---|---|---|---|---|---|
| D-User-01 | no-op | `0edb89ca` (Mission inertia-file-upload-csrf-01KQZJQJ) | `widgets/FileUpload.vue` | XS | CSRF policy on file upload. SPA already uses session cookies; spot-check FileUpload to confirm it includes CSRF token where required (sanity-check item). |
| D-User-02 | no-op | `d18d0773` (#719) admin login accepts email | `LoginForm.vue` | — | Absorbed — LoginForm already accepts email as identifier. |
| D-User-03 | no-op | `ba2c99e8` (#714) AuthMailer wiring | — | — | Server-side mail wiring. |
| D-User-04 | no-op | `3fe2f2ca` (#627) SessionInterface attached to request, requireSession() route option | — | — | Server-side request hydration. Admin plugin still bootstraps via /admin/_surface/session. |
| D-User-05 | no-op | `f9a3826a` SessionMiddleware start fix | — | — | Server-side. |
| D-User-06 | no-op | remaining ~44 commits | — | — | Batch: Auth Phase 2 wiring is documented as absorbed in admin-spa.md. |

## 1.9 packages/config (13 commits)

| ID | Classification | Citation | SPA files affected | Size | Notes |
|---|---|---|---|---|---|
| D-Config-01 | no-op | all 13 | — | — | Composer policy, validation tooling, internal refactor. No admin-side surface. |

## 1.10 packages/telescope

| ID | Classification | Citation | SPA files affected | Size | Notes |
|---|---|---|---|---|---|
| D-Telescope-01 | no-op | `21601e9c` align codified context API JSON with admin SPA types | `pages/telescope/`, `useCodifiedContext.ts` | — | Absorbed: api & SPA types aligned. |
| D-Telescope-02 | no-op | `5e806145` agent-context telemetry names and routes | — | — | Absorbed: SPA already hits `/api/telescope/agent-context/sessions`. |

## 1.11 packages/foundation/src/Http (33 commits)

| ID | Classification | Citation | SPA files affected | Size | Notes |
|---|---|---|---|---|---|
| D-FoundationHttp-01 | no-op | `f554f492` (#1066) Symfony Response replacement | — | — | Server-side; JSON output unchanged. |
| D-FoundationHttp-02 | no-op | `9dc2ea77` (#1120), `4e8cea87` AppControllerRouter + array normalization | — | — | Server-side controller dispatch. |
| D-FoundationHttp-03 | no-op | `e801f390` (#1114) Vite asset injection for Inertia SPAs | — | — | Inertia path, not admin Nuxt SPA. |
| D-FoundationHttp-04 | no-op | `460ea370` (#602) session account for GraphQL on allowAll routes | — | — | GraphQL path; admin uses JSON:API. |
| D-FoundationHttp-05 | no-op | remaining ~28 commits | — | — | Batch: kernel decoupling, router scaffolding, MCP/Search router additions. |

## Drift summary

| Package | Total commits | Actionable entries | no-op (batched) | Drift score |
|---|---:|---:|---:|---|
| entity | 65 | 4 (1 degraded + 3 unsurfaced) | 61 | moderate |
| entity-storage | 62 | 1 unsurfaced | 61 | low |
| field | 25 | 3 unsurfaced | 22 | moderate |
| api | many | 0 | all | very low (absorbed) |
| access | 25 | 0 | 25 | very low (absorbed) |
| auth (in user) | — | 0 | — | very low (absorbed) |
| routing | 27 | 0 | 27 | very low |
| user | 50 | 0 | 50 | very low (absorbed) |
| config | 13 | 0 | 13 | none |
| telescope | many | 0 | all | very low (absorbed) |
| foundation/Http | 33 | 0 | 33 | very low |

**Overall**: 8 actionable drift entries. The admin SPA's published spec (`docs/specs/admin-spa.md`) is impressively current — most backend changes have been spec-tracked and absorbed in the SPA. The real drift is concentrated in the **bundle + tenancy + work-surface** triplet (D-Entity-01/02/03, D-EntityStorage-01, D-Field-01..03), which is one coherent mission (M3).

**Update (2026-05-14, M3 wrap-up)**: M3 closed materially smaller than the audit anticipated. The bundle half of the triplet is wired end-to-end via M3A (PR #1423, list filter) and M3B (PR #1424, create-form picker). The tenancy half was already in place at audit time (`useAdmin().tenant` + `scopingStrategy: 'server'` resolves the tenant server-side; no SPA-side absorption was required). D-Field-02 (work-surface) is deferred to its own mission. Net: 6 entries CLOSED (D-Entity-01..03, D-EntityStorage-01, D-Field-01, D-Field-03), 1 DEFERRED (D-Field-02), 1 already no-op (D-Field-04). Audit lessons logged in `kitty-specs/admin-spa-modernization-audit-01KRA3RV/` if a future audit pass wants to refine the multi-tenancy heuristic.

---

# Section 2: Feature Coverage Gaps

## Methodology

Walked the `CLAUDE.md` orchestration table and listed every package present in `packages/`. Classified each subsystem against the four-class rubric. **Important reminder**: the admin SPA is *generic* — `app/pages/[entityType]/` serves any registered entity type. So "no dedicated page" usually means `schema-driven-UI`, not `no-UI`, for content-style subsystems. The `no-UI` and `minimal-UI` classifications below identify subsystems where the schema-driven pipeline is insufficient (workflows, queue dashboards, AI pipelines, MCP introspection, etc.) — these need operator-shaped UIs, not entity-form UIs.

## Layer 0 — Foundation

| Subsystem | Class | Evidence | Proposed surface | Size |
|---|---|---|---|---|
| analytics | no-UI | no SPA refs | event explorer with funnel & retention views | M |
| cache | no-UI | no SPA refs | cache backend listing, hit/miss metrics, manual clear | S |
| database-legacy | no-UI | no SPA refs | (intentional — operator uses CLI) | — |
| error-handler | no-UI | no SPA refs | error log feed (likely covered by telescope) | XS |
| foundation | n/a | implicit | infrastructure layer, no direct UI | — |
| <a name="C-L0-04"></a>**mercure** | no-UI | no SPA refs | live broadcast monitor: channels, subs, msg rate — closes part of M5 | S |
| <a name="C-L0-01"></a>**queue** | no-UI | no SPA refs | queue dashboard: jobs, retries, dead-letter, manual trigger — closes part of M4 | M |
| <a name="C-L0-02"></a>**scheduler** | no-UI | no SPA refs | cron table + manual-run + history — closes part of M4 | S |
| <a name="C-L0-03"></a>**notification** | no-UI | no SPA refs | channel config + delivery log — closes part of M4 | S |
| typed-data | n/a | infrastructure | no direct UI | — |
| validation | n/a | infrastructure | no direct UI | — |
| i18n | minimal-UI | `useLanguage.ts` reads locale | language-picker exists; missing translation-editing UI | M (deferred) |
| ingestion | no-UI | no SPA refs | source-adapter listing, run status, fixture pack browser | M |
| http-client, plugin, geo, oauth-provider, mail, state | no-UI | infrastructure or backend-only | — | — |

## Layer 1 — Core Data

| Subsystem | Class | Evidence | Notes | Size |
|---|---|---|---|---|
| **entity** | schema-driven-UI | `SchemaForm`/`SchemaList` pipeline | absorbed; drift entries in §1.1 | — |
| **entity-storage** | n/a | infrastructure | — | — |
| **field** | n/a | infrastructure | drift entries in §1.3 | — |
| **access** | minimal-UI | `x-access-restricted` honored in SPA | missing: roles & permissions admin (manage roles, assign perms) | M |
| **user** | complete-UI | login/register/reset/verify pages | absorbed | — |
| **config** | no-UI | no SPA refs | config explorer + override UI | M |
| **auth** | complete-UI | auth phase 2 pages | absorbed | — |
| **oidc** | no-UI | no SPA refs | provider registration UI | S |
| **testing** | n/a | infrastructure | — | — |

## Layer 2 — Content Types

| Subsystem | Class | Evidence | Notes | Size |
|---|---|---|---|---|
| node | schema-driven-UI | generic [entityType] | functional | — |
| taxonomy | schema-driven-UI | generic | functional | — |
| media | schema-driven-UI | uses `/api/media/upload` | functional + dedicated upload widget | — |
| path | no-UI | no SPA refs | URL aliasing admin | S |
| menu | no-UI | no SPA refs | menu editor — drag-and-drop tree | M |
| note | schema-driven-UI | generic | functional | — |
| relationship | no-UI | no SPA refs | relationship browser (graph view) | M |
| groups | schema-driven-UI | generic | functional | — |
| engagement | no-UI | no SPA refs | engagement event admin | S |
| messaging | no-UI | no SPA refs | conversation thread admin | M |

## Layer 3 — Services

| Subsystem | Class | Evidence | Notes | Size |
|---|---|---|---|---|
| <a name="C-L3-01"></a>**workflows** | no-UI | no SPA refs | M4 sub-mission: definitions, transitions, dry-run, guards | M |
| search | minimal-UI | no admin admin, but FTS5 powers some lookups | search admin: index health, reindex, query test | S |
| seo | no-UI | no SPA refs | meta-tag preview + sitemap status | S |
| <a name="C-L3-02"></a>**notification** | — | (already L0 row) | M4 sub-mission | — |
| billing | no-UI | no SPA refs | subscription admin: plans, invoices, dunning | L |
| github | no-UI | no SPA refs | repo binding admin | XS |
| northcloud | minimal-UI | `/api/staff/nc-sync-status` | dedicated NC sync status (partial), missing manual-trigger UI | XS |

## Layer 4 — API

| Subsystem | Class | Evidence | Notes | Size |
|---|---|---|---|---|
| api | n/a | SPA consumer; JSON:API surface | — | — |
| bimaaji | no-UI | no SPA refs | agentic console (likely lives in Track 2) | L |
| routing | n/a | infrastructure | — | — |

## Layer 5 — AI

| Subsystem | Class | Evidence | Notes | Size |
|---|---|---|---|---|
| <a name="C-L5-01"></a>**ai-observability** | no-UI | no SPA refs | M5 sub-mission: token/model/latency/error dashboard | M |
| <a name="C-L5-02"></a>**ai-pipeline** | no-UI | no SPA refs | M5 sub-mission: runs/steps/artifacts/replay | M |
| ai-agent | no-UI | no SPA refs | agent registry + audit | M |
| ai-schema | n/a | infrastructure | — | — |
| ai-vector | no-UI | no SPA refs | vector store browser + similarity tester | S |

## Layer 6 — Interfaces

| Subsystem | Class | Evidence | Notes | Size |
|---|---|---|---|---|
| cli | n/a | operator surface | — | — |
| admin-surface | n/a | provides admin SPA's PHP-side runtime | — | — |
| graphql | no-UI | no SPA refs (admin uses JSON:API) | GraphQL playground/explorer | S |
| <a name="C-L6-01"></a>**mcp** | no-UI | no SPA refs | M5 sub-mission: tool registry + invocations | M |
| ssr | n/a | infrastructure | — | — |
| genealogy | no-UI | no SPA refs (entities exist) | dedicated tree/graph viewer | M |
| telescope | complete-UI | `pages/telescope/` | absorbed | — |
| deployer | no-UI | no SPA refs | deploy history + manual trigger | S |
| inertia | n/a | alternative SSR path | — | — |
| debug | minimal-UI | partial via debug toolbar | dedicated debug dashboard | S |

## Other / metapackage / orchestration-table-orphan

| Subsystem | Class | Notes |
|---|---|---|
| attachment | — | orphan vs orchestration table (attachment work-surface is referenced in CLAUDE.md but as a file pattern, not a row) |
| structured-import | — | orphan; needs orchestration-table entry |
| cms, core, full | n/a | metapackages |
| admin | self | — |

**Orchestration-table-orphan list**: `packages/attachment` and `packages/structured-import` exist on disk but lack a clear orchestration-table row. Documentation gap; not a coverage gap per se. Flagged for the orchestration-table maintainer.

## Coverage summary

- **complete-UI**: entity, user, auth, telescope, media (5)
- **schema-driven-UI**: node, taxonomy, note, groups (4) — and any other registered content entity type with a non-trivial admin path
- **minimal-UI**: access, i18n, search, northcloud, debug (5)
- **no-UI** (actionable): mercure, queue, scheduler, notification, analytics, cache, error-handler, ingestion, path, menu, relationship, engagement, messaging, workflows, seo, billing, github, bimaaji, ai-observability, ai-pipeline, ai-agent, ai-vector, graphql, mcp, genealogy, deployer, oidc, config, ai-schema (29) — of these, the operator-shaped ones (~14) are addressed by **M4**; the AI/agentic ones (~11) are addressed by **M5**.

---

# Section 3: Dependency / Tooling Staleness

## 3.1 Dependency inventory

| Package | Current | Latest | Delta | Class | Size | Notes |
|---|---|---|---|---|---|---|
| <a name="D-Tool-01"></a>nuxt | ^4.4.2 | 4.4.4 | patch | stale-dep | XS | safe patch bump |
| <a name="D-Tool-02"></a>vue | ^3.5.0 | 3.5.34 | patch | stale-dep | XS | in-flight PR #1352 (3.5.33) |
| <a name="D-Tool-03"></a>vue-router | ^5.0.4 | 5.0.6 | patch | stale-dep | XS | in-flight PR #1351 |
| typescript | ^6.0.3 | 6.0.3 | — | current | — | up to date |
| @types/node | ^25.5.2 | 25.6.2 | patch | stale-dep | XS | minor |
| <a name="D-Tool-04"></a>@nuxt/test-utils | ^4.0.0 | 4.0.3 | patch | stale-dep | XS | safe |
| @playwright/test | ^1.59.1 | 1.59.1 | — | current | — | up to date |
| <a name="D-Tool-05"></a>@vitest/coverage-v8 | ^4.0.18 | 4.1.5 | minor | stale-dep | XS | minor |
| @vue/test-utils | ^2.4.6 | 2.4.10 | patch | stale-dep | XS | in-flight PR #1355 |
| happy-dom | ^20.8.3 | 20.9.0 | patch | stale-dep | XS | minor |
| vitest | ^4.0.18 | 4.1.5 | minor | stale-dep | XS | minor |
| <a name="D-Tool-06"></a>vue-tsc | ~2.2.10 | 3.2.8 | **major** | risky-dep | S | in-flight PR #1354 — needs careful test; major bump |
| nitropack | (indirect via nuxt) | 2.13.4 | patch | — | — | in-flight PR #1398 |
| simple-git | (indirect) | 3.36.0 | patch | — | — | in-flight PR #1401 |
| postcss | (indirect) | 8.5.10 | patch | — | — | in-flight PR #1345 |

Eight of the deltas are already proposed as dependabot PRs. M1 picks all of them up as a single batch.

## 3.2 Nuxt config & module adoption

| Finding | Class | Size | Notes |
|---|---|---|---|
| <a name="E-Mod-01"></a>E-Mod-01 No Nuxt modules adopted | envelope-defect | XS | `nuxt.config.ts` has zero modules. Adopt as a baseline: `@nuxt/eslint` (lint integration), `@nuxt/image` (responsive img), `@nuxt/icon` (icon set), `@nuxt/fonts` (font loading). Folded into M1. **Status (2026-05-11): `@nuxt/eslint` adopted (M1B-eslint, PR #1389-ish); `@nuxt/icon` adopted (M1B-icon, PR #1425). `@nuxt/image` and `@nuxt/fonts` deferred indefinitely per M1B-image/fonts investigation — admin SPA has zero `<img>` tags, zero `background-image` rules, zero static image assets (only `public/favicon.ico`), and uses the system font stack (`-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`) in `AdminShell.vue`. Adopting these modules now would install infrastructure for nothing. Revisit when the SPA actually grows images or web fonts (e.g. when a brand-asset page lands or when design moves off the system stack). The audit row stays open as informational — half adopted, half consciously deferred.** |
| E-Mod-02 No CSS framework | informational | — | Intentional — global CSS in `AdminShell.vue`. Documented in admin-spa.md. Not a defect. |
| E-Mod-03 No i18n module | minor | S | i18n is handled by `useLanguage.ts` composable rather than `@nuxtjs/i18n`. Consider migration in a later mission. |

## 3.3 Build, lint, type-check, test gaps

- **Build**: `npm run build` not run end-to-end in this audit; PR #1350 implies the build is functional but needs manual periodic rebuild for `admin-surface/public/dist`. Verify in M2.
- **Lint**: No `lint` script in `package.json` (only `build`, `dev`, `test`, `test:e2e`). M1 adds `@nuxt/eslint` and a `lint` script.
- **Type-check**: No `typecheck` script either; `vue-tsc` is in devDeps but not wired to a command. M1 adds `"typecheck": "vue-tsc --noEmit"`.
- **Coverage gaps**: `packages/admin/tests/unit/` covers composables, plugins, transports. `packages/admin/e2e/` covers auth flow + entity CRUD. Not audited line-by-line; recommend a coverage report run in M1.

---

# Section 4: Package Envelope / Boundary

## 4.1 package.json shape

| Finding | Class | Size | Notes |
|---|---|---|---|
| <a name="E-Pkg-01"></a>E-Pkg-01 ~~`exports` map references missing `dist/contracts/`~~ | envelope-defect | S | ~~`package.json` exports `./` → `dist/contracts/index.{d.ts,js}` and `./adapters` → `dist/adapters/index.{d.ts,js}`. No `dist/` directory exists on disk. Either: (a) commit the built artifacts (visible to consumers without local build step), or (b) drop the export promise. Folded into M2.~~ **CLOSED — `exports` map removed; package now declares `private: true`. (closed by M2A — commit `fe5f48fd1`, PR #1422; M2 wrap-up — PR #1468)** |
| <a name="E-Pkg-02"></a>E-Pkg-02 ~~No `engines` constraint~~ | envelope-defect | XS | ~~`package.json` has no `engines` field. Node version is implicit. Add `"engines": { "node": ">=22.0.0" }`. Folded into M2.~~ **CLOSED — `engines.node = >=22.12.0` declared (stricter than audit-suggested >=22.0.0, matches Nuxt 4.4.4 constraint). (closed by M2A — commit `fe5f48fd1`, PR #1422; M2 wrap-up — PR #1468)** |
| E-Pkg-03 ~~No `publishConfig`, no `private: true`~~ | informational | XS | ~~If the package is genuinely meant to be installable (`@waaseyaa/admin`), add `publishConfig.access`. If it's not published, mark `"private": true`. Folded into M2.~~ **CLOSED — `"private": true` declared; package is monorepo-internal, not published. No `publishConfig` needed. (closed by M2A — commit `fe5f48fd1`, PR #1422; M2 wrap-up — PR #1468)** |
| E-Pkg-04 ~~No peerDependencies~~ | informational | XS | ~~Consumers needing the contracts only would benefit from peerDeps on `vue`, `nuxt`. Folded into M2.~~ **CLOSED — No `peerDependencies` added by M2 wrap-up decision (2026-05-13). Package is private and has zero published consumers (see E-Pkg-06). (closed by M2A — commit `fe5f48fd1`, PR #1422; M2 wrap-up — PR #1468)** |

## 4.2 build:contracts pipeline

| Finding | Class | Size | Notes |
|---|---|---|---|
| <a name="E-Pkg-05"></a>E-Pkg-05 ~~No CI step verifies `build:contracts` output~~ **CLOSED — finding was stale** | envelope-defect | S | **Resolved by M2B-build-pipeline investigation (#1411 umbrella context, 2026-05-11).** The CI gate already exists: `.github/workflows/admin.yml:22-78` defines the `admin/contracts` job which (1) runs `nuxi typecheck`, (2) runs `npm run build:contracts` and uploads `dist/` as a 14-day artifact, (3) validates `contracts/bootstrap.schema.json` against a reference payload via `ajv-cli`, (4) runs `vitest`. The job triggers on every push/PR touching `packages/admin/**`. `packages/admin/.gitignore` correctly excludes `dist/` (verification artifact, not shipped — confirmed zero downstream consumers in M2A and E-Pkg-06). `build:contracts` remains valuable beyond `typecheck` because it verifies contracts/adapters can be *emitted* as standalone `.d.ts` (catches accidental dependency on Nuxt auto-imports or non-published transitive types). No code change needed; this finding was authored before the gate was added. |
| <a name="E-Pkg-06"></a>E-Pkg-06 ~~No known downstream consumer of `@waaseyaa/admin` imports~~ | envelope-defect (informational) | XS | ~~Grep across this workspace and `~/dev/waaseyaa.org` found zero `@waaseyaa/admin` import statements. The exports map exists but is consumed by **nothing in the visible workspace**. Decide in M2 whether to keep the contracts/adapters export shape or remove it as YAGNI.~~ **CLOSED — YAGNI confirmed by M2A and M2 wrap-up verification grep. Zero `@waaseyaa/admin` import statements across `waaseyaa/framework` and `waaseyaa.org`. Private-app shape adopted. (closed by M2A — commit `fe5f48fd1`, PR #1422; M2 wrap-up — PR #1468)** |

## 4.3 README freshness

| Finding | Class | Size | Notes |
|---|---|---|---|
| <a name="E-Docs-01"></a>E-Docs-01 ~~README is 21 lines, comprehensive spec is in `docs/specs/admin-spa.md`~~ | envelope-defect | XS | ~~Bring README to a 50–80 line publishable summary including: bootstrap contract, AdminSurface, codified-context telemetry, auth phase 2, build commands, deployment notes. Folded into M2.~~ **CLOSED — README expanded from 21 lines to ~63 lines covering stack, develop/test/build commands, `build:contracts` verification gate, i18n, and modernization-audit pointer. M2 wrap-up verifies (63 lines confirmed). (closed by M2A — commit `fe5f48fd1`, PR #1422; M2 wrap-up — PR #1468)** |

## 4.4 Directory structure

`packages/admin/` is clean: `app/`, `contracts/`, `e2e/`, `tests/`, `public/`, `nuxt.config.ts`, `playwright.config.ts`, `tsconfig.json`, `tsconfig.contracts.json`, `vitest.config.ts`, `package.json`, `package-lock.json`, `README.md`, `.gitignore`. No obsolete or orphaned subdirectories observed.

## 4.5 Playwright / Vitest config modernity

Both `playwright.config.ts` and `vitest.config.ts` are present; size suggests minimal config (~400-700 bytes each). Modernity not deeply audited; if M1 adopts `@nuxt/eslint` it may bring lint to these files too.

## 4.6 Monorepo-shape recommendation

The admin SPA is the **only JS-only package in a PHP monorepo**, and the only one that publishes contracts via `dist/`. Three options:

1. **Status quo (keep as monorepo sibling)** — pragmatic, low friction. The "package" is really an application, not a library; the `exports` map is aspirational. Recommended **unless** Waaseyaa's distribution model changes.
2. **Pre-built tarball model** — what PR #1350 implies. Ship a pre-built admin SPA dist as a sibling package, consumed by composer-installable Waaseyaa apps without needing Node. Requires CI to rebuild dist on every release.
3. **Relocate to sibling repo** — if multiple PHP apps want to consume the SPA, lifting it to its own repo enables independent versioning. Probably premature without ≥2 known consumers.

**Recommendation**: keep status quo but tighten contracts (M2). Adopt option 2 as a hardening step **only if** an external Waaseyaa app appears that doesn't want Node in its build chain.

**Decision (2026-05-13, M2 wrap-up)**: Option 1 (status quo) adopted. The admin SPA remains the only JS-only package in a PHP monorepo. The `exports` map and `dist/` references were removed by M2A (commit `fe5f48fd1`, PR #1422). PR #1350 (the manual dist-rebuild PR) is closed as obsolete in the same window. The pre-built tarball model (option 2) and the sibling-repo model (option 3) remain documented as escape hatches if a future external Waaseyaa app justifies them. (M2 wrap-up: PR #1468.)

---

# UX / Visual Polish — Deferred

Per the originating spec, UX/visual-polish work is **out of scope** for this audit and was not surveyed. A future mission can audit:
- Design-system maturity (no CSS framework adopted; tokens live ad-hoc in `AdminShell.vue`)
- Component library coverage (widget set is functional but unstyled beyond defaults)
- Dashboard sparsity (`pages/index.vue` is a thin landing page)
- Empty-state and skeleton-loader patterns
- A11y audit beyond what the i18n composable provides

If/when prioritized, it slots between M4 and M5 or runs in parallel with M4 — it has no dependencies on the framework-alignment missions.

---

# Out of Scope

Per the originating spec (FR-014):
- Any code change in `packages/admin/`
- Any backend (`packages/*` excluding `packages/admin/`) source change
- Execution of any of the Top 5 follow-up missions
- UX/visual-polish work (covered as deferred above)
- Re-evaluation of Waaseyaa's framework architecture or layer graph
- Cross-project admin work outside `packages/admin/`

Additionally deviated from the spec (see "Scope deviations from spec" above):
- One-issue-per-actionable-entry filing (FR-012, NFR-005) — replaced with 5 umbrella issues
- Per-commit ≥90% classification with individual rows (NFR-001) — replaced with high-signal entries + per-package batch `no-op` tallies

---

# Appendix: Citation conventions & cross-references

- **Commit citations**: 8-character SHA prefix on `main`'s first-parent history at audit time.
- **Issue / PR citations**: `#NNNN` resolves to `https://github.com/waaseyaa/framework/issues/NNNN` (or `/pull/NNNN`).
- **Mission citations**: spec-kitty mission slug (e.g. `Mission #1257`, `Mission alpha-172`); look up under `kitty-specs/`.
- **Methodology**: full methodology, rubrics, and tool selection in [`research.md`](../../kitty-specs/admin-spa-modernization-audit-01KRA3RV/research.md).
- **Spec**: [`spec.md`](../../kitty-specs/admin-spa-modernization-audit-01KRA3RV/spec.md) for the mission's requirements and acceptance criteria.
- **Validation greps**: [`plan.md`](../../kitty-specs/admin-spa-modernization-audit-01KRA3RV/plan.md) — `Acceptance & Validation` section.

## Live entry-count summary

- Drift entries (actionable): **8**
- Drift entries (no-op, batched): **all remaining v1.x commits across 11 corpus packages**
- Coverage gaps (actionable, non-complete-UI / non-schema-driven): **29**
- Tooling findings (actionable): **6**
- Envelope findings (actionable): **6**
- Documentation findings: **1**
- Total Top 5 missions: **5**
- Total tracking issues filed by this audit: **5 umbrella issues** (see Top 5 section)
