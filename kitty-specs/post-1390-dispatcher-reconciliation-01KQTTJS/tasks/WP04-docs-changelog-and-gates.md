---
work_package_id: WP04
title: Docs, CHANGELOG, and Alpha-Cut Gates
dependencies:
- WP02
- WP03
requirement_refs:
- C-006
- FR-007
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T013
- T014
- T015
- T016
agent: "claude:opus-4-7:implementer:implementer"
shell_pid: "93383"
history:
- '2026-05-05: created'
authoritative_surface: docs/specs/
execution_mode: code_change
mission_id: 01KQTTJS73GVXHFPY5W8E8K3DX
mission_slug: post-1390-dispatcher-reconciliation-01KQTTJS
owned_files:
- docs/specs/api-layer.md
- CHANGELOG.md
- CLAUDE.md
tags: []
---

# WP04 — Docs, CHANGELOG, and Alpha-Cut Gates

## Objective

Promote the dispatcher contract into the canonical spec at `docs/specs/api-layer.md`, optionally clarify CLAUDE.md's orchestration table, add the `[Unreleased]` CHANGELOG bullet that `release-cut.yml` will promote at tag time, and run all framework gates green. Mission completes here.

## ⚠️ Hard precondition

framework#1390 merged on `main`, AND WP02 + WP03 both merged on `main`. If any of these fail, **stop**.

## Context

This WP is release-mechanics-shaped. It does not introduce new behavior; it makes the behavior already shipped by WP02/WP03 visible in the canonical surfaces (spec, CHANGELOG, optionally CLAUDE.md). After this WP merges, the next alpha tag will carry the dispatcher reconciliation as a release-notes line.

Read first:

- `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/post-1390-dispatcher-contract.md` (canonical contract — link this from the spec).
- `docs/specs/api-layer.md` (current spec — find the right place for the cross-link).
- `CHANGELOG.md` (current `[Unreleased]` section if any).
- `CLAUDE.md` (orchestration table around the API layer skill).
- `feedback_changelog_release_workflow.md` rule (in `MEMORY.md`): bullets go in `[Unreleased]`, `release-cut.yml` promotes them.
- `feedback_pr_traceability_signals.md` rule: post-merge, the tracking issue must be closed and the GitHub Release notes edited manually.

## Branch Strategy

- Planning/base branch: `main`.
- Final merge target: `main`.
- Lane worktree per `lanes.json`.

## Subtasks

### T013 — Update `docs/specs/api-layer.md`

**Purpose**: Make the dispatcher contract discoverable from the canonical API-layer spec.

**Steps**:

1. Open `docs/specs/api-layer.md`.
2. Find the section that documents controller-method binding / `MapRoute` / `MapQuery` / argument resolution. If no such section exists, add a new heading `## Controller parameter binding`.
3. In that section, add a paragraph that:
   - States the post-#1390 contract: unannotated `array $params` is treated as `#[MapRoute]`, unannotated `array $query` as `#[MapQuery]`, both with a deprecation notice.
   - Cross-links to the mission contract artifact: `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/post-1390-dispatcher-contract.md`.
   - Cross-links to the deprecation log schema in the same artifact.
4. Add a one-line entry to the spec's "Recent contract changes" table (or equivalent) referencing #1390 and this mission slug.
5. Keep the diff minimal — do not refactor unrelated sections.

**Files touched**:

- `docs/specs/api-layer.md`.

**Validation**:

- `tools/drift-detector.sh` (if present in the repo) finds no drift for `api-layer.md`.
- The cross-linked path exists.

### T014 — (Optional) CLAUDE.md orchestration table clarification

**Purpose**: The orchestration table in `CLAUDE.md` maps `packages/api/*` and `packages/routing/*` to the `waaseyaa:api-layer` skill. The actual dispatcher implementation, however, lives under `packages/ssr/src/Http/AppController/`. This subtask documents that fact so future agents don't lose 10 minutes grepping.

**Steps**:

1. Open `CLAUDE.md`. Find the orchestration table.
2. Add `packages/ssr/src/Http/AppController/*` to the row for `waaseyaa:api-layer` (or to the `waaseyaa:infrastructure` row for `packages/ssr/*` — whichever fits the existing convention better; the api-layer row is preferred since this is dispatcher logic).
3. Or, more conservatively, add a one-line note after the orchestration table that points dispatcher-related work to `packages/ssr/src/Http/AppController/`.
4. **Skip this subtask if** the orchestration table already documents the location adequately. CLAUDE.md edits are sensitive — make the smallest possible change.

**Files touched**:

- `CLAUDE.md` (zero or one line of change).

**Validation**:

- The change does not introduce a contradiction with the layer architecture table elsewhere in CLAUDE.md.

### T015 — Add `[Unreleased]` CHANGELOG bullet

**Purpose**: `release-cut.yml` promotes `[Unreleased]` to the next version heading at tag time. The bullet must be in place before that workflow runs (per `feedback_changelog_release_workflow.md`).

**Steps**:

