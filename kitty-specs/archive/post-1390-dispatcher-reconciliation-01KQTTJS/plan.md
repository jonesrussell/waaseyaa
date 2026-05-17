# Implementation Plan: Post-#1390 Dispatcher Reconciliation

**Branch**: `main` | **Date**: 2026-05-05 | **Spec**: [spec.md](./spec.md)
**Input**: [spec.md](./spec.md), [framework#1390](https://github.com/waaseyaa/framework/issues/1390), [framework#1388](https://github.com/waaseyaa/framework/issues/1388)
**Tracking issue**: [framework#1391](https://github.com/waaseyaa/framework/issues/1391) (Track 3 — Parity & performance)
**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`
**Mission ID**: `01KQTTJS73GVXHFPY5W8E8K3DX`

## Branch Contract

- Current branch at plan start: `main`
- Planning/base branch: `main`
- Final merge target for completed changes: `main`
- `branch_matches_target`: true

All WP merges land on `main`.

## Summary

Lock the post-#1390 controller-dispatcher contract for the next alpha after the implicit-array compatibility shim merges. WP01 runs immediately and produces (a) a written contract specification, (b) a coverage audit of all controller signatures shipped by the framework, and (c) a self-contained Resume Verification Plan that lets Minoo's frozen `upgrade-waaseyaa-alpha-171-01KQTDC2` mission re-test against the new alpha. WP02+ — deprecation logger plumbing, contract test coverage, CHANGELOG, and the alpha-cut prep — execute only after #1390 lands on `main`. The mission is strictly framework-scoped, does not re-implement #1390, and does not modify any consumer.

## Technical Context

**Language/Version**: PHP 8.4+ (project minimum, per CLAUDE.md). `declare(strict_types=1)` in every file.
**Primary Dependencies**: Symfony 7.x components (Console, EventDispatcher, Routing, Validator, Uid, Yaml, Messenger), Doctrine DBAL (entity storage abstraction), `Waaseyaa\Foundation\Log\LoggerInterface` (no `psr/log`).
**Storage**: N/A for this mission. Existing `DBALDatabase::createSqlite()` for any test fixture that needs persistence; not expected for dispatcher contract tests.
**Testing**: PHPUnit 10.5 (do **not** use `-v`). Contract tests under `packages/ssr/tests/Contract/`. Unit tests under `packages/ssr/tests/Unit/Http/AppController/`. Use `#[Test]`, `#[CoversClass]`, `#[CoversNothing]` attributes per CLAUDE.md.
**Target Platform**: Composer-installed library; consumers run on PHP-FPM, built-in dev server, or CLI workers.
**Project Type**: Single PHP monorepo (62 first-party packages under `packages/*`).
**Performance Goals**: Zero per-request overhead for controllers that do not rely on the implicit-array shim (NFR-001). Deprecation log line emitted at most once per `(class::method)` registration per process (NFR-002).
**Constraints**: No `vendor/` edits (C-003). No Minoo edits (C-002). No re-implementation of #1390 (C-001). Layer discipline (C-007): dispatcher fix lives in API/L4 (`packages/ssr/`); cross-layer ripple only if non-upward. Composer policy (C-008) enforced by `bin/check-composer-policy`.
**Scale/Scope**: Spec maps roughly **184 affected methods across 37 controller files** in Minoo (the canonical stuck consumer). The framework's own controller surface is smaller; WP01 audit produces an exact count.

### Resolved technical decisions

- **Dispatcher subsystem location**: `packages/ssr/src/Http/AppController/` — confirmed by grepping the rejection message at `AppParameterBindingBuilder.php:149`. The relevant files are `AppParameterBindingBuilder.php`, `AppParameterBindingSpec.php`, `AppParameterKind.php`, `AppControllerArgumentResolver.php`, `AppControllerMethodInvoker.php`, `AppInvocationContext.php`, plus the attribute classes `Attribute/MapRoute.php` and `Attribute/MapQuery.php`. Test infrastructure already exists at `packages/ssr/tests/{Contract,Unit,Support,fixtures}/`.
- **Dispatcher package layer**: SSR (Layer 6 — Interfaces) per the layer architecture table in CLAUDE.md. Cross-layer downward dependencies (e.g., `Waaseyaa\Foundation\Log\LoggerInterface`) are allowed.
- **Deprecation channel**: `Waaseyaa\Foundation\Log\LoggerInterface` injected into `AppParameterBindingBuilder` (or a sibling collaborator). Constructor pattern: `?LoggerInterface $logger = null` defaulting to `NullLogger`, per CLAUDE.md "no psr/log" gotcha.
- **Audit artifact format**: A markdown table in the mission directory (`kitty-specs/.../artifacts/controller-shape-audit.md`). FR-009's optional CLI surface (`bin/waaseyaa controllers:audit`) stays deferred unless WP01 finds the log path insufficient for consumer tooling.
- **Verification plan format**: Self-contained markdown checklist at `kitty-specs/.../artifacts/minoo-resume-verification.md`, referenced from the mission README and CHANGELOG bullet.

### Items deliberately deferred to WP01 analysis

- Whether the deprecation signal lives on `AppParameterBindingBuilder`, on a new `DispatcherDeprecationCollector`, or on the kernel's existing logger plumbing.
- Exact log line schema (string template + structured context fields).
- Whether `MapRoute`/`MapQuery` need new optional constructor parameters to preserve historical implicit-array semantics, or whether the existing classes are sufficient.
- Final test fixture layout (do we add new fixture controllers under `packages/ssr/tests/fixtures/` or extend an existing one?).

These are *intentional* deferrals: WP01's deliverable is the answer to each.

## Charter Check

There is no `.kittify/charter/charter.md` in this repo at plan time. Charter Check is skipped. Project doctrine carried by:

- `CLAUDE.md` (project rules — layer discipline, composer policy, gotchas, conventions).
- `docs/specs/workflow.md` (mission/PR/issue traceability).
- `docs/specs/api-layer.md`, `docs/specs/middleware-pipeline.md`, related API/dispatcher specs.
- `feedback_changelog_release_workflow.md`, `feedback_pr_traceability_signals.md` (release mechanics).

Plan complies with all of the above.

## Project Structure

### Documentation (this feature)

```
kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/
├── plan.md                                  # this file
├── spec.md                                  # mission spec
├── meta.json                                # mission identity
├── research.md                              # Phase 0 output
├── data-model.md                            # Phase 1 output (contract entities)
├── quickstart.md                            # Phase 1 output (how to run/verify)
├── contracts/
│   └── dispatcher-deprecation-contract.md   # log-line + invariant contract
├── checklists/
│   └── requirements.md                      # quality checklist
├── artifacts/                               # WP01 deliverables (created in WP01)
│   ├── post-1390-dispatcher-contract.md
│   ├── controller-shape-audit.md
│   └── minoo-resume-verification.md
├── tasks/                                   # WP files (Phase 2 / /spec-kitty.tasks)
│   └── README.md
└── status.events.jsonl                      # mission event stream (mission-managed)
```

### Source Code (repository root)

The mission targets two existing layers:

```
packages/ssr/                              # API/L4 — dispatcher subsystem
├── src/
│   └── Http/
│       └── AppController/
│           ├── AppParameterBindingBuilder.php       # PRIMARY: deprecation emission point
│           ├── AppParameterBindingSpec.php
│           ├── AppParameterKind.php
│           ├── AppControllerArgumentResolver.php
│           ├── AppControllerMethodInvoker.php
│           ├── AppInvocationContext.php
│           └── Exception/
│       └── Attribute/
│           ├── MapRoute.php
│           └── MapQuery.php
└── tests/
    ├── Contract/                                    # NEW contract tests land here
    ├── Unit/
    │   └── Http/
    │       └── AppController/                       # binding builder unit tests
    ├── Support/                                     # test helpers
    └── fixtures/                                    # fixture controllers

packages/foundation/                       # L0 — logger interface (read-only consumer)
└── src/
    └── Log/
        └── LoggerInterface.php

docs/specs/                                # cold-spec home
└── api-layer.md                           # update with dispatcher contract reference

CHANGELOG.md                               # [Unreleased] bullet
```

**Structure Decision**: Single PHP monorepo, all changes confined to `packages/ssr/` plus a CHANGELOG bullet and an updated `docs/specs/api-layer.md` cross-link. WP01 produces the artifact files inside the mission directory. WP02+ touches `packages/ssr/src/`, `packages/ssr/tests/`, `docs/specs/`, and `CHANGELOG.md`.

## Phase Plan (mapped to WP intent — WPs are materialized in `/spec-kitty.tasks`)

### Phase A — Analysis (WP01, executes immediately on `main`)

- Read the current dispatcher source at `packages/ssr/src/Http/AppController/` and the rejection point at `AppParameterBindingBuilder.php:149`.
- Read the in-flight #1390 PR (if present on the framework remote) or the issue to reconcile assumptions in spec §7.
- Produce three artifacts in `kitty-specs/.../artifacts/`:
  1. `post-1390-dispatcher-contract.md` — concrete contract spec: implicit-array→`#[MapRoute]`/`#[MapQuery]` mapping rules, edge cases from spec §3, deprecation log schema.
  2. `controller-shape-audit.md` — table of every framework-shipped controller method shape with classification (already-attribute, relies-on-shim, neither).
  3. `minoo-resume-verification.md` — self-contained checklist (composer commands, test commands, smoke-route commands, expected pass/fail signals).
- WP01 makes **no source edits**. Output is markdown only.
- Acceptance: artifacts merged to `main` via Spec-Kitty PR; reviewer confirms checklist runnable by an external operator without escalation.

### Phase B — Implementation (WP02..WPn, gated until #1390 merges)

WPs in this phase are dispatched only after framework#1390 is confirmed merged on `main`. The gate is a hard precondition declared in each WP's preamble; the agent must verify before starting. Expected WP shapes (final names assigned during `/spec-kitty.tasks`):

- **WP02 — Deprecation emission plumbing**. Wire `LoggerInterface` into `AppParameterBindingBuilder` (or sibling). Emit one structured log line per `(class::method, parameter, missing-attribute)` registration; dedup per process; satisfies FR-002, NFR-001, NFR-002.
- **WP03 — Contract & unit tests**. Add tests at `packages/ssr/tests/Contract/` and `packages/ssr/tests/Unit/Http/AppController/` covering: implicit-array shim path emits expected log line and resolves correctly (FR-003); attribute-annotated path resolves correctly with zero noise (FR-004); `array $query`-only path (FR-005); edge cases from spec §3 (mixed typed params, query-only, non-route arrays).
- **WP04 — Spec & docs**. Update `docs/specs/api-layer.md` with the dispatcher contract reference (FR-001). Cross-link from `kitty-specs/.../artifacts/post-1390-dispatcher-contract.md`. Promote any WP01 audit findings into the spec where canonical.
- **WP05 — CHANGELOG & alpha-cut prep**. Add `[Unreleased]` bullet referencing #1390, #1388 per `feedback_changelog_release_workflow.md` (FR-007). Verify `bin/check-composer-policy`, `bin/check-package-layers`, `composer phpstan`, full PHPUnit clean. Filed GitHub issue (per `docs/specs/workflow.md`) closed; release-notes edit ready per `feedback_pr_traceability_signals.md`.

`/spec-kitty.tasks` may split or fold these based on dependency analysis; this is the planner's intent.

## Phase 0: Research

Research findings are recorded in `research.md` (Phase 0 output). Topics:

1. Where the dispatcher rejection lives and which collaborators participate (resolved).
2. Whether `MapRoute`/`MapQuery` already accept the parameters needed to express the implicit-array semantics (deferred to WP01 — needs source review beyond the rejection point).
3. The shape of `LoggerInterface` and how existing dispatcher code obtains it (deferred to WP01).
4. The framework controller inventory: how many internal controllers ship with the framework, and which take `array $params`/`array $query`.
5. Existing contract-test pattern in `packages/ssr/tests/Contract/`.

See [research.md](./research.md).

## Phase 1: Design & Contracts

- **`data-model.md`** — Records the conceptual entities of the dispatcher contract: deprecation event, parameter kind, attribute equivalence map, audit row schema. No persistent storage.
- **`contracts/dispatcher-deprecation-contract.md`** — Specifies the deprecation emission contract: trigger conditions, log channel, line format, dedup invariant, parser-friendly schema.
- **`quickstart.md`** — How a maintainer or reviewer runs the mission's verification commands locally and against `main`.

## Complexity Tracking

No charter violations to justify (no charter exists). The mission is intentionally narrow:

| Aspect                  | Choice                                                            | Why simpler alternative was not chosen                                              |
|-------------------------|-------------------------------------------------------------------|--------------------------------------------------------------------------------------|
| Two-phase WP gating      | WP01 immediate, WP02+ gated on #1390 merging.                     | Single all-at-once WP would block analysis on upstream timing; gating de-risks. |
| Markdown audit artifact  | One-shot table in mission directory.                              | A new CLI command (FR-009) is heavier; deferred unless WP01 proves it necessary. |
| Logger via `?LoggerInterface = null` + `NullLogger` | Matches existing project convention. | A new dispatcher-specific event would re-invent CLAUDE.md's pre-existing pattern. |

## Branch Contract (restated)

- Current branch: `main`
- Planning/base branch: `main`
- Final merge target: `main`
- `branch_matches_target`: true

## Next command

`/spec-kitty.tasks` — generate the work-package outline and dependency graph from this plan.
