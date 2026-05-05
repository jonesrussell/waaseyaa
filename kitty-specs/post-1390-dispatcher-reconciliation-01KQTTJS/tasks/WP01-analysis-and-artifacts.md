---
work_package_id: WP01
title: Analysis & Artifacts
dependencies: []
requirement_refs:
- FR-001
- FR-006
- FR-008
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Each WP runs in its own lane worktree; planning base is main; final merge target is main.
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
history:
- '2026-05-05: created'
authoritative_surface: kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/
execution_mode: planning_artifact
mission_id: 01KQTTJS73GVXHFPY5W8E8K3DX
mission_slug: post-1390-dispatcher-reconciliation-01KQTTJS
owned_files:
- kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/**
tags: []
---

# WP01 — Analysis & Artifacts

## Objective

Produce three markdown artifacts that ratify the post-#1390 controller-dispatcher contract, audit framework controller shapes, and hand Minoo a self-contained Resume Verification Plan. **No source edits.**

This WP runs **immediately on `main`** — it does not require framework#1390 to be merged first. If #1390 has not yet merged at WP01 execution time, the artifacts are written against the current `main` (alpha.172) and the dispatcher-contract artifact explicitly flags assumptions that need re-confirmation when #1390 lands.

## Context

This mission is a follow-up to framework#1390. #1390 is the upstream dispatcher fix that re-introduces a compatibility shim for unannotated `array $params` / `array $query` controller parameters. This WP plans the framework-side reconciliation — what changes do **we** need once the shim lands? The mission's spec, plan, research, data-model, and contract are already on disk. WP01 reads them and produces three deliverables that drive WP02–WP04.

Read first:

- `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/spec.md` — full mission spec.
- `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/plan.md` — implementation plan.
- `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/research.md` — Phase 0 research.
- `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/data-model.md` — entity vocabulary.
- `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/contracts/dispatcher-deprecation-contract.md` — deprecation emission contract (this WP confirms or revises).
- `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php` — current rejection point at line 149.
- Framework issue #1390 — verify state via `gh issue view 1390 --repo waaseyaa/framework`.

## Branch Strategy

- Planning/base branch: `main`.
- Final merge target: `main`.
- This WP runs in its lane worktree allocated by `lanes.json` after `finalize-tasks`. Stay inside that worktree for the duration of the WP.

## Subtasks

### T001 — Audit current dispatcher source

**Purpose**: Build a complete mental model of the dispatcher pipeline as it stands today so the contract artifact (T003) is grounded in reality, not in the issue description alone.

**Steps**:

1. Read every PHP file in `packages/ssr/src/Http/AppController/`:
   - `AppParameterBindingBuilder.php` (the file holding the rejection at line 149)
   - `AppParameterBindingSpec.php`
   - `AppParameterKind.php`
   - `AppControllerArgumentResolver.php`
   - `AppControllerMethodInvoker.php`
   - `AppInvocationContext.php`
   - `Exception/` subdirectory contents
2. Read `packages/ssr/src/Attribute/MapRoute.php` and `packages/ssr/src/Attribute/MapQuery.php`.
3. Read `packages/ssr/src/SsrServiceProvider.php` to see how the dispatcher's collaborators are wired today and where a `LoggerInterface` injection would land in WP02.
4. Skim the existing tests at `packages/ssr/tests/Unit/Http/AppController/` and `packages/ssr/tests/Contract/` to understand the established test patterns. Note the fixture conventions in `packages/ssr/tests/fixtures/` so WP03's fixture controllers will fit the existing scheme.

**Deliverable**: an internal mental model used by T003. No file output for this subtask.

**Validation**:

- You can describe in one paragraph what triggers the rejection at `AppParameterBindingBuilder.php:149`.
- You know whether `MapRoute` / `MapQuery` already accept the parameters needed to express implicit-array semantics, or whether a new flag is required.
- You know the constructor surface of `AppParameterBindingBuilder` (which arguments must be retained when adding `?LoggerInterface $logger = null`).

### T002 — Reconcile spec assumptions against #1390's landed shape

**Purpose**: Prevent the rest of this mission from drifting away from upstream reality. Spec §7 lists the assumed shape of #1390's fix; this subtask verifies those assumptions or produces a written delta.

**Steps**:

1. Run `gh issue view 1390 --repo waaseyaa/framework` and capture: state (`open` / `closed`), the fix PR (if linked), latest comment, label set.
2. If a PR is linked, run `gh pr view <PR#> --repo waaseyaa/framework` and read its description and diff. Pay particular attention to:
   - Where the deprecation signal is emitted (logger? event? notice?).
   - The exact attribute-equivalence rules used by the shim.
   - Whether `MapRoute` / `MapQuery` were modified.
   - Whether the dispatcher pipeline gained any new collaborator class.
3. If #1390 has not yet merged, document the current state and write the artifacts against the assumed shape from spec §7, marking each assumption explicitly so a later WP01 amendment can reconcile.
4. Capture a short delta document inline in `artifacts/post-1390-dispatcher-contract.md` (created in T003) under a heading `## Reconciliation against landed #1390`.

**Validation**: a reader of the contract artifact knows exactly which behaviors are confirmed against `main` and which are assumptions awaiting confirmation.

### T003 — Produce `artifacts/post-1390-dispatcher-contract.md`

**Purpose**: This is the canonical contract that consumers (and WP02–WP04) reference. It supersedes the draft in `contracts/dispatcher-deprecation-contract.md` if T002 found a divergence.

**Steps**:

1. Create `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/post-1390-dispatcher-contract.md`.
2. Required sections:
   - **Status** — confirmed-against-main / draft-pending-1390-merge.
   - **Trigger conditions** — exactly when the shim engages and exactly when the deprecation event fires.
   - **Attribute equivalence rules** — table per `data-model.md` §4.
   - **Log emission contract** — channel name, level, schema fields per `contracts/dispatcher-deprecation-contract.md` §"Schema". Lock the channel name (default proposal: `dispatcher.deprecation`).
   - **Dedup invariant** — `(controller_class, method, parameter_name)` per process.
   - **Edge case decisions** — finalize the four edge cases in spec §3 (mixed signature, query-only, non-route arrays, divergent #1390 shape).
   - **Reconciliation against landed #1390** — output of T002.
   - **Open items** — anything still TBD; should be empty before WP02 starts.
3. Cross-link to `data-model.md`, `contracts/dispatcher-deprecation-contract.md`, and `spec.md` FRs.

**Validation**: WP02 can be implemented from this artifact alone, without needing to re-read the issue.

### T004 — Produce `artifacts/controller-shape-audit.md`

**Purpose**: Inventory every controller method shape used by framework-shipped controllers so the deprecation noise the framework emits about itself is quantified up-front.

**Steps**:

1. Find every controller class in the framework. Start with:
   ```bash
   rg -l 'extends.*Controller|use Symfony\\\\Bundle.*Controller' packages/ --type php | sort -u
   ```
   Then refine — many framework "controllers" may not match the convention; look for classes that get registered as routes via `RouteBuilder` or `AppControllerArgumentResolver`. The `bin/check-package-layers`-friendly approach is to grep for usages of `App\` or `Waaseyaa\\Api\\Controller\\` patterns; pick the one that accurately captures the framework's own controllers.
2. For each `(class, method)` pair, classify per `data-model.md` §5 `ControllerShapeAuditRow`:
   - `attribute_annotated` — every `array $X` parameter has `#[MapRoute]` or `#[MapQuery]`.
   - `relies_on_shim` — at least one `array $X` parameter is unannotated and the shim would auto-bind it.
   - `no_array_params` — no `array` parameters at all.
3. Write `kitty-specs/.../artifacts/controller-shape-audit.md` with a markdown table:
   ```markdown
   | controller_class | method | category | notes |
   |------------------|--------|----------|-------|
   ```
4. Add a summary block at the top: total methods, count per category, top 5 affected files.

**Validation**:

- Every framework-shipped controller method appears in the table exactly once.
- The summary block matches the row counts.
- A reviewer can derive the framework's deprecation-noise budget for the next alpha.

**Tip**: this file is `[P]` (independent of T002/T005/T006) — generate concurrently if the agent supports it.

### T005 — Produce `artifacts/minoo-resume-verification.md`

**Purpose**: Hand Minoo a self-contained checklist that lets its frozen `upgrade-waaseyaa-alpha-171-01KQTDC2` mission resume against the next alpha without further framework discovery (FR-008, NFR-004).

**Steps**:

1. Create `kitty-specs/.../artifacts/minoo-resume-verification.md`.
2. Required sections:
   - **Prerequisites** — exact `composer require` command for the next alpha (with placeholder for the version), env vars, php version pin.
   - **Step-by-step verification** — numbered list. For each step, include the command to run, the expected pass signal, and the failure signal:
     - Composer install completes clean.
     - Booting the kernel does not 500. (Expectation: integration `composer test` passes, or `php artisan` boots.)
     - At least one previously-failing route returns HTTP 200 (provide an example smoke command).
     - Deprecation log lines appear with the schema documented in T003. (Provide a `grep` recipe.)
     - The framework deprecation count matches Minoo's expected migration debt (184 methods / 37 files per #1390).
   - **Resuming the upgrade mission** — the exact command Minoo uses to mark the upgrade mission as resumed (refer to Minoo's own Spec-Kitty conventions — call out that this lives outside this mission's scope).
   - **Escalation path** — what to do if any step fails: file a `framework` issue with template; do not proceed.
3. The plan must be runnable end-to-end without reading framework source.

**Validation**: A reviewer who has never read this mission can reproduce the plan against a hypothetical alpha bump and predict pass/fail at each step.

### T006 — File follow-up issues for adjacent invariants

**Purpose**: Spec C-005 hard-bounds this mission to the controller-dispatcher subsystem. Anything WP01's analysis surfaces *adjacent* to that (e.g., other alpha.171/172 invariants, JsonResponseTrait contract drift, EntityType `_fieldDefinitions` migration, ServiceProvider `setKernelServices` migration, phpstan baseline) must become its own GitHub issue, not absorbed into this mission.

**Steps**:

1. While doing T001 / T002 / T004, keep an internal list of adjacent invariants worth flagging.
2. For each, file a separate GitHub issue:
   ```bash
   gh issue create --repo waaseyaa/framework \
     --title "<descriptive title>" \
     --body "$(cat <<'EOF'
   <issue body explaining the invariant, the consumer-side blast radius, and a suggested deprecation/shim path mirroring #1390 / alpha.165 tenancy>
   EOF
   )" \
     --milestone <appropriate Track>
   ```
3. Reference all filed issue numbers in `artifacts/post-1390-dispatcher-contract.md` under an "Adjacent invariants surfaced" appendix.
4. If no adjacent invariants are found, write that explicitly in the appendix ("Audit found no adjacent invariants. Mission scope holds.").

**Validation**:

- No adjacent invariant is silently absorbed into this mission.
- Any filed issue carries a milestone (per `bin/check-milestones`).

## Test strategy

WP01 is markdown-only. There are no automated tests. Reviewer pass criteria:

- All three artifacts exist at the documented paths.
- Each artifact is self-contained and matches the contract / verification / audit definitions in this WP.
- Spec assumptions either confirmed against #1390's landed shape, or explicitly flagged for later reconciliation.
- Adjacent invariants either filed as separate issues or explicitly noted as none.

## Definition of Done

- [ ] `kitty-specs/.../artifacts/post-1390-dispatcher-contract.md` exists and supersedes the draft contract document.
- [ ] `kitty-specs/.../artifacts/controller-shape-audit.md` exists with the complete framework-controller inventory.
- [ ] `kitty-specs/.../artifacts/minoo-resume-verification.md` exists, runnable end-to-end without escalation.
- [ ] T002 reconciliation against #1390 is captured inside the contract artifact.
- [ ] Adjacent-invariant issues filed (if any) and listed in the contract artifact's appendix.
- [ ] All `tasks.md` checkbox rows for T001..T006 marked complete.
- [ ] WP01 commit lands on `main` via Spec-Kitty PR; PR references mission slug per `docs/specs/workflow.md`.

## Risks

- **#1390 not yet merged at execution time** → write against current main + flag assumptions; T002 records the divergence.
- **Audit grep misses an idiosyncratic controller** → use multiple grep patterns and cross-reference against `RouteBuilder` registrations; reviewer verifies count.
- **Resume-plan ambiguity** → have the plan reviewed by an operator unfamiliar with the mission before merging.

## Reviewer guidance

- Confirm each artifact is at its documented path.
- Open the resume plan and try to follow it without referring to anything outside the file. Flag any step that requires framework knowledge.
- Confirm the audit summary numbers match the table.
- Confirm any adjacent invariant filed as an issue is genuinely outside the dispatcher subsystem (per spec C-005).

## Implementation command

```bash
spec-kitty agent action implement WP01 --agent <your-agent-name> --mission post-1390-dispatcher-reconciliation-01KQTTJS
```