1. Open `CHANGELOG.md`. Confirm there is a `## [Unreleased]` section with the standard Keep-a-Changelog subsections (`Added`, `Changed`, `Fixed`, etc.).
2. Add a bullet under the most appropriate subsection (likely `Changed` or `Fixed`):
   ```markdown
   ### Changed
   - **Controller dispatcher** restored compatibility with the historical implicit-array signature (`array $params`, `array $query`) via a deprecation-emitting shim. Method registrations relying on the shim now emit a structured `dispatcher.deprecation` notice naming the controller, method, parameter, and recommended attribute (`#[MapRoute]` or `#[MapQuery]`). Closes #1390 follow-up; companion to #1388. (#1390)
   ```
3. Do not touch any other line in `CHANGELOG.md`. In particular, do not move existing entries; do not promote `[Unreleased]` to a version heading — that is the job of `release-cut.yml`.

**Files touched**:

- `CHANGELOG.md`.

**Validation**:

- Diff is purely additive to `[Unreleased]`.
- `composer changelog:check` (or whatever the project's changelog linter is) passes; if no such linter exists, eyeball Keep-a-Changelog conformance.

### T016 — Run all gates

**Purpose**: Ensure the merged mission state is releasable.

**Steps**:

1. Run from repo root (single command per gate, no parallel chains):

   ```bash
   composer cs-check
   composer phpstan
   bin/check-composer-policy
   bin/check-package-layers
   ./vendor/bin/phpunit
   ```

2. If any exits non-zero, fix the underlying issue inside this WP's `owned_files` if possible. If the failure is in code outside `owned_files`, **stop** and surface the issue — do not edit foreign code as a side effect. Open a follow-up issue per spec C-005 if needed.
3. Once all gates are green, double-check:
   - CHANGELOG `[Unreleased]` carries the bullet from T015.
   - `docs/specs/api-layer.md` cross-link exists (T013).
   - The seven contract tests in `packages/ssr/tests/Contract/DispatcherDeprecationContractTest.php` are present and green.
   - The mission status reports all WPs in `done` lane:
     ```bash
     spec-kitty agent tasks status --mission post-1390-dispatcher-reconciliation-01KQTTJS --json
     ```

4. Post-merge follow-ups (per `feedback_pr_traceability_signals.md`):
   - Close the mission's GitHub tracking issue: `gh issue close 1391 --repo waaseyaa/framework`.
   - Edit the GitHub Release notes for the alpha that ships this WP to surface the dispatcher reconciliation entry — neither happens automatically.

**Files touched**: none beyond the prior subtasks. This subtask is verification + orchestration only.

**Validation**:

- All five gates green.
- Mission status all-green.
- Tracking issue closed; release notes edited manually.

## Test strategy

This WP is gates-driven. The tests already live in WP03; this WP runs them and the static checkers and confirms green.

## Definition of Done

- [ ] T013 cross-link landed in `docs/specs/api-layer.md`.
- [ ] T014 CLAUDE.md edit applied or explicitly skipped (with reason).
- [ ] T015 `[Unreleased]` CHANGELOG bullet present.
- [ ] All five gates green.
- [ ] Mission status reports all WPs `done`.
- [ ] Tracking issue **#1391** closed; release notes edited (manual, per `feedback_pr_traceability_signals.md`).
- [ ] No edits outside `owned_files`.
- [ ] `tasks.md` rows T013..T016 marked complete.
- [ ] WP04 PR references mission slug, tracking issue **#1391**, and upstream **#1390** per `docs/specs/workflow.md`.

## Risks

- **CHANGELOG conflicts with concurrent release-cuts** — coordinate with maintainers; if a release-cut is mid-flight, rebase the WP04 branch and re-run gates.
- **Phpstan baseline drift** — if WP02/WP03 introduced any baseline noise, fix in this WP as a final sweep; do not push noise into `phpstan-baseline.neon` without a rationale.
- **CLAUDE.md edit cascades** — keep T014's diff minimal; large CLAUDE.md edits ripple into every future session.

## Reviewer guidance

- Diff scope confined to `docs/specs/api-layer.md`, `CHANGELOG.md`, optionally `CLAUDE.md`.
- Confirm the cross-link target file exists (`kitty-specs/.../artifacts/post-1390-dispatcher-contract.md`).
- Confirm the CHANGELOG bullet matches `feedback_changelog_release_workflow.md`'s pattern (single bullet, in `[Unreleased]`, references the GitHub issue).
- Run the five gates yourself before approving.

## Implementation command

```bash
spec-kitty agent action implement WP04 --agent <your-agent-name> --mission post-1390-dispatcher-reconciliation-01KQTTJS
```

## Activity Log

- 2026-05-05T16:06:45Z – claude:opus-4-7:implementer:implementer – shell_pid=91087 – Started implementation via action command
- 2026-05-05T16:10:48Z – claude:opus-4-7:implementer:implementer – shell_pid=91087 – Ready for review: T013 spec cross-link landed (docs/specs/api-layer.md), T014 skipped (packages/ssr/* row in CLAUDE.md orchestration table already covers AppController/*), T015 skipped (existing [Unreleased] #1390 bullet from 454d00f77 already accurately describes the post-reconciliation deprecation-shim contract — controller/parameter/recommended_attribute/per-request dedup all present), T016 gates: cs-check/phpstan/check-composer-policy/check-package-layers all clean (exit 0), drift-detector clean, full PHPUnit green except 2 verified-pre-existing QueueIntegrationTest state-pollution failures (workerRunProcessesMultipleJobsThroughDbalTransport, workerRunMixesSuccessAndFailure) orthogonal to mission. Commit 683e47b27.
- 2026-05-05T16:11:37Z – claude:opus-4-7:reviewer:reviewer – shell_pid=92328 – Started review via action command
- 2026-05-05T16:16:26Z – claude:opus-4-7:reviewer:reviewer – shell_pid=92328 – Moved to planned
- 2026-05-05T16:17:21Z – claude:opus-4-7:implementer:implementer – shell_pid=93383 – Started implementation via action command
