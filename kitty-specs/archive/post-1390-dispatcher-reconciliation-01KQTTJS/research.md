# Phase 0 Research: Post-#1390 Dispatcher Reconciliation

**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`
**Date**: 2026-05-05

## Decision 1: Dispatcher subsystem location

- **Decision**: The controller-dispatcher rejection point and surrounding code live in `packages/ssr/src/Http/AppController/`.
- **Rationale**: Direct grep against `main` (`rg 'array parameters require' packages/`) returns a single match at `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php:149`. Sibling files in the same directory comprise the dispatcher's invocation pipeline (`AppParameterBindingBuilder`, `AppParameterBindingSpec`, `AppParameterKind`, `AppControllerArgumentResolver`, `AppControllerMethodInvoker`, `AppInvocationContext`, plus the `Attribute/` directory with `MapRoute.php` and `MapQuery.php`).
- **Alternatives considered**: We initially expected the dispatcher to live in `packages/api/` or `packages/routing/` based on layer conventions. Both packages exist but neither contains the rejection message, so they are not the correct target. The orchestration table in `CLAUDE.md` does map `packages/api/*` and `packages/routing/*` to the `waaseyaa:api-layer` skill — this skill's surface includes the dispatcher conceptually but the implementation lives under `ssr`. Update worth flagging in WP04.

## Decision 2: Test infrastructure location

- **Decision**: New contract tests for the dispatcher land at `packages/ssr/tests/Contract/`. Unit tests for `AppParameterBindingBuilder` land at `packages/ssr/tests/Unit/Http/AppController/`. Test fixtures (fixture controllers) extend or sit alongside `packages/ssr/tests/fixtures/`.
- **Rationale**: `ls packages/ssr/tests/` confirms the directories exist; CLAUDE.md instructs that contract tests are in `packages/*/tests/Contract/` with `#[CoversNothing]`, and the test conventions section establishes PHPUnit 10.5 attributes and `DBALDatabase::createSqlite()` for in-memory persistence (not expected to be needed here).
- **Alternatives considered**: Adding a top-level `tests/Integration/PhaseN/` directory was considered (per CLAUDE.md "Integration tests in `tests/Integration/PhaseN/`") but reserved for cross-package phases. Single-package contract coverage stays inside the package's own test directory.

## Decision 3: Deprecation logging channel

- **Decision**: `Waaseyaa\Foundation\Log\LoggerInterface` injected into `AppParameterBindingBuilder` (or a focused collaborator) via constructor pattern `?LoggerInterface $logger = null`, defaulting to `NullLogger`.
- **Rationale**: CLAUDE.md gotcha "No psr/log: Project does not use `psr/log`. Use `Waaseyaa\Foundation\Log\LoggerInterface` for structured logging. Accept `?LoggerInterface $logger = null` in constructors and default to `NullLogger`. Reserve `error_log()` only for last-resort fallbacks inside the logging infrastructure itself."
- **Alternatives considered**: A dispatcher-specific deprecation event over `EventDispatcherInterface` was considered (mirrors how kernel boot dispatches events). Rejected for now: it adds a new public surface that consumers must subscribe to, while a structured log line is universally consumable and matches the precedent set elsewhere in the framework. WP01 may revisit if it discovers a stronger reason during analysis.

## Decision 4: Dispatcher package layer

- **Decision**: `packages/ssr/` is Layer 6 (Interfaces) per the layer architecture table in CLAUDE.md. Downward dependencies (`Waaseyaa\Foundation\Log\LoggerInterface` from L0) are allowed and required.
- **Rationale**: Layer table in CLAUDE.md: "6 | Interfaces | cli, admin-surface, graphql, mcp, **ssr**, genealogy, telescope, deployer, inertia, debug". Foundation is L0; SSR is L6; downward import is layer-compliant. `bin/check-package-layers` will validate.
- **Alternatives considered**: None — layer assignment is a fixed project rule.

## Decision 5: Audit artifact format

- **Decision**: WP01 writes a markdown table to `kitty-specs/.../artifacts/controller-shape-audit.md` as a one-shot document. FR-009's optional CLI surface (`bin/waaseyaa controllers:audit`) is deferred unless WP01's analysis concludes the log path is insufficient for consumer tooling.
- **Rationale**: A markdown table is sufficient for the framework's own controller inventory (a small, slow-changing surface). Consumers ingest the live deprecation log when running their own boot, which is more useful for migration debt than a one-shot framework-internal audit.
- **Alternatives considered**: JSON output for tooling, embedded in the framework. Rejected as premature; the live log is already structured and parser-friendly.

## Decision 6: Verification plan format

- **Decision**: Self-contained markdown checklist at `kitty-specs/.../artifacts/minoo-resume-verification.md`. Includes exact `composer require` command, test commands, smoke commands, and pass/fail signals for each step.
- **Rationale**: NFR-004 requires the plan to be runnable by "an operator with no framework knowledge beyond the README". Markdown with copy-pasteable commands satisfies this.
- **Alternatives considered**: A scripted runner (`bin/verify-minoo-resume`) was considered; rejected because it would live in framework while targeting a specific consumer, violating the "framework-scoped" mission boundary.

## Open items deferred to WP01

The following questions intentionally stay open at plan time. WP01's analysis answers them and produces written deltas if any spec assumption changes:

1. **#1390 landed shape** — does the upstream PR match spec §7's assumption that the shim defaults unannotated `array $params` to `#[MapRoute]` and `array $query` to `#[MapQuery]` semantics? WP01 confirms or revises FR-002/FR-010.
2. **`MapRoute`/`MapQuery` constructor surface** — do these classes already accept the optional parameters needed to express implicit semantics, or do they need a new `legacyImplicitShim: true` flag? WP01 reads the source and decides.
3. **Internal collaborator surface** — should the deprecation logger live on `AppParameterBindingBuilder` directly, on a sibling `DispatcherDeprecationCollector`, or hooked into existing kernel logger plumbing? WP01 reviews the surrounding code and proposes.
4. **Log line schema** — exact string template and structured context fields. WP01 publishes the chosen schema in `contracts/dispatcher-deprecation-contract.md` (created here as a stub).
5. **Framework controller inventory size** — count of framework-shipped controllers by shape category. WP01's `controller-shape-audit.md` answers.

## References

- `framework#1390` — controller dispatcher: array params require `#[MapRoute]`/`#[MapQuery]` without compatibility shim (issue body captured in mission spec).
- `framework#1388` — companion regression closed in alpha.172 (referenced for CHANGELOG cross-link).
- `CLAUDE.md` — project conventions, layer architecture, gotchas, composer policy.
- `docs/specs/api-layer.md` — current dispatcher spec; WP04 updates with new contract reference.
- `docs/specs/workflow.md` — Spec-Kitty + GitHub workflow, milestone rules, PR traceability.
- `feedback_changelog_release_workflow.md` — `[Unreleased]` bullet rule for releases.
- `feedback_pr_traceability_signals.md` — post-merge issue-close + release-notes-edit rule.
