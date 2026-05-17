---
work_package_id: WP09
title: Access policy translate operation
dependencies:
- WP01
requirement_refs:
- FR-047
- FR-048
- FR-049
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T046
- T047
- T048
- T049
- T050
history: []
authoritative_surface: packages/access/
execution_mode: code_change
owned_files:
- packages/access/src/Gate/AccessChecker.php
- packages/access/tests/Unit/AccessChecker*
- packages/access/tests/Unit/Translate*
tags: []
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "598028"
---

# WP09 — Access policy translate operation

## Objective

Add `'translate'` to `AccessChecker`'s recognized operation names, default it to `update`-compatible when policies return Neutral, honor explicit `Forbidden`, and pass `['langcode' => $lc]` context to access policy calls.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.9 (FR-047..FR-049)
- **Research:** [`../research.md`](../research.md) R11

## Subtasks

### T046 — Recognize `'translate'` operation

**Steps:**

1. Open `packages/access/src/Gate/AccessChecker.php`. Find the recognized operations list (likely a const or method).
2. Add `'translate'` to the recognized set.
3. If operation names are validated against a whitelist, update the whitelist.

**Files:** `packages/access/src/Gate/AccessChecker.php` (modify, ~10 lines).

### T047 — Default behavior: translate ⊆ update

**Steps:**

1. In `AccessChecker::check($entity, $op, $account, $context)`:
   - After invoking all policies, if `$op === 'translate'` and the aggregated result is `Neutral`:
     - Re-invoke `check($entity, 'update', $account, $context)`.
     - Return that result.
   - Otherwise return the original aggregated result.

**Files:** ~20 lines added.

### T048 — Explicit Forbidden honored

**Steps:**

1. The default `Neutral`→`update` fallthrough ONLY applies when no policy returned a non-Neutral verdict. If any policy returns `Forbidden` on `translate`, it's honored (no fallthrough to `update`).
2. The aggregator logic already does this — verify it.

**Files:** Probably no changes; verification only.

### T049 — `['langcode' => $lc]` context

**Steps:**

1. When the coordinator (or controller) invokes `AccessChecker::check()` for a `translate` operation, it MUST pass `['langcode' => $lc]` in the `$context` array.
2. Inspect the existing `AccessChecker` signature for `$context`. If absent, add as a parameter:
   ```php
   public function check(
       EntityInterface $entity,
       string $op,
       AccountInterface $account,
       array $context = [],
   ): AccessResult;
   ```
3. Each policy's `access()` method already accepts `$context`; verify they look at `$context['langcode']` when implementing translate decisions.

**Files:** `packages/access/src/Gate/AccessChecker.php` (modify, ~15 lines), `packages/access/src/AccessPolicyInterface.php` (if signature update needed).

### T050 — Unit tests

**Steps:**

1. Create `packages/access/tests/Unit/TranslateOperationTest.php`:
   - Translate operation recognized (no exception).
   - Translate Neutral aggregate falls through to update; update returns Allowed → translate returns Allowed.
   - Translate explicit Forbidden honored (no fallthrough).
   - Translate context contains `langcode` for policy introspection.
   - Test with a fixture policy that returns different verdicts for different langcodes (e.g. allows 'en' translation but forbids 'fr').

**Files:** ~200 lines of tests.

## Definition of Done

- [ ] `AccessChecker` recognizes `'translate'`.
- [ ] Default `Neutral` translate falls through to `update`.
- [ ] Explicit `Forbidden` translate is honored.
- [ ] `$context['langcode']` is plumbed through to policies.
- [ ] All unit tests pass.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.

## Risks

| Risk | Mitigation |
|---|---|
| AccessChecker signature change (adding `$context` if not already present) could break callers. | Default to `[]`; existing callers unaffected. |
| `AccessPolicyInterface::access()` signature is part of the stable surface. | Verify the `$context` array is already accepted; if not, this is a (minor) stable-surface change. Document in WP14. |

## Reviewer guidance

- Verify the fallthrough logic checks the aggregated result is precisely `Neutral` (not just non-Forbidden).
- Verify the langcode context plumbing is in the access checker, not silently embedded in policies (single source of context).

## Implementation command

```bash
spec-kitty agent action implement WP09 --agent <name>
```

## Activity Log

- 2026-05-12T23:44:57Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=595862 – Started implementation via action command
- 2026-05-12T23:51:04Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=595862 – Access translate op: Neutral->update fallthrough, explicit Forbidden honored, langcode context plumbed
- 2026-05-12T23:51:39Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=598028 – Started review via action command
- 2026-05-12T23:54:18Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=598028 – WP09 approved: translate op + Neutral->update fallthrough + langcode context via ContextAwareAccessPolicyInterface companion. Backward-compat preserved (~40 existing policies). Baseline improved 46/1->46/0.
