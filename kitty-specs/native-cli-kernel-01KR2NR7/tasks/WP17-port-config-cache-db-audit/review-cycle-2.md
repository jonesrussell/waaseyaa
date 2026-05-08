---
affected_files: []
cycle_number: 2
mission_slug: native-cli-kernel-01KR2NR7
reproduction_command:
reviewed_at: '2026-05-08T15:36:28Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP17
---

# WP17 Review — Cycle 1: REJECTED

**Commit reviewed:** `62a6258cf` (lane `lane-a`, mission `native-cli-kernel-01KR2NR7`).
**Reviewer:** claude:opus-4-7:reviewer
**Date:** 2026-05-08

## Verdict

**REJECTED.** Implementer's claim "phpstan GREEN" is false. Running `composer phpstan` from the worktree at `62a6258cf` produces **10 errors**, broken into two distinct defects:

### Defect 1 — Stale phpstan baseline entries (5 unmatched ignores)

`phpstan-baseline.neon` was not updated when WP17 deleted `packages/cli/src/Command/AuditLogCommand.php`. The baseline still contains 5 ignored-error entries pointing at the now-deleted file:

```
$ grep -n "Command/AuditLogCommand.php" phpstan-baseline.neon
295:    path: packages/cli/src/Command/AuditLogCommand.php
301:    path: packages/cli/src/Command/AuditLogCommand.php
307:    path: packages/cli/src/Command/AuditLogCommand.php
313:    path: packages/cli/src/Command/AuditLogCommand.php
319:    path: packages/cli/src/Command/AuditLogCommand.php
```

Phpstan reports each with: *"Ignored error pattern #^Offset 'X' on array{...}# in path packages/cli/src/Command/AuditLogCommand.php was not matched in reported errors."*

`git diff 098f653fc..62a6258cf -- phpstan-baseline.neon` is empty — the baseline was never touched.

### Defect 2 — Five NEW phpstan errors in `AuditLogHandler.php` (the port)

The same offending pattern that was suppressed at the old `Command/AuditLogCommand.php` line is now triggering as a real error at `packages/cli/src/Handler/AuditLogHandler.php:53` (and surrounding lines):

```
Line  cli/src/Handler/AuditLogHandler.php
53    Offset 'entity_type_id' on array{entity_type_id: string, action: string,
      actor_id: string, timestamp: string} on left side of ?? always exists
      and is not nullable.
      (also: 'action', 'actor_id', 'tenant_id', 'timestamp' — 5 errors total)
```

These are legitimate type-narrowing issues: when the typed shape guarantees the offset exists and is non-null, `?? ''` is dead code per phpstan strict rules. Per the project's phpstan config (which forbids `@phpstan-ignore` and tells reviewers "Do not just suppress"), the fix is to drop the `?? ''` defensive coalescing where the array shape already proves the keys are present, OR widen the array shape if the keys are genuinely optional.

**Score:** 5 stale baseline + 5 new errors = **10 errors**, matching `composer phpstan` exit-code-1 output.

## Other gate results (pre-fix, for context)

| Gate | Result |
|---|---|
| Fixture immutability (5 fixtures vs `a923be435`) | PASS — diff is empty |
| HelpRenderer untouched by WP17 | PASS — `git log 098f653fc..62a6258cf -- packages/cli/src/Help/HelpRenderer.php` is empty |
| Snapshot tests | PASS — 52/52 (cumulative across WPs) |
| Full phpunit | PASS — 7474 tests, 0 errors, 0 failures |
| Ghost imports in test tree | PASS-with-note — three soft references remain in `tests/Integration/Phase9/CliCommandIntegrationTest.php:107` (test method name `testCacheClearCommandClearsAllBins`) and two doc-comments in `tests/Integration/Phase10/EndToEndSmokeTest.php:68,497`. These are textual-only (method name + comments) and do not import or instantiate the deleted classes. **Not blocking, but please rename the method and refresh the comments while addressing the phpstan failures.** |
| cs-check | PASS |
| check-package-layers | PASS |
| check-composer-policy | PASS |
| Main repo contamination | PASS — only ephemeral `csrf_upload_*` and `tmp.*` files |
| db:init pre-boot wiring | PASS (rationale-checked) — `ConsoleKernel` Closure-wires `DbInitHandler` to `db:init` so the bootstrap command can run before a full kernel boot. This matches the spec/charter intent that `db:init` must be reachable on a fresh install with no schema present. The construction `new DbInitHandler(projectRoot: $projectRoot)` in `ConsoleKernel` plus the dispatch through native `CliKernel` is justified — DI wiring would require a booted entity container that doesn't yet exist on first run. |

## Required fixes

1. **Update `phpstan-baseline.neon`**: remove the 5 stale `AuditLogCommand.php` entries.
2. **Fix the 5 new errors at `AuditLogHandler.php:~53`** without adding `@phpstan-ignore` comments and without re-adding the deleted commands' patterns to the baseline. Either tighten the array shape to mark the offsets as optional / nullable (so `?? ''` is meaningful), or drop the `?? ''` coalescing where the typed shape already guarantees presence/non-null.
3. **Re-run** `composer phpstan` from the lane worktree and confirm `[OK] No errors` before requesting re-review.
4. **(Soft, do at the same time)** rename `testCacheClearCommandClearsAllBins` → `testCacheClearHandlerClearsAllBins` in `tests/Integration/Phase9/CliCommandIntegrationTest.php` and refresh the two `EndToEndSmokeTest.php` doc-comment references to handler names.
5. **Update the WP17 commit body** so it doesn't claim "phpstan GREEN" when the next push lands the fix — replace with the actual gate output.

## Note to dependents

WP23 depends on WP17. The handler-port surface, snapshot fixtures, and ConsoleKernel db:init wiring are all stable and will not change in cycle 2 — only the phpstan baseline and the `AuditLogHandler::formatEntries()` (or equivalent) defensive `?? ''` block. Rebases for downstream WPs should be clean.
