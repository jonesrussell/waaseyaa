# Mission spec: 1107-api-symfony-decoupling

**Charter:** Wrap Symfony HTTP types behind Waaseyaa request/response abstractions so app code never imports Symfony directly. Introduce a Waaseyaa-owned `EventDispatcherInterface`. Consolidate the two parallel JSON:API response traits. Scope is HTTP request/response and event-dispatch layer only — Routing internals stay Symfony-coupled (separate mission if needed).

**Milestone:** Track 3 — Parity & performance

**Origin:** Pass 1 architect-mode triage (2026-04-30). Single-issue mission anchored on `#1107`.

**Decomposition artifact:** `decomposition.md` in this directory.

---

## Single-issue mission notice

`meta.json` `child_issues` contains only the anchor `#1107` itself. The scaffold language *"absorbs the closed issues listed in meta.json child_issues"* is factually empty for this mission. There are no absorbed siblings; the four-row abstraction table in the anchor's body is the entire surface. WP02 spec acceptance treats `#1107` as a contract-defining issue, not an aggregator.

---

## Decision: NO-SPLIT (5 WPs, 1 deferred)

The four abstractions (S1-S4) are tightly coupled: `JsonApiResponse` (S1) is unusable without trait migration (S4); the trait migration is unusable without a Request type (S2); the dispatcher interface (S3) is the same architectural pattern (wrap Symfony with a Waaseyaa-owned interface). One mission, sequential WPs, with WP06 (S5: import-boundary linter) deferred unless C5 ratifies it in-scope.

**Mode: architectural** (small surface). 3-4 new public framework types. Modest blast radius.

| WP | Title | Surface | Notes |
|----|-------|---------|-------|
| WP02 | foundation-http-request-type | S2 | Smallest first. Shape depends on C2 (alias vs wrapper). If alias, one file + spec; if wrapper, absorbs `ControllerDispatcher` signature changes. |
| WP03 | foundation-event-dispatcher-interface | S3 | Independent of HTTP path. Parallel-safe with WP02. |
| WP04 | api-jsonapiresponse-and-trait-consolidation | S1, S4 | Migrates `JsonApiController` and `SchemaController` to the new response type. Removes `Waaseyaa\Api\JsonResponseTrait`. |
| WP05 | spec-docs-and-contract-test | All | Updates 5 spec docs; adds contract test asserting a sample app controller produces a JSON:API response without importing any `Symfony\` class. |
| WP06 | symfony-import-boundary-linter | S5 | **Deferred to follow-up issue unless C5 ratifies in-scope.** `bin/check-symfony-imports` script + allowlist. |

**Sequencing.** WP02 first (Request type required by WP04). WP03 in parallel with WP02 (independent surface). WP04 after WP02 (consumes Request). WP05 after WP02-WP04 (codifies in spec).

Per-WP detail in `tasks.md`.

---

## Charter-vs-body framing — RATIFIED Path R-narrow (2026-04-30)

Anchor `#1107`'s body framing read "app code never imports Symfony directly" (broad) while the mission scaffold's charter read "scope is HTTP layer only" (narrow). Live-source analysis confirmed `packages/routing/` leaks 6 distinct `Symfony\Component\Routing\*` types in public method signatures.

**Decision: Path R-narrow.** Mission solves request/response/event-dispatch only. Routing stays Symfony-coupled — `RouteBuilder` consumers continue to import `Symfony\Component\Routing\Route` after this mission lands. WP05 spec updates tighten the anchor body framing to match the charter. WP05 also files a follow-up mission (`routing-symfony-decoupling`) referencing this mission's merged commits. Path R-narrow keeps the mission shippable in 4 small WPs and aligns with the existing `AuthOidcRouteServiceProvider` exception (canonical L1→L4 route registration per `CLAUDE.md`).

---

## Ratified contracts (C1-C5) — approved 2026-04-30

All five choice points decided. Pragmatic, reversible v1 shape across the board. WP04 carries the C4 layer verification as preliminary work.

### C1 — `Waaseyaa\Api\Http\JsonApiResponse`: subclass — RATIFIED option (a)

`Waaseyaa\Api\Http\JsonApiResponse extends \Symfony\Component\HttpFoundation\JsonResponse`. `instanceof Response` works in `ControllerDispatcher`; no translation layer. WP05 spec docs add a note that future major versions may flip to a standalone wrapper if the C5 linter (deferred) surfaces leak patterns. Pragmatic, reversible, ships in one WP.

### C2 — `Waaseyaa\Foundation\Http\Request`: class alias — RATIFIED option (a)

`class_alias(\Symfony\Component\HttpFoundation\Request::class, 'Waaseyaa\Foundation\Http\Request')`. Zero behavior change. App code uses the Waaseyaa name; all Symfony methods remain callable. A real composition wrapper is a multi-mission migration explicitly out of scope here.

### C3 — `Waaseyaa\Foundation\Event\DomainEvent` parent class: keep Symfony — RATIFIED option (a)

