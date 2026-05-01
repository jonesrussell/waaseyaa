# Plan: architectural-remediation

Phased implementation. Phases follow the surface dependency graph, not the M4-M7 milestones (which were flattened during decomposition).

## Phase 0 — Decomposition (WP01, complete)

Output: `decomposition.md` lists 8 contract surfaces, dependency graph, and 2 new contracts to ratify (`KernelServicesInterface`, `composer verify`).

Decision: NO-SPLIT. Surfaces are tightly coupled.

## Phase 1 — Foundation (WP02)

Layer graph and manifest authority. Replace `kernelResolver` `Closure` fallback with typed `KernelServicesInterface`. Reclassify or relocate admin. Scope kernel exemption explicitly. Outcome: `bin/check-package-layers` passes against live graph.

## Phase 2 — Provider extension (WP03)

`ServiceProviderInterface` enumerates every kernel-invoked hook. Hooks accept interfaces only. Contract test locks interface, base class, and dispatcher together.

## Phase 3 — Entity-type manager surface (WP04)

`EntityTypeManagerInterface` mutation surface documented (`registerEntityType`, `registerCoreEntityType`, reserved-namespace contract). `RevisionableStorageInterface` synced to source. Admin-surface host extension switches to interface-only.

## Phase 4 — Access control (WP05)

`AccessChecker` canonical placement. `ResourceSerializer` and `SchemaPresenter` enforce paired-nullable context (throw on partial).

## Phase 5 — JsonApi discovery (WP06)

`api.discovery` route documented and tested.

## Phase 6 — Parallel: admin-surface and manifest authority (WP07, WP08)

WP07 (depends on WP02+WP04) and WP08 (depends on WP02) can run in parallel after Phases 1-3 land.

WP07: contract package becomes single source for admin-surface payload shape. Root-level integration test conforms backend to contract.

WP08: every framework package declares `extra.waaseyaa`. `ConsoleKernel` loses hard-coded command list. Manifest is sole registration path.

## Phase 7 — Governance and verify entry point (WP09)

`composer verify` (Contract B) is the one canonical verification command. CI uses it. README skeleton applied. All spec docs touched by upstream WPs republished. Foundation tests exercise public seams only. Workflow milestone table sync.

## Mission close

When all 8 acceptance criteria in `spec.md` hold and every member issue (52) is referenced from a merged WP, the mission accepts.
