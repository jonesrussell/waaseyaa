# Tasks: Entrypoint Provider — Trait-Member Reachability

**Mission**: `entrypoint-provider-trait-reachability-01KS3SMJ`
**Branch**: `main` → `main`
**Date**: 2026-05-20
**Closes**: #1501

---

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Run PHPStan baseline grep — enumerate all 31 trait entries | WP01 | — | [D] |
| T002 | Add temporary probe in provider to capture declaringClass + isTrait() | WP01 | — | [D] |
| T003 | Verify hasApiPhpDoc fires for the three traits via php -r reflection script | WP01 | — | [D] |
| T004 | Confirm loadEntitySupportingTraits populates RevisionableEntityTrait | WP01 | — | [D] |
| T005 | Write wp01-diagnosis.md with findings, confirmed hypothesis, WP02 design instruction | WP01 | — | [D] |
| T006 | Add `isTraitWithApiPhpDoc()` named method to WaaseyaaEntrypointProvider | WP02 | — | [D] |
| T007 | Wire isTraitWithApiPhpDoc into shouldMarkPropertyAsRead/Written and shouldMarkMethodAsUsed | WP02 | — | [D] |
| T008 | Write WaaseyaaEntrypointProviderTest.php: fixtures (a) @api trait, (b) no-@api trait, (c) regression for all three real traits | WP02 | — | [D] |
| T009 | Regenerate phpstan-dead-code-baseline.neon | WP03 | — |
| T010 | Assert 31 entries dropped — grep returns 0 for the three trait names | WP03 | — |
| T011 | Run `composer verify` — exits 0 | WP03 | — |
| T012 | Run `phpunit --filter WaaseyaaEntrypointProviderTest` — exits 0 | WP03 | — |
| T013 | Update CLAUDE.md § "Dead code audits and intentional scaffolding" | WP04 | — |
| T014 | Add CHANGELOG.md [Unreleased] bullet | WP04 | — |
| T015 | Final `composer verify` green confirmation | WP04 | — |

---

## Work Package 1 — Diagnostic (Investigative)

**Goal**: Determine exactly which failure mechanism produces the 31 persistent baseline entries.
No code changes. Deliverable is `research/wp01-diagnosis.md`.

**Priority**: Critical (all other WPs depend on this)
**Estimated prompt size**: ~300 lines
**Dependencies**: none

### Subtasks
- [x] T001 Run PHPStan baseline grep — enumerate all 31 trait entries (WP01)
- [x] T002 Add temporary probe in provider to capture declaringClass + isTrait() (WP01)
- [x] T003 Verify hasApiPhpDoc fires for the three traits via php -r reflection script (WP01)
- [x] T004 Confirm loadEntitySupportingTraits populates RevisionableEntityTrait (WP01)
- [x] T005 Write wp01-diagnosis.md with findings, confirmed hypothesis, WP02 design instruction (WP01)

### Implementation sketch
1. Run PHPStan dead-code analysis, grep for the three trait names, capture all 31 findings.
2. Add `error_log()` probes in the provider's `shouldMarkPropertyAsRead`/`shouldMarkPropertyAsWritten` to surface declaringClass FQCN and `isTrait()` value per call.
3. Run a standalone `php -r` script to instantiate `ReflectionClass` for each of the three traits and call `hasApiPhpDoc` logic directly.
4. Check whether `loadEntitySupportingTraits` actually populates `RevisionableEntityTrait` by examining its scanner's glob output.
5. Consolidate into `research/wp01-diagnosis.md`.

**Risks**: Probe output may require a `--debug` mode rather than `error_log`; adjust if needed.
**Prompt file**: `tasks/WP01-diagnostic.md`

---

## Work Package 2 — Provider Patch

**Goal**: Implement the fix from WP01's diagnosis. Add `isTraitWithApiPhpDoc()` named method.
Write unit tests covering fixtures (a), (b) and regression cases (c).

**Priority**: Critical
**Estimated prompt size**: ~350 lines
**Dependencies**: WP01

### Subtasks
- [x] T006 Add `isTraitWithApiPhpDoc()` named method to WaaseyaaEntrypointProvider (WP02)
- [x] T007 Wire isTraitWithApiPhpDoc into shouldMarkPropertyAsRead/Written and shouldMarkMethodAsUsed (WP02)
- [x] T008 Write WaaseyaaEntrypointProviderTest.php: (a) @api trait, (b) no-@api trait, (c) regression for real traits (WP02)

### Implementation sketch
1. Add private static `isTraitWithApiPhpDoc(\ReflectionClass $declaringClass): bool` that returns `$declaringClass->isTrait() && self::hasApiPhpDoc($declaringClass)`.
2. In `shouldMarkPropertyAsRead`, add a guard before the `isEntrypointClass` call.
3. Mirror in `shouldMarkPropertyAsWritten` (already delegates) and `shouldMarkMethodAsUsed`.
4. Write test file: fixture traits created via temp PHP files + eval; regression cases use real reflections of the three live traits.
5. Commit with `Closes #1501` in footer.

**Risks**: eval-based fixture stubs may need careful autoload setup; use temp file + include instead if eval is problematic.
**Prompt file**: `tasks/WP02-provider-patch.md`

---

## Work Package 3 — Baseline Regeneration and Verification

**Goal**: Regenerate `phpstan-dead-code-baseline.neon`. Assert exactly 31 entries dropped.
Confirm `composer verify` and unit test both exit 0.

**Priority**: Critical
**Estimated prompt size**: ~240 lines
**Dependencies**: WP02

### Subtasks
- [ ] T009 Regenerate phpstan-dead-code-baseline.neon (WP03)
- [ ] T010 Assert 31 entries dropped — grep returns 0 for the three trait names (WP03)
- [ ] T011 Run `composer verify` — exits 0 (WP03)
- [ ] T012 Run `phpunit --filter WaaseyaaEntrypointProviderTest` — exits 0 (WP03)

### Implementation sketch
1. Count entries in baseline before regeneration.
2. Run regeneration command.
3. Count entries after; verify delta = −31.
4. Grep for the three trait file paths; assert count = 0.
5. Run `composer verify` and `phpunit --filter`.
6. Commit the regenerated baseline.

**Risks**: Regeneration may reveal unrelated new dead-code entries (out of scope); document but do not chase.
**Prompt file**: `tasks/WP03-baseline-regeneration.md`

---

## Work Package 4 — Wrap-up

**Goal**: Documentation and changelog. Final `composer verify` green.

**Priority**: Normal
**Estimated prompt size**: ~200 lines
**Dependencies**: WP03

### Subtasks
- [ ] T013 Update CLAUDE.md § "Dead code audits and intentional scaffolding" (WP04)
- [ ] T014 Add CHANGELOG.md [Unreleased] bullet (WP04)
- [ ] T015 Final `composer verify` green confirmation (WP04)

### Implementation sketch
1. Insert the trait-@api propagation note into CLAUDE.md's dead-code section.
2. Add fix(dead-code) bullet to CHANGELOG.md [Unreleased].
3. Run `composer verify` — confirm exit 0.
4. Commit.

**Risks**: none significant.
**Prompt file**: `tasks/WP04-wrap-up.md`

---

## WP Dependency Chain

```
WP01 (diagnostic) → WP02 (provider patch) → WP03 (baseline regen) → WP04 (wrap-up)
```

All WPs are sequential — single lane.
