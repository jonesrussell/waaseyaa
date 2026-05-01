# Decomposition — architectural-remediation

Date: 2026-04-30 (Pass 2 WP01 output)

Mission absorbs 52 closed issues spanning M1 audit findings (#824-#858, excluding #826/#853/#859 which are pass/umbrella refs) and M4-M7 remediation tracks (#875-#893). Modern-stance reframe: the M4-M7 phased gates are flattened; we work along contract surfaces, not milestones.

## Decision: NO-SPLIT

The 52 issues form one strongly-coupled remediation graph, not two independent clusters. The dependency chain is:

> ServiceProvider extension hooks (#833, #838) → EntityTypeManagerInterface registration surface (#835, #836) → AccessChecker placement and route option semantics (#832, #834, #841) → Admin-surface session/catalog/email-verification contract (#839, #840, #851) → Verification harness that locks all of those (#842-#847) → Spec authority and codified-context refresh that publishes the corrected contracts (#848-#852) → CLI/DX surface that exercises them through the public path (#854, #856, #858, #885, #893).

Every "later" surface depends on a "lower" surface having been redefined. The admin-surface contract (interfaces layer) cannot be rewritten until the EntityTypeManager interface boundary (core-data layer) and ServiceProvider hooks (foundation layer) are settled, because the admin host currently rebuilds access wiring (#831) and binds to the concrete manager (#836). The verification harness (#842-#847) cannot lock contracts that have not been authored. The docs-governance refresh (#848-#852) cannot republish authoritative specs while the underlying contracts are still moving. Splitting into two missions would force one mission to merge before the other starts on shared contract files (`docs/specs/access-control.md`, `docs/specs/admin-spa.md`, `docs/specs/entity-system.md`) and produce certain merge conflicts and re-derivations. One mission, surface-ordered work packages.

## Contract surfaces

Eight surfaces grouped from the 52 issues. WPs are aligned 1:1 with surfaces below.

### S1 — Layer graph and package topology authority

- **Current state.** `bin/check-package-layers` enforces a layer table that the actual Composer package graph contradicts: `admin` is documented as L6 but lives outside the Composer graph as a Nuxt app (#824); the package count in the layer table is stale (#825, #848); foundation kernel bootstrappers and `EventListenerRegistrar` import upward outside the documented exemption (#827, #830); user package reaches into SSR (#829); admin-surface provider rebuilds AccessPolicyRegistry from manifest storage (#831); ProviderRegistry exposes a kernel-resolver fallback that enables synchronous cross-provider coupling (#828); and Foundation harvests upward types in tests (#847).
- **Target state.** The layer table in `CLAUDE.md` and `docs/specs/extension-compatibility-matrix.md` is the mechanically verifiable source of truth. `admin` is either reclassified as a non-package interface app (with the table updated) or moved into the Composer graph. The kernel exemption is precisely scoped (named files only) and `EventListenerRegistrar` either gets a new exemption entry or is decomposed. ProviderRegistry has no kernel-resolver fallback; cross-provider coupling routes through a typed, auditable kernel-services contract.
- **Breaking change scope.** Any package importing the `EntityTypeManager` concrete class transitively from foundation breaks; any provider relying on `kernelResolver` fallback breaks; any consumer of the loose layer table needs to refresh assumptions.
- **Member issues.** #824 #825 #827 #828 #829 #830 #831 #847 (and #848 reflects this in docs).
- **Spec docs to update.** `docs/specs/extension-compatibility-matrix.md`, `docs/specs/package-discovery.md`, `docs/specs/infrastructure.md`.

### S2 — ServiceProvider extension contract

- **Current state.** `ServiceProviderInterface` omits hooks the kernel actually invokes (#838); `ServiceProvider::routes()/commands()/graphqlMutationOverrides()/middleware()` type-hint the concrete `EntityTypeManager` instead of `EntityTypeManagerInterface` (#833); foundation tests do not verify hook coherence across interface, base class, and kernel call sites (#843).
- **Target state.** A complete `ServiceProviderInterface` declares every hook the kernel calls; all hooks accept interface types, never concretes; a contract test in `packages/foundation/tests/Contract/` keeps interface, abstract base, and kernel dispatcher in lockstep.
- **Breaking change scope.** Every `ServiceProvider` subclass that overrides a hook must update its parameter type-hints; downstream packages binding to the concrete `EntityTypeManager` from inside hooks must switch to the interface.
- **Member issues.** #833 #838 #843.
- **Spec docs to update.** `docs/specs/infrastructure.md` (provider extension points), `docs/specs/plugin-extension-points.md`.

### S3 — EntityTypeManagerInterface public surface

- **Current state.** The published spec for `EntityTypeManagerInterface` documents only read/query methods; the actual interface exposes `registerEntityType()` and `registerCoreEntityType()` with reserved-namespace semantics (#835). `RevisionableStorageInterface` signatures published in the spec are out of date (#837). Admin-surface host extension binds to the concrete manager (#836).
- **Target state.** `docs/specs/entity-system.md` enumerates the full public mutation surface with reserved-namespace contract; `RevisionableStorageInterface` doc matches the PHP source one-to-one; the admin-surface host extension surface accepts only `EntityTypeManagerInterface`.
- **Breaking change scope.** Admin host integrators breaking from concrete-class bindings; any third-party storage adapter against the old `RevisionableStorageInterface` shape.
- **Member issues.** #835 #836 #837.
- **Spec docs to update.** `docs/specs/entity-system.md`, `docs/specs/admin-spa.md` (the host extension surface section).

### S4 — Access-control placement and paired-nullable invariant

- **Current state.** `access-control.md` and `api-layer.md` place `AccessChecker` in routing while the public class lives in `Waaseyaa\Access` (#832); the paired-nullable `(?EntityAccessHandler, ?AccountInterface)` precondition on `ResourceSerializer::serialize()` and `SchemaPresenter::present()` is documented but unenforced at runtime or in tests (#834, #844).
- **Target state.** Single canonical home for `AccessChecker` documented in both specs with the namespace and routing-time consumption contract; `ResourceSerializer` and `SchemaPresenter` enforce the paired-nullable precondition explicitly (throw or narrow the type) and have negative-path tests that lock the invariant.
- **Breaking change scope.** Callers passing partial access context will receive a typed exception instead of silently degraded output; any code referencing the misplaced `AccessChecker` namespace will need to update import.
- **Member issues.** #832 #834 #844.
- **Spec docs to update.** `docs/specs/access-control.md`, `docs/specs/api-layer.md`, `docs/specs/jsonapi.md`.

### S5 — JsonApi route and discovery surface

- **Current state.** `JsonApiRouteProvider` contract spec omits the public `api.discovery` route and the `ApiDiscoveryController` surface that already ships in code (#841).
- **Target state.** `docs/specs/api-layer.md` enumerates the full route table including `api.discovery`, with the `ApiDiscoveryController` response contract documented and exercised by an integration test.
- **Breaking change scope.** None at runtime (the route already ships); spec consumers gain a previously-undocumented surface they can now rely on.
- **Member issues.** #841.
- **Spec docs to update.** `docs/specs/api-layer.md`.

### S6 — Admin-surface integration contract (session, catalog, email verification)

- **Current state.** The admin-surface session contract omits `email_verification` state used by both backend and SPA runtime (#839); the catalog contract omits the `description` field emitted by backend and consumed by SPA (#840); admin-surface authority is split between `packages/admin-surface/contract/` and a contradictory subsystem spec (#851); admin host wiring rebuilds access wiring out of manifest storage (#831, S1 overlap); admin-surface route and host wiring has package-local unit tests but no root integration coverage (#842, #846).
- **Target state.** `packages/admin-surface/contract/types.ts` is the single authoritative source for the admin-surface payload shape; `docs/specs/admin-spa.md` references it rather than redefining it; `email_verification` and catalog `description` are part of the contract; a root-level integration test asserts the live PHP backend response shape conforms to the TypeScript contract via a fixture.
- **Breaking change scope.** SPA consumers reading `email_verification` from a non-spec location break; subsystem-spec readers redirect to the contract package.
- **Member issues.** #831 (overlap with S1) #839 #840 #842 #846 #851.
- **Spec docs to update.** `docs/specs/admin-spa.md` (deauthor, link out to contract package); `packages/admin-surface/contract/README.md` (single-source declaration).

### S7 — Manifest authority and command/route exposure

- **Current state.** `extra.waaseyaa` Composer metadata is documented as the registration path for providers, commands, and routes, but `waaseyaa/cli`, `waaseyaa/api`, `waaseyaa/graphql`, `waaseyaa/mcp`, `waaseyaa/telescope` carry no metadata and are wired through hard-coded lists in `ConsoleKernel` (#854); implemented CLI commands are missing from the registered console surface (#858); the root package has no `extra.waaseyaa` block (#854).
- **Target state.** Every active framework package declares its providers, commands, and routes in `extra.waaseyaa`; `PackageManifestCompiler` is the only registration path; `ConsoleKernel` no longer hard-codes a command list; root `composer.json` has its own `extra.waaseyaa` for repo-level surfaces.
- **Breaking change scope.** Any external code reading `ConsoleKernel`'s hard-coded list breaks; the manifest cache fingerprint forces a one-time recompile.
- **Member issues.** #854 #858 (and #893 is the M7 track that already maps here).
- **Spec docs to update.** `docs/specs/package-discovery.md`, `docs/specs/operator-diagnostics.md`.

### S8 — Workflow governance and root verification surface

- **Current state.** `composer dev` couples the PHP server and admin SPA via a brittle shell one-liner (#855); the root workflow surface lacks a unified verification entry point (no `composer verify` running CS+phpstan+tests+layer checks together) (#856); package-local READMEs are uneven across active packages (#857); canonical architecture documents publish stale topology and ownership (#848, #850); workflow milestone table no longer reflects the GitHub roadmap (#852); subsystem specs continue to present stale contracts as authoritative (#849); admin-surface authority is split (#851, overlap with S6); foundation unit suites use reflection on private state instead of public seams (#845).
- **Target state.** `composer dev` runs each long-lived process in its own typed entry; `composer verify` is the one canonical command for repo-wide verification (covering CS, phpstan, layers, composer policy, no-secrets, ingestion-defaults, manifest, contract tests); every active package has a README with the same skeleton (purpose, layer, contracts exposed, contracts consumed); `docs/specs/per-site-convergence-audit.md` and `CLAUDE.md` reflect actual topology; the workflow milestone table is generated or hand-synced from Spec Kitty mission roster; foundation unit tests exercise public seams only.
- **Breaking change scope.** Contributors running `composer dev` see a different process model; CI replaces ad-hoc steps with `composer verify`; the milestone table loses any prose-only entries.
- **Member issues.** #845 #848 #849 #850 #852 #855 #856 #857.
- **Spec docs to update.** `docs/specs/operator-diagnostics.md`, `docs/specs/operations-playbooks.md`, `docs/specs/codified-context-integration.md`, `docs/specs/workflow.md`, plus a fresh pass over every spec touched by the surfaces above.

## Coupling matrix

|       | S1 | S2 | S3 | S4 | S5 | S6 | S7 | S8 |
|-------|----|----|----|----|----|----|----|----|
| S1    | —  | →  | →  | →  | →  | →  | →  | →  |
| S2    |    | —  | →  |    |    | →  |    | →  |
| S3    |    |    | —  | →  |    | →  |    | →  |
| S4    |    |    |    | —  | →  |    |    | →  |
| S5    |    |    |    |    | —  |    | →  | →  |
| S6    |    |    |    |    |    | —  |    | →  |
| S7    |    |    |    |    |    |    | —  | →  |
| S8    |    |    |    |    |    |    |    | —  |

`row → column` means "row must land before column can be finalized." S8 is the consolidation surface that closes the mission once all upstream surfaces are stable.

There is no zero-coupling cluster. S1 (layer/manifest authority) gates everything; S8 (governance refresh) absorbs the consequences of every upstream change.

## Work package roster

WP01 was this decomposition. WP02-WP09 each touch one surface. Order is bottom-up by layer.

| Slug | Title | Surface | Member issues |
|------|-------|---------|--------------|
| WP02 | layer-graph-and-manifest-authority | S1 | #824 #825 #827 #828 #829 #830 #831 #847 |
| WP03 | service-provider-extension-contract | S2 | #833 #838 #843 |
| WP04 | entity-type-manager-public-surface | S3 | #835 #836 #837 |
| WP05 | access-checker-placement-and-paired-nullable | S4 | #832 #834 #844 |
| WP06 | jsonapi-route-and-discovery-surface | S5 | #841 |
| WP07 | admin-surface-integration-contract | S6 | #839 #840 #842 #846 #851 |
| WP08 | manifest-authority-and-command-exposure | S7 | #854 #858 |
| WP09 | workflow-governance-and-verify-entry-point | S8 | #845 #848 #849 #850 #852 #855 #856 #857 |

Sequencing: WP02 → WP03 → WP04 → WP05 → WP06 in strict order; WP07 depends on WP02+WP04 (admin host needs interface and access wiring stable); WP08 depends on WP02 (manifest authority); WP09 depends on every prior WP because it republishes the specs.

The 52 issues map cleanly. The remediation-track umbrella issues (#875-#893) are absorbed structurally — they are M4-M7 phased gates whose finding clusters now live in the surface-aligned WPs, and they should be referenced in `tasks.md` as "absorbed by WPxx" rather than reproduced as work.

### Cleanup follow-ups (no WP required)

None of the 52 issues are pure noise; every issue maps to a contract surface. The M4-M7 track umbrellas (#875, #876, #877, #878, #879, #880, #881, #882, #883, #884, #885, #886, #887, #888, #889, #890, #891, #892, #893) are not WPs in their own right — they are remediation-planning artifacts whose findings are already absorbed.

### Potential new contracts

Two contracts are not in `CLAUDE.md` today and are surfaced for explicit decision in the spec:

1. **`KernelServicesInterface`** — a typed kernel-services bus that replaces the current `kernelResolver` `\Closure(string): ?object` fallback in `ServiceProvider`. Surface S1 needs this to remove the synchronous cross-provider coupling that #828 calls out.
2. **`composer verify` workflow contract** — a single declarative entry point in root `composer.json` (and a parallel `bin/verify` script) that runs CS+phpstan+layer checks+composer policy+contract tests in one shot. S8 needs this; it does not exist today.

Both should be ratified in the spec before WP02/WP09 begin.

## Spec doc impact

| Spec doc | Surfaces touching it | Nature of change |
|----------|----------------------|-------------------|
| `extension-compatibility-matrix.md` | S1 | Refresh layer table; reclassify admin |
| `package-discovery.md` | S1, S7 | Mark `extra.waaseyaa` as the only registration path |
| `infrastructure.md` | S1, S2 | Document complete `ServiceProviderInterface`; remove kernel-resolver fallback |
| `plugin-extension-points.md` | S2 | Hook list + interface-only type contract |
| `entity-system.md` | S3 | Enumerate registerEntityType / registerCoreEntityType / RevisionableStorageInterface |
| `admin-spa.md` | S3, S6, S8 | Deauthor in favor of contract package; drop split-authority sections |
| `access-control.md` | S4 | Place `AccessChecker` in `Waaseyaa\Access`; document paired-nullable invariant |
| `api-layer.md` | S4, S5 | AccessChecker location; full route table including `api.discovery` |
| `jsonapi.md` | S4 | Paired-nullable enforcement at serialize/present boundaries |
| `operator-diagnostics.md` | S7, S8 | `composer verify` entry; manifest as authoritative path |
| `operations-playbooks.md` | S8 | Process model for `composer dev` and `composer verify` |
| `codified-context-integration.md` | S8 | Authoritative ownership for full active package surface |
| `workflow.md` | S8 | Milestone table sync with Spec Kitty roster |
| `per-site-convergence-audit.md` | S8 | Refresh against actual topology |

`docs/specs/admin-spa.md` is the most rewritten spec; `docs/specs/extension-compatibility-matrix.md` is the most load-bearing for mechanical enforcement.

## Acceptance for the mission as a whole

The mission is done when:

1. `bin/check-package-layers` passes against the live Composer graph, with no implicit exemptions and no `kernelResolver` fallback in `ServiceProvider`.
2. `ServiceProviderInterface` enumerates every hook the kernel calls; all hooks type interfaces; a contract test in `packages/foundation/tests/Contract/` keeps interface/base/kernel in lockstep.
3. `EntityTypeManagerInterface` registration surface is fully documented and the admin host extension surface accepts only the interface.
4. `ResourceSerializer::serialize()` and `SchemaPresenter::present()` throw on partial access context; negative-path tests exist.
5. `JsonApiRouteProvider` documents `api.discovery`; an integration test exercises the route.
6. `packages/admin-surface/contract/` is the single authoritative source for the admin-surface payload shape; `email_verification` and catalog `description` are in the contract and exercised by a root-level integration fixture.
7. Every active framework package declares providers, commands, and routes via `extra.waaseyaa`; `ConsoleKernel` carries no hard-coded command list.
8. `composer verify` is the canonical repo-wide verification command; CI uses it; every active package has a README skeleton; `docs/specs/workflow.md` and the codified-context table reflect actual topology.

When all eight conditions hold and every member issue is referenced from at least one merged WP, the mission accepts.