`DomainEvent` continues to `extends \Symfony\Contracts\EventDispatcher\Event`. Only the dispatcher gets a Waaseyaa-owned interface (S3, WP03). App code uses `Waaseyaa\Foundation\Event\EventDispatcherInterface` to dispatch; event subclasses still inherit Symfony's `Event` via `DomainEvent`. Leak contained to Foundation; consumers don't see it directly. WP05 documents this as an explicit framework-internal contract in `docs/specs/infrastructure.md` (closes drift D4).

### C4 — Trait ownership: foundation-canonical (inverted) — RATIFIED option (a-inverted) on 2026-05-03

**Original ratification (2026-04-30):** option (a) move-and-deprecate; canonical trait moves to `packages/api`; foundation keeps a deprecation shim. **Amended on 2026-05-03 after WP02 prerequisite audit.**

WP02's T004 layer-rule audit found that none of the three shim paths in the original plan are implementable:

1. `class_alias` shim — PHP only aliases classes, not traits.
2. Hard-delete the foundation trait — would break 9 in-package consumers (`HttpKernel`, `ControllerDispatcher`, and 7 routers: `JsonApiRouter`, `SchemaRouter`, `SearchRouter`, `McpRouter`, `BroadcastRouter`, `EntityTypeLifecycleRouter`, `CodifiedContextApiRouter`).
3. Foundation trait re-implements the payload internally — keeps two divergent copies, defeats consolidation goal.

**Amended decision (a-inverted):** **Canonical trait stays in `packages/foundation` as `Waaseyaa\Foundation\Http\JsonApiResponseTrait`** (where it already lives and where 9 in-package consumers + 4 cross-layer consumers — `graphql/GraphQlRouter`, `ssr/SsrRouter`, `ssr/AppControllerRouter`, `media/MediaRouter` — already import it). The duplicate `Waaseyaa\Api\JsonResponseTrait` is **deleted**. Api consumers (`JsonApiController`, `SchemaController`, etc.) `use Waaseyaa\Foundation\Http\JsonApiResponseTrait` directly. L4 → L0 imports are allowed by the layer rule, so no shim is needed.

This still consolidates to one trait, still removes the duplicate, still resolves drift D3 — just from foundation's side rather than api's. WP04's surface is unchanged: ship `Waaseyaa\Api\Http\JsonApiResponse` (the new response *class*) and remove the duplicate trait. WP05 still documents the canonical pattern in `docs/specs/jsonapi.md` and `docs/specs/api-layer.md`.

Audit evidence and recommendation are in `tasks/WP02-foundation-http-request-type.md` activity log.

### C5 — Symfony-import boundary linter: out-of-scope — RATIFIED option (b)

`bin/check-symfony-imports` deferred to a follow-up mission. WP06 (the linter WP) is dropped from this mission. WP05 acceptance includes: file a new GitHub issue titled "enforce Symfony-import boundary across consumer code" with body referencing this mission's merged commits, the abstractions ratified in C1-C4, and the soft-rot tradeoff acknowledgment.

---

## Drift flags

| # | Flag | Resolution |
|---|------|------------|
| D1 | Mission spec is a stub. `spec.md` says acceptance is undefined. | THIS spec.md is the expansion. WP01 acceptance requires C1-C5 ratified. |
| D2 | No closed absorbed children. `meta.json` has only the anchor. | Acknowledged as single-issue mission. Spec language reframed (above). |
| D3 | `JsonApiResponseTrait` in foundation, `JsonResponseTrait` in api. Two traits, same use case. | C4 resolves. WP04 reconciles. |
| D4 | `DomainEvent extends Symfony Event` is an undocumented public surface. | C3 resolves and documents the choice in `docs/specs/infrastructure.md`. |
| D5 | Routing package leaks 6 Symfony types in public signatures. Charter says "HTTP layer only" but anchor body framing implies routing too. | "Charter-vs-body tension" decision (above) resolves. Path R-narrow recommended. |
| D6 | `AuthOidcRouteServiceProvider` is the canonical L1→L4 route pattern (per `CLAUDE.md` exemption). | Confirmed unchanged by this mission. Spec update notes this. |
| D7 | No conflict with prior ratified contracts (824 `KernelServicesInterface`, 619 C1-C10, 1257 K1-K7+C1). | Clean room confirmed. |

---

## Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | `Waaseyaa\Api\Http\JsonApiResponse` MUST exist per ratified C-001 (subclass of `\Symfony\Component\HttpFoundation\JsonResponse`). | ratified |
| FR-002 | `Waaseyaa\Foundation\Http\Request` MUST exist per ratified C-002 (`class_alias` of `\Symfony\Component\HttpFoundation\Request`). | ratified |
| FR-003 | `Waaseyaa\Foundation\Event\EventDispatcherInterface` MUST exist (PSR-14 compatible) with a `SymfonyEventDispatcherAdapter` default binding. | ratified |
| FR-004 | **Amended 2026-05-03:** The canonical JSON:API response trait MUST live in `packages/foundation` as `Waaseyaa\Foundation\Http\JsonApiResponseTrait` (its existing location). The duplicate `Waaseyaa\Api\JsonResponseTrait` MUST be removed. Api consumers MUST import foundation's trait directly (L4 → L0). | ratified |
| FR-005 | The five spec docs `api-layer.md`, `jsonapi.md`, `http-entry-point.md`, `middleware-pipeline.md`, `infrastructure.md` MUST reference the new types and tighten charter language to Path R-narrow. | ratified |
| FR-006 | A contract test in `packages/api/tests/Contract/` MUST assert that a sample app controller produces a JSON:API response without importing any `Symfony\` class. | ratified |

## Constraints

| ID | Constraint | Decision |
|---|---|---|
| C-001 | `Waaseyaa\Api\Http\JsonApiResponse` shape: subclass vs standalone wrapper. | (a) subclass — `JsonApiResponse extends \Symfony\Component\HttpFoundation\JsonResponse`. |
| C-002 | `Waaseyaa\Foundation\Http\Request` shape: class alias vs real wrapper. | (a) class alias — `class_alias(Symfony Request, Waaseyaa Request)`. |
| C-003 | `Waaseyaa\Foundation\Event\DomainEvent` parent: keep Symfony Event vs flip to PSR-14 only. | (a) keep Symfony parent; only the dispatcher gets a Waaseyaa interface. Documented in `infrastructure.md`. |
| C-004 | `JsonApiResponseTrait` ownership: keep both / move-and-deprecate / hard delete. | **Amended 2026-05-03:** (a-inverted) foundation-canonical. Canonical `Waaseyaa\Foundation\Http\JsonApiResponseTrait` stays in foundation; the duplicate `Waaseyaa\Api\JsonResponseTrait` is deleted; api consumers import foundation's trait (L4 → L0, allowed). Original (a) was unimplementable — WP02 audit confirmed PHP traits cannot be class_aliased and 9 foundation files use the trait. |
| C-005 | `bin/check-symfony-imports` boundary linter: in-scope vs follow-up issue. | (b) follow-up issue. WP06 dropped from this mission; WP05 files the follow-up at acceptance. |

## Acceptance

The mission accepts when ALL of:

1. C1-C5 ratified by user; chosen options recorded in `spec.md`.
2. `Waaseyaa\Foundation\Http\Request` exists per C2 (alias or wrapper).
3. `Waaseyaa\Foundation\Event\EventDispatcherInterface` exists with `SymfonyEventDispatcherAdapter` default binding.
4. `Waaseyaa\Api\Http\JsonApiResponse` exists per C1; canonical trait stays in foundation as `Waaseyaa\Foundation\Http\JsonApiResponseTrait` per amended C4; the duplicate `Waaseyaa\Api\JsonResponseTrait` is removed; api consumers import foundation's trait directly.
5. `JsonApiController` and `SchemaController` migrated to the new response type.
6. Five spec docs updated: `api-layer.md`, `jsonapi.md`, `http-entry-point.md`, `middleware-pipeline.md`, `infrastructure.md`.
7. Contract test in `packages/api/tests/Contract/` asserts a sample app controller produces a JSON:API response without importing any `Symfony\` class.
8. Anchor `#1107` body annotated with merged-commit references (or closed if user prefers; surface at WP05).
9. If C5 ratifies in-scope: `bin/check-symfony-imports` ships in WP06 with allowlist.

Existing apps continue to work; Symfony imports still function but become not-recommended. Hard removal scheduled per C5 follow-up if filed.

---

## Risks

1. **Subclass leak (C1 (a)).** App developers can type-hint Symfony's `JsonResponse` parent and miss the abstraction. Mitigation: doc note + (eventually) the C5 linter. Severity: medium.
2. **Adapter correctness (C3/S3).** A Waaseyaa `EventDispatcherInterface` adapter wrapping Symfony's must preserve subscriber-discovery and stoppable-event semantics. PSR-14 covers stoppable events but not subscriber registration. Mitigation: contract test in `packages/foundation/tests/Contract/`. Severity: medium.
3. **Documentation churn.** Five spec docs touched. WP05 dedicated to spec updates. Severity: low.
4. **Charter underspecification.** Without C1-C5 ratified, WP02 implementer may pick the wrong option (e.g., real Request wrapper) and balloon the mission. Mitigation: WP01 acceptance gates implement on ratification. Severity: high if skipped, low if enforced.
5. **Consumer migration ambiguity.** "Existing apps continue to work, Symfony imports still function, just not recommended" means nothing without a deprecation signal. Mitigation: spec update names a target version (e.g., "removed in v0.3.0") or commits to indefinite soft-deprecation. Severity: low.
6. **Routing-out-of-scope drift (D5).** App code registering routes via `RouteBuilder` will continue importing `Symfony\Component\Routing\Route` post-mission. The mission solves "app controllers never import Symfony in request/response path," not the broader anchor-body framing. Mitigation: Path R-narrow tightens anchor body framing; routing decoupling files as new mission. Severity: medium.
