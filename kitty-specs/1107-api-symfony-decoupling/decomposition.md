# Decomposition — api-symfony-decoupling

Date: 2026-04-29 (Pass 2 WP01 output)

Mission charter (per `spec.md`): "Wrap Symfony HTTP types behind Waaseyaa request/response abstractions so app code never imports Symfony directly. Scope is HTTP layer only." Anchor `#1107` is open. The mission has no other absorbed children — the `meta.json` `child_issues` array contains only the anchor itself.

## Mission summary

This is an **architectural mission with a thin contract surface**. The thesis is single-axis: introduce Waaseyaa-owned HTTP request/response and event-dispatch types so that consumer apps (`northops-waaseyaa`, `minoo`, etc.) can build controllers and listeners without importing `Symfony\Component\HttpFoundation\*` or `Symfony\Contracts\EventDispatcher\*`. The framework retains Symfony as the engine; Symfony imports remain legal in Foundation/Routing/API internals (Routing is keyed on the `symfony/routing` runtime; HttpFoundation is the request lifecycle in `public/index.php` and `ControllerDispatcher`). What is not legal, after this mission lands, is for application code to reach across the framework boundary into Symfony directly.

Live source confirms a small, contained surface: `packages/api/src/` has roughly 4 distinct Symfony imports across two namespaces (`HttpFoundation\Request|Response|JsonResponse`); `packages/routing/src/` has 6 distinct imports all under `Symfony\Component\Routing\*` (Route, RouteCollection, RequestContext, UrlMatcher, UrlGenerator, ResourceNotFoundException). The existing `JsonResponseTrait` (api package) and `JsonApiResponseTrait` (foundation package) both return raw `JsonResponse`. There is no `Waaseyaa\Api\JsonApiResponse` class today. There is no `Waaseyaa\Foundation\Http\Request` wrapper today. There is no `Waaseyaa\Foundation\Event\EventDispatcherInterface` today — `Waaseyaa\Foundation\Event\DomainEvent` extends Symfony's `Contracts\EventDispatcher\Event` directly.

**Mode: architectural.** The work is to introduce 3-4 new public framework interfaces/classes, migrate one trait, deprecate (but not remove) direct Symfony imports in app code, and update specs. It mirrors the 619 organ-extraction pattern more than the 1257 hardening pattern: new public surface, contract ratification needed, modest blast radius. The mission spec itself flags that it is a "minimal scaffold" and acceptance must be defined per WP — that gap is exactly what this decomposition fills.

## Absorbed issues + open anchor

| Issue | State | Title | Role |
|---|---|---|---|
| #1107 | OPEN | feat: wrap Symfony dependencies so app code never imports Symfony directly | Anchor + sole child. Defines four abstractions (table in body): `JsonApiResponse`, `Foundation\Http\Request`, `Foundation\Event\EventDispatcherInterface`, expanded `JsonApiResponseTrait` usage. |

There are no closed absorbed children. The mission is a single-issue charter awaiting decomposition. This decomposition is the first artifact that turns the four-row abstraction table into ratifiable contracts.

## Contract surfaces consolidated

### S1 — `Waaseyaa\Api\Http\JsonApiResponse` (new public class)

- **Current state.** No such class exists. App code constructs `new JsonResponse(['jsonapi' => [...]])` inline, or reaches for `JsonApiResponseTrait::jsonApiResponse()` in foundation (which still returns `Symfony\Component\HttpFoundation\JsonResponse`). The api-package `JsonResponseTrait::json()` is even thinner — returns raw `JsonResponse`, no JSON:API envelope.
- **Target state.** A concrete `Waaseyaa\Api\Http\JsonApiResponse` value object (or thin subclass of `Symfony\Component\HttpFoundation\JsonResponse` for engine compatibility). Constructor takes `JsonApiDocument` (or array) + status + headers; sets `application/vnd.api+json` content type and JSON encoding flags automatically. Becomes the single public response builder for app controllers.
- **Open question.** Subclass-of-Symfony vs. wrapper-with-`toResponse()` method. Subclassing is pragmatic (passes `instanceof Response` checks in `ControllerDispatcher`) but leaks the parent type signature back into app code via reflection/type-hints. Wrapper is cleaner but requires every dispatch path to call `$response->toResponse()`. **Needs ratification.**
- **Spec docs.** `docs/specs/api-layer.md` (File Reference table), `docs/specs/jsonapi.md` (response shape).

### S2 — `Waaseyaa\Foundation\Http\Request` (new public type)

