# Tasks / work packages — 1107-api-symfony-decoupling

WP01 was the Pass-2 decomposition (`decomposition.md` in this directory). WP02-WP05 sequenced; WP06 deferred unless C5 ratifies in-scope.

| WP | Title | Outcome | Status |
|----|-------|---------|--------|
| WP01 | mission-decomposition | `decomposition.md`. NO-SPLIT decision. 5 contract surfaces (S1-S5) and 5 choice points (C1-C5) surfaced. 7 drift flags raised. Mode = architectural. | done |
| WP02 | foundation-http-request-type | `Waaseyaa\Foundation\Http\Request` ships as `class_alias(\Symfony\Component\HttpFoundation\Request::class, 'Waaseyaa\Foundation\Http\Request')` per ratified C2 (a). One file + autoload entry + spec update. WP02 also performs the C4 layer-rule prerequisite check: confirm a foundation-side deprecation shim for `JsonApiResponseTrait` is implementable without a foundation L0 → api L4 import edge. Records the chosen shim path in this file before WP04 enters implement. If no clean shim path exists, surface to user; do not downgrade to C4 option (b) silently. | unscheduled |
| WP03 | foundation-event-dispatcher-interface | `Waaseyaa\Foundation\Event\EventDispatcherInterface` (PSR-14 compatible: `dispatch(object $event): object`) + `SymfonyEventDispatcherAdapter`. Kernel binds the adapter; consumer packages migrate type-hints from `Symfony\Contracts\EventDispatcher\EventDispatcherInterface` to the Waaseyaa interface. C3 ratification determines whether `DomainEvent` parent class flips (option b/c) or stays Symfony's `Event` (option a). Contract test covers subscribe / dispatch / stop. | unscheduled |
| WP04 | api-jsonapiresponse-and-trait-consolidation | `Waaseyaa\Api\Http\JsonApiResponse` exists per ratified C1 (subclass or wrapper). Canonical trait lives in api package per C4 (move-and-deprecate or delete). `Waaseyaa\Api\JsonResponseTrait` removed. `JsonApiController` and `SchemaController` migrated. | unscheduled |
| WP05 | spec-docs-and-contract-test | Five spec docs updated: `docs/specs/api-layer.md`, `docs/specs/jsonapi.md`, `docs/specs/http-entry-point.md`, `docs/specs/middleware-pipeline.md`, `docs/specs/infrastructure.md`. Contract test in `packages/api/tests/Contract/` asserts a sample app controller produces a JSON:API response without importing any `Symfony\` class. Anchor `#1107` body annotated with merged-commit references. | unscheduled |
| WP06 | symfony-import-boundary-linter | **Dropped from this mission per ratified C5 (b).** WP05 files a new GitHub issue at acceptance: "enforce Symfony-import boundary across consumer code" with allowlist scope (Foundation, Routing, API internals, Validation, CLI) and reference to this mission's merged commits. | dropped |

**Review gate:** Each WP runs through Spec Kitty implement → review per `docs/specs/workflow.md`. WP02 cannot enter implement until C1-C5 are ratified by user and recorded in `spec.md`.

## Dependencies (between WPs)

- WP01 (this) → WP02, WP03, WP04, WP05 (ratification gate)
- WP02 ⟂ WP03 (parallel; independent surfaces)
- WP02 → WP04 (WP04 consumes the new Request type)
- WP02 + WP03 + WP04 → WP05 (spec updates and contract test reference all three)
- WP06 conditional on C5 ratification

## Per-WP gating notes

- **WP01 acceptance** = C1-C5 ratified, Path R-narrow chosen (all locked in `spec.md` 2026-04-30).
- **WP02** is small (C2 (a) chosen): one file + autoload entry + spec update. WP02 also runs the C4 layer-rule prerequisite check before WP04 enters implement. If shim isn't cleanly implementable, surface to user.
- **WP03** is independent of HTTP path. Can run in parallel with WP02 if worktrees coordinate. Contract test must cover stoppable-event semantics (PSR-14) and subscriber registration (Symfony-specific).
- **WP04** must verify the layer rule for the C4 deprecation shim. Foundation L0 cannot import api L4. The shim, if it exists, lives in api and foundation either re-exports a deprecated alias or deletes outright.
- **WP05** is non-skippable. The mission's value is the spec lock plus the contract test that proves a controller can ship without `use Symfony\`. Without WP05, the abstractions exist but the boundary isn't testable.
- **WP06** is dropped (C5 (b) ratified). WP05 acceptance includes filing the follow-up issue.
