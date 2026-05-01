# `waaseyaa/admin-surface` — Contract Package

This directory is the **single authoritative source** for the host-to-SPA payload boundary of the Waaseyaa admin surface.

If three documents claim to define the admin payload shape, exactly one of them is right. This package is that one.

## Authority

| Artifact | Authority |
|----------|-----------|
| **`packages/admin-surface/contract/types.ts`** | **Authoritative.** Defines every type that crosses the host-to-SPA boundary: `AdminSurfaceSession`, `AdminSurfaceAccount`, `AdminSurfaceTenant`, `AdminSurfaceCatalog`, `AdminSurfaceCatalogEntry`, `AdminSurfaceField`, `AdminSurfaceAction`, `AdminSurfaceCapabilities`, `AdminSurfaceEntity`, `AdminSurfaceResult`, `AdminSurfaceError`, `AdminSurfaceListQuery`, `AdminSurfaceListResult`, plus optional UI customization (`AdminSurfaceUiCustomization`, `AdminSurfaceHeaderLink`, `AdminSurfaceSidebarItem`). |
| `packages/admin-surface/src/Host/*.php` | Implementation. Backend emitters (`AdminSurfaceSessionData::toArray()`, `CatalogBuilder::build()`) must conform to the TypeScript contract above. |
| `packages/admin/app/contracts/*.ts` | SPA-local mirror. The admin SPA builds its own contracts under `app/contracts/` for `tsc --rootDir app` to produce clean declarations; these are mirrors of the canonical types, not authoritative redefinitions. They must stay structurally compatible. |
| `docs/specs/admin-spa.md` | Subsystem spec. Describes the SPA runtime, components, routes, and behaviour. **Does not define payload shape.** It references type names from this package and assumes their definitions. |

## Conformance

Two cross-boundary tests guard the contract:

- `tests/Integration/AdminSurface/AdminSurfaceContractConformanceTest.php` — parses the TypeScript interfaces in this package and asserts that backend-emitted PHP payloads conform structurally (no missing required fields, no unknown fields).
- `tests/Integration/AdminSurface/AdminSurfaceRouteWiringIntegrationTest.php` — exercises the production composition of `AdminSurfaceServiceProvider` + `WaaseyaaRouter` + `GenericAdminSurfaceHost` and asserts the published route names and paths match `AdminSurfaceRoutePaths`.

Drift on either side breaks both tests. That is the point.

## Adding a contract field

1. Edit `types.ts` with TSDoc explaining provenance (which PHP class emits the field, which SPA call site reads it) and optional/required semantics.
2. Update the matching PHP emitter (typically `AdminSurfaceSessionData` or a `CatalogBuilder` definition).
3. Add a regression assertion to the relevant package-local test (e.g. `CatalogBuilderTest`).
4. The cross-boundary integration tests will pick up the new field automatically.
5. If `docs/specs/admin-spa.md` references the field, update it to use the same name and casing.

## Naming convention

Payload keys are **camelCase** in both the TypeScript contract and the PHP emit (`emailVerified`, `requireVerifiedEmail`, `description`). Do not introduce snake_case variants. The audit (#851) flagged exactly this kind of split — a third vocabulary in the spec contradicting the contract — and the fix is to keep this package as the single source and align everything else to it.
