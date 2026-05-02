# Plan: 1107-api-symfony-decoupling

Four phases mapped to WP02-WP05 (plus optional WP06 for boundary enforcement). Phase boundaries are merge points; nothing crosses a phase line until the prior phase's WPs are `approved` per `docs/specs/workflow.md`.

## Phase 0 — Ratification (WP01 acceptance)

Objective: lock C1-C5 and the charter-vs-body framing before any implementation WP starts.

- User ratifies C1 (JsonApiResponse: subclass / wrapper).
- User ratifies C2 (Foundation\Http\Request: alias / wrapper).
- User ratifies C3 (DomainEvent parent: keep Symfony / flip / PSR-14 only).
- User ratifies C4 (trait ownership in api package: move-and-deprecate / delete).
- User ratifies C5 (boundary linter: in-scope / follow-up).
- User picks Path R-narrow (charter wins) vs Path R-wide (anchor body wins; routing in scope).

Exit criteria: choices recorded in `spec.md`. WP01 marked done. No code changes.

## Phase 1 — Foundation types in parallel (WP02 + WP03)

Objective: stand up `Waaseyaa\Foundation\Http\Request` and `Waaseyaa\Foundation\Event\EventDispatcherInterface` as published surfaces.

- WP02 (S2): Request type per ratified C2. If alias, one file + autoload entry + spec update. If wrapper, real class + ControllerDispatcher signature flip + every controller signature in tree updated.
- WP03 (S3): EventDispatcherInterface + SymfonyEventDispatcherAdapter. DomainEvent parent class action depends on C3. Contract test in `packages/foundation/tests/Contract/` covers PSR-14 dispatch / subscribe / stop semantics.

Exit criteria: WP02 and WP03 both approved. Foundation publishes the new surfaces. `bin/check-package-layers` clean.

## Phase 2 — API response and trait consolidation (WP04)

Objective: ship `Waaseyaa\Api\Http\JsonApiResponse` and consolidate the two parallel JSON:API traits.

- WP04 (S1, S4): JsonApiResponse class per C1. Canonical trait in api package per C4. `Waaseyaa\Api\JsonResponseTrait` removed. JsonApiController and SchemaController migrated.
- Layer-rule check: foundation L0 cannot import api L4. C4 (a) deprecation shim must live in api with foundation either re-exporting a deprecated alias or deleting outright. WP04 picks the cleanest path.

Exit criteria: WP04 approved. New response type in production use. Old api-package trait gone.

## Phase 3 — Spec lock and contract test (WP05)

Objective: codify the boundary in spec docs and prove it via a test that fails if a sample controller imports any `Symfony\` class.

- Update five spec docs: `api-layer.md`, `jsonapi.md`, `http-entry-point.md`, `middleware-pipeline.md`, `infrastructure.md`.
- Add contract test in `packages/api/tests/Contract/` that asserts a sample app controller produces a JSON:API response without `use Symfony\` imports.
- Anchor `#1107` body annotated with merged-commit references.
- If C5 = (b) follow-up: file `enforce-symfony-import-boundary` as a new issue referencing this mission's merged commits.

Exit criteria: WP05 approved. Mission accepts.

## Phase 4 — Optional: boundary enforcement (WP06)

**Runs only if C5 = (a) in-scope.** Otherwise skipped; new issue filed at Phase 3 close.

- WP06 (S5): `bin/check-symfony-imports` script.
- Allowlist covers framework-internal packages: Foundation, Routing, API internals, Validation, CLI.
- Wired into `composer verify` (the root command from 824 mission).

Exit criteria: WP06 approved. App-code Symfony imports outside allowlist fail CI.

## Cross-phase invariants

- No new dependency from L0 Foundation to L4+ packages. (C4 trait shim respects this.)
- `composer verify` (824 mission) gates every WP merge.
- No `psr/log`. Use `Waaseyaa\Foundation\Log\LoggerInterface` everywhere.
- Symfony engine remains under the framework. Foundation, Routing, API internals, Validation, CLI continue to import Symfony freely. App code does not. Bimaaji decoupling is a separate future mission.
- The scope tightening (Path R-narrow recommended) means routing definitions still leak Symfony Route types through `RouteBuilder`. App code that defines routes via providers continues to import `Symfony\Component\Routing\Route` after this mission lands. Acknowledged.

## Sequencing summary

```
WP01 (ratify) ─┬─→ WP02 (Request) ─┐
                 │                    ├─→ WP04 (JsonApiResponse) ─→ WP05 (spec lock)
                 ├─→ WP03 (Dispatcher)┘                              │
                 │                                                    │
                 └─→ [WP06 if C5 in-scope, else follow-up issue at WP05 close]
```

WP02 → WP04 is the critical path (response consumes Request). WP03 is parallel-safe. WP05 gates mission acceptance. WP06 is optional.