- **Current state.** No `Waaseyaa\Foundation\Http\Request` exists. `ControllerDispatcher` and routing both type-hint `Symfony\Component\HttpFoundation\Request` directly. App controllers receive that type. The api-package `JsonResponseTrait::jsonBody(Request $request)` parameter is a Symfony Request.
- **Target state.** One of two shapes (must be ratified):
  - **Option A — Re-export alias.** `Waaseyaa\Foundation\Http\Request` = class alias of `Symfony\Component\HttpFoundation\Request`. Zero-cost, app code uses the Waaseyaa name, framework continues to consume Symfony internally.
  - **Option B — Thin wrapper.** Real Waaseyaa class composing the Symfony request, exposing only `getMethod()`, `getPathInfo()`, `getContent()`, `getQuery($key)`, `headers()`, `attributes()`, etc. Hides Symfony entirely but requires every controller signature to flip and `ControllerDispatcher` to translate at the boundary.
- **Risk.** Option B is a breaking change for every consumer controller already in tree. Option A is reversible later. **Charter says "re-export or thin wrapper" — both are on the table.** Needs ratification.
- **Spec docs.** `docs/specs/http-entry-point.md`, `docs/specs/middleware-pipeline.md`, `docs/specs/api-layer.md`.

### S3 — `Waaseyaa\Foundation\Event\EventDispatcherInterface` (new public interface)

- **Current state.** Every consumer that dispatches events imports `Symfony\Contracts\EventDispatcher\EventDispatcherInterface` directly. `Waaseyaa\Foundation\Event\DomainEvent` extends `Symfony\Contracts\EventDispatcher\Event`. Live grep shows the Symfony Contracts EventDispatcher import scattered across entity, ssr, northcloud, foundation kernel, search, cache, graphql.
- **Target state.** A `Waaseyaa\Foundation\Event\EventDispatcherInterface` (PSR-14 compatible signature: `dispatch(object $event): object`) plus a default adapter `Waaseyaa\Foundation\Event\SymfonyEventDispatcherAdapter` wrapping the Symfony service. App code (and ideally framework packages above Foundation) type-hint the Waaseyaa interface; the kernel binds the adapter.
- **Coupling.** `DomainEvent extends Symfony\Contracts\EventDispatcher\Event` is the deeper hook. Changing the parent class is a breaking change for every event subclass. Either (a) `DomainEvent` extends a Waaseyaa-owned `Event` base and the adapter bridges, or (b) the inheritance stays and only the dispatcher interface is wrapped. **Needs ratification.**
- **Spec docs.** `docs/specs/infrastructure.md` (event subsystem), new section in `docs/specs/api-layer.md` on event-dispatch boundary.

### S4 — Trait consolidation: `JsonResponseTrait` → `JsonApiResponseTrait` (single source)

- **Current state.** Two parallel traits: `Waaseyaa\Api\JsonResponseTrait` (returns raw `JsonResponse`, no envelope) and `Waaseyaa\Foundation\Http\JsonApiResponseTrait` (sets vnd.api+json content-type, encoding options). The api-package `JsonApiController` does not use either consistently. Spec body of #1107 names `JsonApiResponseTrait` as the canonical pattern "started in alpha.104, expand usage."
- **Target state.** Single canonical trait owned by the api package (since JSON:API is api-layer concern), wrapping S1's `JsonApiResponse`. Foundation's trait is deprecated or moved to api. The api-package `JsonResponseTrait` is removed (no envelope, no value).
- **Spec docs.** `docs/specs/api-layer.md` File Reference table.

### S5 — Symfony-import boundary policy (new convention, possibly enforced)

