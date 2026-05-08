---
affected_files: []
cycle_number: 3
mission_slug: native-cli-kernel-01KR2NR7
reproduction_command:
reviewed_at: '2026-05-08T14:32:52Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP15
---

# WP15 Review — Cycle 3

**Verdict:** REJECTED

## Summary

Cycle-2 correctly migrated the two Phase9 `CliCommandIntegrationTest` cases (`testUserCreateCommand`, `testUserRoleAddAndRemove`) from `CommandTester` to `CliTester` + handlers, removed the stale `UserCreateCommand`/`UserRoleCommand` imports, and the User/Permission cluster port itself is sound. That part of the work is good.

However, the implementer's framing of the 2 remaining suite errors as "pre-existing Phase10 errors present in main repo before this WP" is **factually incorrect**, and this is a stop-the-line issue.

## The Phase10 errors are WP14 fallout, not pre-existing main state

Investigation:

```
$ git log --diff-filter=D --oneline -- \
    packages/cli/src/Command/EntityCreateCommand.php \
    packages/cli/src/Command/TypeDisableCommand.php
447b5596d feat(WP14): port entity + type lifecycle cluster to native CLI
```

Both classes were deleted by **WP14** (commit `447b5596d`), inside this mission's lane. They did **not** vanish in main pre-mission.

```
$ grep -rn "EntityCreateCommand\|TypeDisableCommand" tests/Integration/Phase10/
tests/Integration/Phase10/EndToEndSmokeTest.php:26:use Waaseyaa\CLI\Command\EntityCreateCommand;
tests/Integration/Phase10/EndToEndSmokeTest.php:29:use Waaseyaa\CLI\Command\TypeDisableCommand;
tests/Integration/Phase10/EndToEndSmokeTest.php:141:        $createCommand = new EntityCreateCommand($entityTypeManager);
tests/Integration/Phase10/EndToEndSmokeTest.php:505:        $application->add(new TypeDisableCommand($entityTypeManager, $lifecycleManager));
```

Phase10 `EndToEndSmokeTest` still imports and instantiates both deleted classes. This is the same class of bug that caused cycle-1 rejection on WP15 — a port deleted Symfony command classes without sweeping all integration-test callers — except it originated in **WP14** and was missed by both implementer and reviewer (me, prior cycle).

## Required for cycle 3

Migrate `tests/Integration/Phase10/EndToEndSmokeTest.php` off the deleted Symfony command classes, using the same `CliTester` + handler pattern applied to Phase9 in cycle-2. Specifically:

1. Remove `use Waaseyaa\CLI\Command\EntityCreateCommand;` and `use Waaseyaa\CLI\Command\TypeDisableCommand;` (lines 26, 29).
2. Replace the `new EntityCreateCommand($entityTypeManager)` site (~line 141) with the native CLI equivalent (`CliTester` driving `entity:create` via the registered handler, mirroring WP14's other migrated callers).
3. Replace the `$application->add(new TypeDisableCommand(...))` site (~line 505) similarly — invoke `type:disable` through the native CLI surface, not a Symfony `Application`.
4. Re-run the full suite and confirm **0 errors, 0 failures** (excluding any genuinely unrelated skips). Do not declare suite-green with non-zero errors again.
5. Re-run snapshot byte-parity and `cs-check` / `phpstan` to make sure this fix doesn't perturb baselines.

## Notes for the implementer

- "Pre-existing in main" means the error reproduces against `origin/main` with no mission commits applied. It does **not** mean "present before I started this WP within the mission lane." Mission lane debt that originated in an earlier WP of the same mission is mission debt, and the next WP that observes it owns the fix (or files an immediate fast-follow WP that blocks approval of subsequent WPs).
- This was avoidable: WP14's grep should have included `tests/Integration/Phase10/`, not just Phase9. Worth a one-line note in the mission's lessons / gotchas so future ports sweep all `tests/Integration/Phase*/` directories before deleting Symfony command classes.

## Other checks (deferred)

Snapshot byte-parity, fixture immutability, cs-check/phpstan, and main-repo contamination were not re-verified this cycle because the suite-green precondition fails. Re-run on cycle 3.
