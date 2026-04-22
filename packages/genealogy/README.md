# waaseyaa/genealogy

Genealogy domain entities (**`genealogy_tree`** tenancy root, **person**, **family**, **event**), graph edges via `waaseyaa/relationship`, pedigree services, and Twig SSR routes for Waaseyaa applications.

Runtime **`waaseyaa/field`** registers core `FieldDefinition`s; **`waaseyaa/workflows`** supplies **`WorkflowVisibility`** for published checks alongside tree ownership and living-person rules. See **`CHANGELOG.md`** for breaking defaults (private-by-default, `status` off, SSR opt-in field).

## Transport: JSON:API is optional

This package is **data, access policy, and SSR** — it does **not** declare a runtime dependency on **`waaseyaa/api`**. Host applications (for example Minoo) wire **JSON:API** if they expose REST collections for genealogy types.

Consumers that are **GraphQL-only**, **SSR-only**, or otherwise API-less can depend on **`waaseyaa/genealogy`** without pulling the JSON:API stack.

### Future: `waaseyaa/genealogy-api`

If package-specific HTTP surfaces are needed (for example access-aware pedigree pagination that does not map cleanly onto generic JSON:API), introduce a dedicated **`waaseyaa/genealogy-api`** package that depends on both **`waaseyaa/genealogy`** and **`waaseyaa/api`** (or the HTTP layer you choose).

## Invariant: no `Waaseyaa\Api\` coupling in package sources

Production PHP under `src/` must not import or reference **`Waaseyaa\Api\…`** classes (no `use`, `extends`, `instanceof`, `new`, or `::class` on API types). Integration that the API package **reads from config or attributes** when the host has installed `api` is allowed — **no hard PHP coupling** from this package.

**Verify locally:**

```bash
composer verify:no-api-coupling
```

Run this in CI for the genealogy package so regressions fail the build.

## Downstream access posture

Applications that need **stricter** visibility than this package’s defaults may register additional **`AccessPolicyInterface`** implementations (for example Forbidden-first overlays). That pattern is for **downstream** products; Minoo and this package prefer **source-level** policy defaults in `waaseyaa/genealogy` during active development.
