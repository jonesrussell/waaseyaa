# Tasks: Post-#1390 Dispatcher Reconciliation

**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`
**Date**: 2026-05-05
**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Contract**: [contracts/dispatcher-deprecation-contract.md](./contracts/dispatcher-deprecation-contract.md)
**Tracking issue**: [framework#1391](https://github.com/waaseyaa/framework/issues/1391) · **Upstream dep**: [framework#1390](https://github.com/waaseyaa/framework/issues/1390)

## Branch contract

- Planning/base branch: `main`
- Final merge target: `main`
- Each WP gets its own lane worktree at execution time (allocated from `lanes.json` after `finalize-tasks`).

## Phase posture

- **Phase A** — WP01 only. Markdown-only analysis. **Runs immediately on `main`.** No dependency on framework#1390 landing.
- **Phase B** — WP02..WP04. Source / test / docs work. **Each WP carries a hard precondition that framework#1390 must be merged on `main` before it starts.** The agent verifies the gate before opening files.

## Subtask Index

| ID    | Description                                                                                          | WP   | Parallel |
|-------|------------------------------------------------------------------------------------------------------|------|----------|
| T001  | Audit current dispatcher source at `packages/ssr/src/Http/AppController/`                            | WP01 |          | [D] |
| T002  | Reconcile spec assumptions against the merged shape of framework#1390 (or current main if not yet merged) | WP01 | [D] |
| T003  | Produce `artifacts/post-1390-dispatcher-contract.md`                                                 | WP01 |          | [D] |
| T004  | Produce `artifacts/controller-shape-audit.md`                                                        | WP01 | [D] |
| T005  | Produce `artifacts/minoo-resume-verification.md`                                                     | WP01 | [D] |
| T006  | File follow-up issues for any adjacent invariants surfaced (per spec C-005)                          | WP01 | [D] |
| T007  | Inject `LoggerInterface` into the dispatcher binding pipeline                                        | WP02 |          | [D] |
| T008  | Implement deprecation emission with `(class::method::param)` dedup                                   | WP02 |          | [D] |
| T009  | Wire DI through `SsrServiceProvider` so production gets a real logger                                | WP02 |          | [D] |
| T010  | Add fixture controllers under `packages/ssr/tests/fixtures/`                                         | WP03 |          | [D] |
| T011  | Add unit tests for `AppParameterBindingBuilder` deprecation path                                     | WP03 | [D] |
| T012  | Add the seven contract tests defined in `contracts/dispatcher-deprecation-contract.md`               | WP03 |          | [D] |
| T013  | Update `docs/specs/api-layer.md` with cross-link to dispatcher-deprecation contract                  | WP04 | [D] |
| T014  | (Optional) Update CLAUDE.md orchestration table — note that the dispatcher implementation lives in `packages/ssr/` | WP04 | [D] |
| T015  | Add `[Unreleased]` CHANGELOG bullet referencing #1390 (and #1388)                                    | WP04 | [D] |
| T016  | Run all gates (cs-check, phpstan, check-composer-policy, check-package-layers, full PHPUnit) and fix blockers | WP04 |          | [D] |

The `[P]` marker in this index is reference-only. Per-WP tracking checkboxes appear under each WP heading below.

## Work Packages

### WP01 — Analysis & Artifacts (Phase A, immediate)

**Goal**: Produce three markdown artifacts that ratify the post-#1390 dispatcher contract, audit framework controller shapes, and hand Minoo a self-contained resume checklist. No source edits.

**Priority**: P0 (immediate). Does not require #1390 to land.
**Independent test**: A reviewer can read all three artifacts and reproduce the verification checklist without escalating to framework maintainers (NFR-004).
**Estimated prompt size**: ~450 lines.
**Execution mode**: `planning_artifact`.
**Owned files**: `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/**`.

Tracking:

- [x] T001 Audit current dispatcher source at `packages/ssr/src/Http/AppController/` (WP01)
- [x] T002 Reconcile spec assumptions against the merged shape of framework#1390 (WP01)
- [x] T003 Produce `artifacts/post-1390-dispatcher-contract.md` (WP01)
- [x] T004 Produce `artifacts/controller-shape-audit.md` (WP01)
- [x] T005 Produce `artifacts/minoo-resume-verification.md` (WP01)
- [x] T006 File follow-up issues for adjacent invariants surfaced (WP01)

Implementation sketch: read dispatcher source → check #1390 status → write contract → write audit → write resume plan → file follow-ups → commit.

Dependencies: none.
Risks: #1390 PR not yet merged at WP01 execution time — handled by writing contract against current `main` and flagging assumptions for re-confirmation when #1390 lands.
Prompt: [tasks/WP01-analysis-and-artifacts.md](./tasks/WP01-analysis-and-artifacts.md)

### WP02 — Deprecation emission plumbing (Phase B, gated)

**Goal**: Inject `LoggerInterface` into the dispatcher binding pipeline and emit one structured deprecation log line per `(class::method::parameter)` registration that relies on the implicit-array shim.

**Priority**: P1.
**Independent test**: Unit test asserts that registering a fixture controller method with `array $params` (no attribute) emits exactly one log entry with the schema in `contracts/dispatcher-deprecation-contract.md`.
**Estimated prompt size**: ~280 lines.
**Execution mode**: `code_change`.
**Owned files**: `packages/ssr/src/Http/AppController/**`, `packages/ssr/src/SsrServiceProvider.php`.
**Authoritative surface**: `packages/ssr/src/Http/AppController/`.

Tracking:

- [x] T007 Inject `LoggerInterface` into the dispatcher binding pipeline (WP02)
- [x] T008 Implement deprecation emission with `(class::method::param)` dedup (WP02)
- [x] T009 Wire DI through `SsrServiceProvider` (WP02)

Implementation sketch: identify the right collaborator (likely `AppParameterBindingBuilder`) → constructor-inject `?LoggerInterface $logger = null` → dedup map keyed on `(class::method::param)` → emit `notice` per spec → wire in `SsrServiceProvider`.

Dependencies: WP01 (consumes the contract from WP01's artifact). Hard precondition: framework#1390 merged on `main`.
Risks: dedup scope locked at per-request per contract §7 (NFR-002 reinterpreted accordingly); WP02 must not introduce a longer-lived collaborator without an explicit contract revision.
Prompt: [tasks/WP02-deprecation-emission-plumbing.md](./tasks/WP02-deprecation-emission-plumbing.md)

### WP03 — Test coverage (Phase B, gated)

**Goal**: Add fixture controllers, unit tests, and the seven contract tests defined in `contracts/dispatcher-deprecation-contract.md`. Verify legacy and modern signatures both work and the deprecation invariant holds.

**Priority**: P1.
**Independent test**: `./vendor/bin/phpunit packages/ssr/tests/Contract/` passes; the seven contract tests run green.
**Estimated prompt size**: ~350 lines.
**Execution mode**: `code_change`.
**Owned files**: `packages/ssr/tests/Contract/**`, `packages/ssr/tests/Unit/Http/AppController/**`, `packages/ssr/tests/fixtures/**`.
**Authoritative surface**: `packages/ssr/tests/`.

Tracking:

- [x] T010 Add fixture controllers under `packages/ssr/tests/fixtures/` (WP03)
- [x] T011 Add unit tests for `AppParameterBindingBuilder` deprecation path (WP03)
- [x] T012 Add the seven contract tests defined in the contract document (WP03)

Implementation sketch: create five fixture controllers (LegacyArrayParams, Annotated, Mixed, OnlyQuery, UnboundArray) → unit test the binding builder's classification logic → contract tests cover the seven scenarios in §"Test contract".

Dependencies: WP02 (the deprecation logger must be wired before the tests can assert it). Hard precondition: framework#1390 merged on `main`.
Risks: PHPUnit 10.5 mocking limits — use real anonymous classes / null logger fakes; do not pass `-v`.
Prompt: [tasks/WP03-test-coverage.md](./tasks/WP03-test-coverage.md)

### WP04 — Spec, docs, CHANGELOG, alpha-cut gates (Phase B, gated)

**Goal**: Promote the contract into `docs/specs/api-layer.md`, optionally clarify CLAUDE.md's orchestration table, add the CHANGELOG `[Unreleased]` bullet, and run all gates green. Mission completes here.

**Priority**: P1 (release-blocking).
**Independent test**: All gates green (`composer cs-check`, `composer phpstan`, `bin/check-composer-policy`, `bin/check-package-layers`, `./vendor/bin/phpunit`); CHANGELOG `[Unreleased]` carries the bullet referencing #1390 (and #1388); spec cross-link present in `docs/specs/api-layer.md`.
**Estimated prompt size**: ~280 lines.
**Execution mode**: `code_change`.
**Owned files**: `docs/specs/api-layer.md`, `CHANGELOG.md`, `CLAUDE.md`.
**Authoritative surface**: `docs/specs/`.

Tracking:

- [x] T013 Update `docs/specs/api-layer.md` with cross-link to dispatcher-deprecation contract (WP04)
- [x] T014 (Optional) Note that dispatcher implementation lives in `packages/ssr/` in CLAUDE.md orchestration table (WP04)
- [x] T015 Add `[Unreleased]` CHANGELOG bullet referencing #1390 (and #1388) (WP04)
- [x] T016 Run all gates and fix blockers (WP04)

Implementation sketch: edit `docs/specs/api-layer.md` → optionally edit CLAUDE.md → edit CHANGELOG → run gates → land PR → close tracking issue → edit GitHub Release notes per `feedback_pr_traceability_signals.md`.

Dependencies: WP02, WP03 (both must be merged so the docs accurately describe shipped behavior). Hard precondition: framework#1390 merged on `main`.
Risks: CHANGELOG conflicts with concurrent release-cuts; merge order matters — coordinate with maintainers.
Prompt: [tasks/WP04-docs-changelog-and-gates.md](./tasks/WP04-docs-changelog-and-gates.md)

## MVP scope

WP01 alone is the analysis MVP — it unblocks Minoo's resume-planning even if WP02..WP04 are deferred to a later cut. Once #1390 lands, WP02 + WP03 form the implementation MVP; WP04 is the release plumbing.

## Parallelization

- WP01 internal: T002 / T004 / T005 / T006 are `[P]` (independent reads/writes within the artifacts directory).
- WP04 internal: T013 / T014 / T015 are `[P]` (different files).
- Across WPs: WP02 → WP03 → WP04 form a strict chain (each consumes the previous). No cross-WP parallelization.

## Next command

`spec-kitty agent mission finalize-tasks --mission post-1390-dispatcher-reconciliation-01KQTTJS --json`