- **Current state.** No tooling enforces "app code does not import Symfony." The mission's stated acceptance ("Existing apps continue to work, Symfony imports still function, just not recommended") is soft.
- **Target state.** A `bin/check-symfony-imports` script (or a rule in the existing layer linter) that scans non-framework consumer code and warns/fails on `use Symfony\` imports outside an allowlist. Allowlist covers Foundation, Routing, API internals, Validation, CLI (the kernel-adjacent packages that legitimately consume Symfony).
- **Open question.** Is this in-scope for #1107, or a follow-up? The charter says "scope is HTTP layer only," which argues for follow-up. But without enforcement, the abstractions rot.

## SPLIT vs NO-SPLIT decision

**NO-SPLIT.**

Justification:

1. The four abstractions (S1, S2, S3, S4) are tightly coupled. `JsonApiResponse` is unusable without the trait migration (S4). The trait migration is unusable without a Request wrapper to feed `jsonBody()` (S2). The dispatcher interface (S3) is independent of HTTP but is in the charter and shares the same "wrap Symfony with Waaseyaa-owned interface" architectural pattern.
2. There is exactly one closed/absorbed child issue beyond the anchor — zero, in fact. SPLIT requires 2+ independent contract clusters with member issues to assign. There are no member issues to assign.
3. The mission's net new public surface is 3-4 classes/interfaces. That fits comfortably inside one mission of 3-5 work packages.
4. Splitting "abstract HttpFoundation" from "abstract EventDispatcher" would require two missions both consuming the same boilerplate (decomposition, plan, tasks, WP01 setup) for a combined surface smaller than 824's S2 alone.

The `bimaaji` package (also at L4) is not in scope — its Symfony coupling is separate and lower-priority. If a future bimaaji-decoupling mission emerges, it can reuse S1-S3 as ratified contracts.

## Proposed WP roster

WP01 is decomposition (this artifact). WP02+ sequenced by dependency.

| WP | Title | Depends on | Surface | Notes |
|---|---|---|---|---|
| WP01 | Decomposition (this doc) | — | — | Ratifies surfaces, decides Option A vs B for S2, decides subclass vs wrapper for S1, decides DomainEvent strategy for S3. |
| WP02 | Foundation HTTP request type (S2) | WP01 ratification | `Waaseyaa\Foundation\Http\Request` | Smallest first. If Option A (alias) wins, this is one file plus spec update. If Option B (wrapper) wins, this is the largest WP and absorbs `ControllerDispatcher` signature changes. |
| WP03 | Foundation event dispatcher interface (S3) | WP01 ratification | `Waaseyaa\Foundation\Event\EventDispatcherInterface` + adapter | Independent of HTTP path. Can run in parallel with WP02 if worktrees coordinate. |
| WP04 | API JsonApiResponse + trait consolidation (S1, S4) | WP02 (consumes Request) | `Waaseyaa\Api\Http\JsonApiResponse`, single canonical trait | Migrates `JsonApiController` and `SchemaController` to the new response type. Removes `Waaseyaa\Api\JsonResponseTrait`. |
| WP05 | Spec doc updates + integration test | WP02-WP04 | `docs/specs/api-layer.md`, `docs/specs/jsonapi.md`, `docs/specs/middleware-pipeline.md`, `docs/specs/infrastructure.md`, new contract test in `packages/api/tests/Contract/` | Codifies the policy. Adds a test that asserts a sample app controller can produce a JSON:API response without importing any `Symfony\` class. |

WP06 (boundary enforcement, S5) is **deferred to a follow-up issue** unless WP01 ratification pulls it forward.

## PROPOSED CONTRACTS — needs ratification before WP02 implement

These are the choice points. WP01 acceptance requires explicit decisions on each.

### C1 — JsonApiResponse: subclass or wrapper?

`Waaseyaa\Api\Http\JsonApiResponse` either:

- **(a)** `extends \Symfony\Component\HttpFoundation\JsonResponse` — `instanceof Response` works in `ControllerDispatcher`, no translation layer needed, but app code accidentally typing `Symfony\...\JsonResponse` still compiles cleanly and the leak is hidden.
- **(b)** standalone class with `toSymfonyResponse(): JsonResponse` — `ControllerDispatcher` learns to call the converter, app code physically cannot pass it where Symfony types are required, but every dispatch path needs an update.

Recommendation: **(a) for v1**, with a doc note that future major versions may flip to (b). Pragmatic, reversible, ships in one WP.

### C2 — Foundation\Http\Request: alias or wrapper?

`Waaseyaa\Foundation\Http\Request` either:

- **(a)** `class_alias(\Symfony\Component\HttpFoundation\Request::class, 'Waaseyaa\Foundation\Http\Request')` — zero behavior change, app code uses the Waaseyaa name, all existing Symfony methods still callable.
- **(b)** real composition wrapper exposing a curated method surface.

Recommendation: **(a) for v1**. (b) is a multi-mission migration; this mission is "scope is HTTP layer only" per charter.

### C3 — DomainEvent parent class: keep Symfony or flip?

`Waaseyaa\Foundation\Event\DomainEvent` currently `extends \Symfony\Contracts\EventDispatcher\Event`. Three options:

- **(a)** Keep parent, only wrap the dispatcher. App code uses `Waaseyaa\...\EventDispatcherInterface` to dispatch, but event classes still extend Symfony's base via DomainEvent. Acceptable leak — event base classes are a framework-internal contract.
- **(b)** Flip parent to a Waaseyaa-owned `Event` base. Breaking change for every existing event subclass.
- **(c)** PSR-14 only — neither inheritance required, just dispatch contract. Most flexible, biggest semantic shift.

Recommendation: **(a) for v1**. The leak is contained to Foundation; consumers don't see it.

### C4 — Trait ownership

The canonical JSON:API response trait lives in **`packages/api`** (api-layer concern), not foundation. Foundation's trait either moves or is deleted. Recommendation: move-and-deprecate the foundation trait with a deprecation shim that proxies to the api one. (If api → foundation isn't a legal layer edge, the foundation trait is just deleted — confirm in WP02.)

### C5 — Symfony-import boundary: in-scope or out-of-scope?

If C5 is in-scope, WP06 ships `bin/check-symfony-imports`. If out-of-scope, a follow-up issue is filed referencing this mission. Recommendation: **out-of-scope, file follow-up** — keeps mission focused on the abstractions themselves.

## Drift flags

1. **Mission spec is a stub.** `spec.md` says "Acceptance: To be defined per work package. The charter alone is not enough to drive execution." This decomposition is the first artifact to turn the four-row table from #1107's body into testable contracts. The spec must be expanded before WP02 enters `implement`.

2. **No closed absorbed issues.** Despite the spec mentioning "closed issues listed in `meta.json` `child_issues`," the array contains only the anchor #1107 itself. The narrative "this mission absorbs closed issues" is not factually true on disk. Either prior triage decisions to absorb #1108/#1112/etc. were never recorded, or the mission really is single-issue. Treat it as single-issue.

3. **`JsonApiResponseTrait` is in foundation, `JsonResponseTrait` is in api.** Two traits, both backing the same use case, both returning raw Symfony types. The api-package one (`JsonResponseTrait`) is the more anemic and predates `JsonApiResponseTrait`. WP04 must reconcile, not just add a third class on top.

4. **`DomainEvent extends Symfony Event` is documented nowhere.** This inheritance is an undocumented public surface of the framework. Any app subclassing `DomainEvent` already has a Symfony dependency they cannot drop. The charter does not address this.

5. **Routing package is more deeply Symfony-coupled than API.** `packages/routing/src/` has 6 distinct `Symfony\Component\Routing\*` imports (Route, RouteCollection, RequestContext, UrlMatcher, UrlGenerator, ResourceNotFoundException). The `WaaseyaaRouter` is a thin wrapper but its public method `addRoute(string $name, Route $route)` and `getRouteCollection(): RouteCollection` leak Symfony types in their signatures. The mission charter says "scope is HTTP layer only" which excludes routing definitions, but `RouteBuilder` and `JsonApiRouteProvider` will keep leaking Symfony Route types into provider code. Flag for explicit out-of-scope acknowledgment.

6. **`AuthOidcRouteServiceProvider` (per the project CLAUDE.md exemption) is the canonical pattern for L1 → L4 route registration.** It already accepts L4 routing types. This pattern stays; nothing in #1107 disturbs it. Confirm in spec update.

7. **No conflict with already-ratified contracts.** `KernelServicesInterface` (824 WP02), C1-C10 from 619, K1-K7+C1 from 1257 — none of these intersect with HTTP request/response types or event dispatcher boundaries. Clean room.

## Risks

1. **Subclass leak risk (C1).** If `JsonApiResponse` extends Symfony's `JsonResponse`, an app developer can still type-hint the parent and miss the abstraction. Mitigation: doc note + linter rule (deferred to S5/follow-up). Severity: medium.

2. **Adapter binding correctness (C3/S3).** A Waaseyaa `EventDispatcherInterface` adapter that wraps Symfony's must preserve subscriber-discovery and stoppable-event semantics. PSR-14 covers stoppable events but not subscriber registration. Mitigation: contract test in `packages/foundation/tests/Contract/` covering subscribe/dispatch/stop. Severity: medium.

3. **Documentation churn.** Five spec docs touched (api-layer, jsonapi, http-entry-point, middleware-pipeline, infrastructure). Higher than typical for a mission of this size. Mitigation: WP05 is dedicated to spec updates. Severity: low.

4. **Charter underspecification leading to scope creep.** Without explicit C1-C5 ratification, WP02 implementers may pick the wrong option (e.g., real Request wrapper) and balloon the mission. Mitigation: WP01 acceptance is explicit ratification of C1-C5 before any WP enters implement. Severity: high if skipped, low if enforced.

5. **Consumer migration ambiguity.** Acceptance says "existing apps continue to work, Symfony imports still function, just not recommended." But "not recommended" means nothing without a deprecation signal. Mitigation: spec update names a target version (e.g., "removed in v0.3.0") or commits to indefinite soft-deprecation. Severity: low.

6. **Routing-out-of-scope drift.** App code that registers routes via `RouteBuilder` will continue to import `Symfony\Component\Routing\Route`. The mission does not solve "app code never imports Symfony" — it solves "app controllers never import Symfony in the request/response path." The framing in #1107's body is broader than the framing in `spec.md`'s charter. Resolve in spec update by tightening the body's framing or expanding the charter's scope (recommend the former). Severity: medium.
