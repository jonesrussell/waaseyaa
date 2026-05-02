# Tasks / work packages — architectural-remediation

WP01 was the decomposition phase (output: `decomposition.md`). WP02-WP09 each touch exactly one contract surface. The M4-M7 track umbrellas (#875-#893) are absorbed structurally: their findings live in the surface-aligned WPs, not as separate work.

## Work package roster

| WP | Slug | Surface | Member issues | Status |
|----|------|---------|---------------|--------|
| WP01 | decomposition | n/a | n/a (this work) | done |
| WP02 | layer-graph-and-manifest-authority | S1 | #824 #825 #827 #828 #829 #830 #831 #847 (8) | done (surfaces A–F: 51d5a1503, afc058de2, a07d80f4f, 860076faf, ba380b469, f41b54d64) |
| WP03 | service-provider-extension-contract | S2 | #833 #838 #843 (3) | unscheduled |
| WP04 | entity-type-manager-public-surface | S3 | #835 #836 #837 (3) | unscheduled |
| WP05 | access-checker-placement-and-paired-nullable | S4 | #832 #834 #844 (3) | unscheduled |
| WP06 | jsonapi-route-and-discovery-surface | S5 | #841 (1) | unscheduled |
| WP07 | admin-surface-integration-contract | S6 | #839 #840 #842 #846 #851 (5) | unscheduled |
| WP08 | manifest-authority-and-command-exposure | S7 | #854 #858 (2) | unscheduled |
| WP09 | workflow-governance-and-verify-entry-point | S8 | #845 #848 #849 #850 #852 #855 #856 #857 (8) | unscheduled |

## Sequencing

- `WP02 -> WP03 -> WP04 -> WP05 -> WP06` strict order.
- `WP07` depends on `WP02 + WP04`.
- `WP08` depends on `WP02`.
- `WP09` depends on every prior WP (republishes specs touched by all upstream surfaces).

## Per-WP scope and acceptance evidence

### WP02 — layer-graph-and-manifest-authority (S1)

**Scope.** Make `bin/check-package-layers` enforce the canonical layer table. Reclassify admin or move it into the Composer graph. Scope the kernel exemption to named files. Decompose `EventListenerRegistrar` or grant explicit exemption. Replace `kernelResolver` with the typed `KernelServicesInterface` (Contract A).

**Public contracts touched.** Layer table in `CLAUDE.md` and `extension-compatibility-matrix.md`; `ProviderRegistry` (loses kernelResolver fallback); `KernelServicesInterface` (new).

**Acceptance evidence.** `bin/check-package-layers` passes against live graph; `KernelServicesInterface` ratified in `infrastructure.md`; no `kernelResolver` `Closure` references in `packages/foundation/src/`; `extension-compatibility-matrix.md` matches actual `composer.json` graph.

### WP03 — service-provider-extension-contract (S2)

**Scope.** Complete `ServiceProviderInterface` to enumerate every hook the kernel actually calls. Re-type all hooks to interfaces. Add a contract test that keeps interface, abstract base, and kernel call sites in lockstep.

**Public contracts touched.** `ServiceProviderInterface`; abstract `ServiceProvider`; the hook contract documented in `infrastructure.md` and `plugin-extension-points.md`.

**Acceptance evidence.** Contract test in `packages/foundation/tests/Contract/` exists and passes; all hooks accept interface types in PHPDoc and at runtime; `infrastructure.md` enumerates the full hook list.

### WP04 — entity-type-manager-public-surface (S3)

**Scope.** Document `registerEntityType()` and `registerCoreEntityType()` with reserved-namespace contract in `entity-system.md`. Sync `RevisionableStorageInterface` doc to PHP source. Switch admin-surface host extension to interface-only typing.

**Public contracts touched.** `EntityTypeManagerInterface` (mutation surface); `RevisionableStorageInterface`; admin-surface host extension typing.

**Acceptance evidence.** `entity-system.md` lists the full mutation surface; `RevisionableStorageInterface` in spec matches source one-to-one (verified by a doc-test or contract test); `grep -rn 'EntityTypeManager[^I]' packages/admin*` shows no concrete bindings.

### WP05 — access-checker-placement-and-paired-nullable (S4)

**Scope.** Document `AccessChecker` canonically in `access-control.md` and `api-layer.md` with `Waaseyaa\Access` as the home. Make `ResourceSerializer::serialize()` and `SchemaPresenter::present()` throw on partial access context. Add negative-path tests.

**Public contracts touched.** `AccessChecker` canonical placement; the paired-nullable precondition on `ResourceSerializer::serialize()` and `SchemaPresenter::present()`.

**Acceptance evidence.** Both methods throw a typed exception when context is partial; tests exist for the throw path; `access-control.md` and `api-layer.md` agree on placement.

### WP06 — jsonapi-route-and-discovery-surface (S5)

**Scope.** Add `api.discovery` route and `ApiDiscoveryController` response contract to `api-layer.md`. Add an integration test that exercises the route end-to-end.

**Public contracts touched.** `JsonApiRouteProvider` route table; `ApiDiscoveryController` response contract.

**Acceptance evidence.** `api-layer.md` enumerates the full route table; integration test passes.

### WP07 — admin-surface-integration-contract (S6)

**Scope.** Move `email_verification` and catalog `description` into `packages/admin-surface/contract/types.ts`. Deauthor `admin-spa.md` in favor of the contract package. Add a root-level integration test that conforms backend response to the TypeScript contract via fixture.

**Public contracts touched.** `packages/admin-surface/contract/types.ts`; admin-surface session and catalog payload shape; `admin-spa.md` authority boundary.

**Acceptance evidence.** TypeScript contract is the single source; root-level integration test exists and passes; `admin-spa.md` no longer redefines payload shape.

**Depends on:** WP02 (admin host wiring stable) + WP04 (interface-only typing).

### WP08 — manifest-authority-and-command-exposure (S7)

**Scope.** Add `extra.waaseyaa` blocks to `waaseyaa/cli`, `waaseyaa/api`, `waaseyaa/graphql`, `waaseyaa/mcp`, `waaseyaa/telescope`, and root `composer.json`. Remove the hard-coded command list in `ConsoleKernel`. Surface previously-missing CLI commands through the registered console surface.

**Public contracts touched.** `extra.waaseyaa.{providers,commands,routes}` declarations; `ConsoleKernel` registration path; `PackageManifestCompiler` as sole truth.

**Acceptance evidence.** No string-literal command list in `ConsoleKernel`; all framework packages have `extra.waaseyaa`; `package-discovery.md` documents the contract.

**Depends on:** WP02 (manifest as authoritative path).

### WP09 — workflow-governance-and-verify-entry-point (S8)

**Scope.** Decompose `composer dev` into typed entries. Add `composer verify` (Contract B) as the one canonical verification command. Add the README skeleton to every active package. Refresh `per-site-convergence-audit.md`, `CLAUDE.md`, `workflow.md`, and codified-context tables to match actual topology. Replace foundation reflection-on-private-state tests with public-seam tests.

**Public contracts touched.** `composer verify` (new); `composer dev` process model; package README skeleton; canonical architecture authority across all spec docs touched by the mission.

**Acceptance evidence.** `composer verify` exists, CI uses it, all individual checks routed through it; every active package's README matches the skeleton; `workflow.md` milestone table reflects Spec Kitty roster; foundation tests exercise public seams (no `ReflectionProperty` on private state).

**Depends on:** every prior WP.

---

## Review gate

Each WP runs through Spec Kitty `implement` -> `review`. WP09 cannot enter `implement` until WP02-WP08 are merged.
