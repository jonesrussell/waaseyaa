---
work_package_id: WP02
title: 'array_find: production planner + routing/access sweep'
dependencies:
- WP01
requirement_refs:
- FR-005
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T005
- T006
agent: "claude:opus-4-7:opus-reviewer:reviewer"
shell_pid: "89885"
history:
- timestamp: '2026-05-10T04:40:07Z'
  actor: spec-kitty.tasks
  note: Initial work package generated from plan.md
authoritative_surface: packages/cli/src/Ingestion/
execution_mode: code_change
owned_files:
- packages/cli/src/Ingestion/SemanticRefreshTriggerPlanner.php
tags: []
---

# WP02 — array_find: production planner + routing/access sweep

## Objective

Apply `array_find()` to one production callsite in `SemanticRefreshTriggerPlanner.php:415` after verifying first-match semantics. Conduct a read-only audit sweep of `packages/routing/` and `packages/access/` for additional `array_find` candidates and document findings.

## Branch Strategy

- **Planning base branch**: `main`
- **Final merge target**: `main`
- This WP depends on WP01 — the agent should branch off WP01's lane (or main after WP01 merges) per `lanes.json`.

## Context

The audit (2026-05-10) flagged `packages/cli/src/Ingestion/SemanticRefreshTriggerPlanner.php:415`:

```php
$members = array_values(array_filter(array_map(...)));
```

Confidence was rated **medium** because the result shape (single value vs. list) was not confirmed from the audit alone. This WP confirms shape first, then either swaps or closes-with-rationale.

The sweep covers `packages/routing/src/` and `packages/access/src/` — likely candidates for `foreach { if return }` loops that map to `array_find` semantics. **This WP does NOT edit those packages**; if findings warrant a follow-up mission, file an issue.

## Subtasks

### T005 — Verify and (if applicable) swap `SemanticRefreshTriggerPlanner.php:415`

**Steps**:
1. Open `packages/cli/src/Ingestion/SemanticRefreshTriggerPlanner.php`.
2. Read 30 lines around line 415 to understand:
   - What the `array_map(...)` closure returns (mixed/null/scalar/object?).
   - How `$members` is consumed downstream — looped over (list shape), single-element (`$members[0]`), or counted (`count($members)`)?
3. Decision tree:
   - **If `$members` is consumed as a list** (foreach, count, sliced): Leave as-is. The current pattern is correct. Document this decision in the WP close-out under T005 status.
   - **If `$members` is consumed only as a first-match** (`$members[0]`, `reset($members)`, etc.): swap `array_values(array_filter(array_map($source, $fn)))` → `array_find(array_map($source, $fn), static fn ($x) => $x !== null)` (or equivalent predicate matching the original filter).
4. If swapping: re-run the planner's tests:
   ```bash
   ./vendor/bin/phpunit packages/cli/tests/Unit/Ingestion/
   ```
5. If swapping: also run `composer phpstan` to confirm no new errors (production code is more sensitive to type-shape changes than test code).

**Validation**:
- [ ] Decision recorded (swap or close-with-rationale).
- [ ] If swapped: planner unit tests green; PHPStan green.
- [ ] If left: justification line in WP close-out.

### T006 — Read-only sweep of `packages/routing/` and `packages/access/`

**Steps**:
1. Run targeted greps:
   ```bash
   rg -nP "foreach\s*\(.+?\)\s*\{[\s\S]+?return\s+\$" packages/routing/src/ packages/access/src/
   rg -n "array_values\(array_filter" packages/routing/src/ packages/access/src/
   ```
2. For each match, manually inspect 5–10 lines to classify:
   - **first-match candidate** (clean `array_find` swap),
   - **list filter** (not a candidate),
   - **complex** (nested control flow, side effects, accumulator — not a candidate).
3. Tally findings in a table for the WP close-out:

   | File:line | Pattern | Category | Action |
   |---|---|---|---|
   | (fill in) | (one-line excerpt) | first-match / list / complex | swap-here / follow-up-issue / skip |

4. **Do not edit** `packages/routing/` or `packages/access/` in this WP. If material first-match candidates exist (≥3), file a GitHub issue titled "Follow-up: array_find sweep in routing/access" linked to this mission.

**Validation**:
- [ ] Sweep table produced (even if empty).
- [ ] No edits to `packages/routing/src/` or `packages/access/src/` (`owned_files` does not include them).
- [ ] Follow-up issue filed if warranted; link captured in WP close-out.

## Definition of Done

- [ ] T005 decision documented and (if applicable) implemented.
- [ ] T006 sweep table complete; follow-up issue filed if warranted.
- [ ] Full PHPUnit suite green if T005 swapped.
- [ ] PHPStan + cs-check green if T005 swapped.
- [ ] No edits outside `owned_files`.

## Risks

- **Type-shape change**: swapping in production code can change the return type of an enclosing function (e.g., `array<int, T>` → `?T`). PHPStan will catch this — do not suppress.
- **Sweep scope creep**: resist editing in routing/access this round. Discipline matters for review clarity.

## Reviewer guidance

- If T005 swapped: confirm caller's expected shape matches `array_find`'s `T|null` return.
- If T005 left: confirm the rationale (list-shape consumption) is plausibly correct from the surrounding 30 lines.
- T006 closure: a non-empty sweep table is acceptable; an empty one is acceptable; what matters is that the agent looked.

## Implementation command

```bash
spec-kitty agent action implement WP02 --agent <agent-name>
```

## Activity Log

- 2026-05-10T04:50:50Z – claude:opus-4-7:opus-implementer:implementer – shell_pid=88915 – Started implementation via action command
- 2026-05-10T04:52:29Z – claude:opus-4-7:opus-implementer:implementer – shell_pid=88915 – WP02 ready: left-as-is SemanticRefreshTriggerPlanner.php (list-rebuild, not first-match); routing/access sweep found 0 candidates
- 2026-05-10T04:52:52Z – claude:opus-4-7:opus-reviewer:reviewer – shell_pid=89885 – Started review via action command
