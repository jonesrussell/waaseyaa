---
affected_files: []
cycle_number: 1
mission_slug: post-1390-dispatcher-reconciliation-01KQTTJS
reproduction_command:
reviewed_at: '2026-05-05T15:57:13Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP03
---

**Scope expansion (orchestrator decision, not a code defect)**

WP03's deliverables are correct: 5 fixtures, 7/7 contract tests, 6 new unit tests, all static gates green, scope of touched files matches the WP body. However, WP03's DoD line "Full PHPUnit suite green (no `-v` flag)" is not satisfied because **one integration test** at `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php` is stale against the WP01 contract:

- It asserts `'implicit array parameter'` (pre-contract message) instead of contract §5's `'relies on the implicit-array shim'`.
- It uses log key `method_name` instead of contract §5's `method`.
- It uses `'#[MapRoute]'` with brackets instead of bare `'MapRoute'`.
- It expects throw-on-unbound, which contract §3 supersedes with `ImplicitEmptyArray` + a single `implicit_array_unbound` notice.

This is the same flavor as the WP02 cycle-2 reconciliation: a #1390-era test that ships pre-contract assertions. It was not in WP02's reconciliation pass because the directory wasn't in WP02's scope.

**Required action (re-implementation, narrow scope):**

1. Reconcile only `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php` against the locked contract:
   - Update the asserted log payload to contract §5: `channel='dispatcher.deprecation'`, `event='implicit_array_shim'` (or `'implicit_array_unbound'` per case), `method` (not `method_name`), bare `'MapRoute'`/`'MapQuery'`/`''`, message `'relies on the implicit-array shim'` (or contract §5's unbound template).
   - For any sub-case asserting throw-on-unbound, rewrite to assert `AppParameterKind::ImplicitEmptyArray`, exactly one `implicit_array_unbound` notice with `recommended_attribute=''`, and bound value `[]`.
   - **Do NOT add new test cases.** Only reconcile the existing assertions to match the contract.
2. Do NOT touch any other file. Production code (`packages/ssr/src/...`) is final from WP02. The five fixtures, the 6 unit tests, and the 7 contract tests from WP03's prior implementation (`d4cc1726c`) are correct and stay.
3. Re-run all gates from the lane worktree before requesting review:
   - `./vendor/bin/phpunit packages/ssr/tests/`  (must be all green, including Integration)
   - `./vendor/bin/phpunit`  (full repo green or only with pre-existing failures unrelated to dispatcher work — list any in the for_review note)
   - `composer cs-check`
   - `composer phpstan`
   - `bin/check-package-layers`
   - `bin/check-composer-policy`

**Scope authorization:** `packages/ssr/tests/Integration/**` is **not** currently in `lane-a`'s `write_scope` (lanes.json) nor in WP03's `owned_files` (frontmatter). Treat this paragraph as the orchestrator's documented exception. If spec-kitty's commit guard or a write_scope check blocks the edit, surface the exact error and stop — do not add `--force` or otherwise bypass; the orchestrator will widen the lane scope on main first.

**Conflict-of-precedence reminder:** If the contract §3/§5 disagrees with what the existing test asserts, the contract wins. Update the test, never the production code. WP02's commit `b85898cea` matches the contract; do not introduce code changes that would make the stale test pass.

**Cycle counter:** This is WP03 cycle 2. A cycle-3 rejection triggers arbiter mode.
