---
affected_files: []
cycle_number: 1
mission_slug: post-1390-dispatcher-reconciliation-01KQTTJS
reproduction_command:
reviewed_at: '2026-05-05T15:36:08Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP02
---

**Scope expansion (orchestrator decision, not a code defect)**

WP02's production-code change matches WP01's contract artifact and all gates pass (cs-check, phpstan level 5, check-package-layers, check-composer-policy, ssr Contract suite 30/30, manifest boot). However, 3 unit tests in `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php` ship from #1390 against the **pre-contract** schema and now fail against the locked contract:

- `implicitArrayParamsShimsToMapRouteAndLogsOnce`
- `implicitArrayQueryShimsToMapQueryAndLogsOnce`
- `unannotatedArrayWithUnshimmedNameStillThrows`

These tests reference the old payload shape (`#[MapRoute]` brackets, `method_name` log key, `throw` on unbound) that WP01's contract §3 and §5 explicitly supersedes. The new code is correct; the tests are stale relative to the locked contract.

**Required action (re-implementation):**

1. Update those 3 tests in-place to match the WP01 contract schema:
   - Bare `#[MapRoute]` / `#[MapQuery]` (no brackets), parameter `method` (not `method_name`), event `implicit_array_shim` / `implicit_array_unbound` per contract §5.
   - Replace the `unannotatedArrayWithUnshimmedNameStillThrows` test with the contract's actual behavior: log `implicit_array_unbound` once and bind to empty array (`ImplicitEmptyArray`).
2. Do NOT add new dedup-behavior coverage here — that remains WP03's scope. Only reconcile the 3 existing tests so the suite is green against the contract.
3. Re-run all gates from the lane worktree before requesting review again:
   - `./vendor/bin/phpunit packages/ssr/tests/Unit/`
   - `./vendor/bin/phpunit packages/ssr/tests/Contract/`
   - `composer cs-check`
   - `composer phpstan`
   - `bin/check-package-layers`
   - `bin/check-composer-policy`

**Scope authorization:** `packages/ssr/tests/Unit/Http/AppController/**` is already in `lane-a` write_scope per `lanes.json`. WP02's `owned_files` was drafted before we knew #1390 would ship contract-incompatible tests; the reconciliation belongs with the production code change, not deferred to WP03. Treat this paragraph as the orchestrator's documented exception.

**Do not redo T007/T008/T009 production code.** Commit `b85898cea` is correct and stays. Add only a follow-up commit with the test reconciliation.
