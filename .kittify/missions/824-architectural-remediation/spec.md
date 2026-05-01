# Mission spec: architectural-remediation

**Charter:** Lock the framework's public contract surfaces. Eight surfaces are entangled (provider hooks, EntityTypeManager registration, access-control placement, JsonApi routes, admin-surface integration, manifest authority, workflow governance) and a layer-graph foundation that gates them all. This mission resolves them in a single dependency-ordered pass under the modern stance: PHP 8.4+, no legacy, breaking changes welcome.

**Milestone:** Track 1 — Entity system & hydration

**Origin:** Pass 1 architect-mode triage (2026-04-30). Mission absorbs 52 closed issues spanning M1 audit findings (#824-#858) and M4-M7 remediation tracks (#875-#893). The M4-M7 phased gates are flattened: work proceeds along contract surfaces, not milestones.

**Decomposition artifact:** `decomposition.md` in this directory. WP01 produced it.

---

## Decision: NO-SPLIT

The 52 issues form one strongly-coupled remediation graph. Every higher-layer surface depends on a lower-layer surface being stable first. Splitting into two missions would force shared spec files (`access-control.md`, `admin-spa.md`, `entity-system.md`) to be edited concurrently, producing certain merge conflicts and re-derivations. See `decomposition.md` §"Coupling matrix" for the full dependency graph.

---

## Two ratified contracts (approved 2026-04-30)

### Contract A: `Waaseyaa\Foundation\Kernel\KernelServicesInterface`

A typed kernel-services bus that replaces the current `\Closure(string): ?object` `kernelResolver` fallback in `ServiceProvider`. Removes the synchronous cross-provider coupling that audit #828 calls out. Surface S1 needs this; without it the layer-graph cleanup leaves a typed hole.

### Contract B: `composer verify` workflow entry point

A single declarative root command (root `composer.json` `scripts.verify` plus `bin/verify`) that runs CS, PHPStan, layer checks, composer policy, no-secrets, ingestion-defaults, manifest, and contract tests as one canonical repo-wide verification. Surface S8 needs this; CI today runs ad-hoc step lists.

**Both contracts are ratified as part of this mission spec (2026-04-30).** WP02 implements Contract A; WP09 implements Contract B. ADR documents may follow if substantive variations from these sketches arise during implementation, but the sketches above are the binding agreement.

---

## Contract surfaces

Eight surfaces, each mapped 1:1 to a work package. Full per-surface analysis in `decomposition.md`.

| Surface | Subject | WP |
|---------|---------|----|
| S1 | Layer graph and package topology authority | WP02 |
| S2 | ServiceProvider extension contract | WP03 |
| S3 | EntityTypeManagerInterface public surface | WP04 |
| S4 | Access-control placement and paired-nullable invariant | WP05 |
| S5 | JsonApi route and discovery surface | WP06 |
| S6 | Admin-surface integration contract (session, catalog, email verification) | WP07 |
| S7 | Manifest authority and command/route exposure | WP08 |
| S8 | Workflow governance and root verification surface | WP09 |

S8 is the consolidation surface that closes the mission.

---

## Spec docs touched

Fourteen docs under `docs/specs/`. Most-rewritten: `admin-spa.md` (S3 + S6 + S8). Most load-bearing for mechanical enforcement: `extension-compatibility-matrix.md` (gates `bin/check-package-layers`).

Full list with surface mapping in `decomposition.md` §"Spec doc impact."

---

## Acceptance

The mission accepts when ALL of:

1. `bin/check-package-layers` passes against the live Composer graph, with no implicit exemptions and no `kernelResolver` fallback in `ServiceProvider`.
2. `ServiceProviderInterface` enumerates every hook the kernel calls; all hooks type interfaces; a contract test in `packages/foundation/tests/Contract/` keeps interface, base class, and kernel call sites in lockstep.
3. `EntityTypeManagerInterface` registration surface is fully documented and the admin-surface host extension accepts only the interface.
4. `ResourceSerializer::serialize()` and `SchemaPresenter::present()` throw on partial access context; negative-path tests exist.
5. `JsonApiRouteProvider` documents `api.discovery`; an integration test exercises the route.
6. `packages/admin-surface/contract/` is the single authoritative source for the admin-surface payload shape; `email_verification` and catalog `description` are in the contract and exercised by a root-level integration fixture.
7. Every active framework package declares providers, commands, and routes via `extra.waaseyaa`; `ConsoleKernel` carries no hard-coded command list.
8. `composer verify` is the canonical repo-wide verification command; CI uses it; every active package has the README skeleton; `docs/specs/workflow.md` and the codified-context table reflect actual topology.

Every member issue (52 total) must be referenced from at least one merged WP.

---

## Top three risks

1. **WP07 (admin-surface) is the long pole.** Depends on WP02+WP04. `docs/specs/admin-spa.md` is the most-rewritten spec; if S6 and S8 do not synchronize on admin-surface authority, WP09 re-opens it.
2. **The two new contracts (`KernelServicesInterface`, `composer verify`) need explicit ratification before WP02/WP09 start.** If skipped, scope drift is certain.
3. **WP08 breaks `ConsoleKernel`'s hard-coded command list.** Any external scripts reading the current registration path break in a single commit. The manifest cache fingerprint forces a one-time recompile across all installs. Coordination with downstream consumers (Minoo, Claudriel) is required.
