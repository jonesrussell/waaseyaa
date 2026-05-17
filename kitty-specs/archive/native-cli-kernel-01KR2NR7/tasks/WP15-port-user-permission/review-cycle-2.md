---
affected_files: []
cycle_number: 2
mission_slug: native-cli-kernel-01KR2NR7
reproduction_command:
reviewed_at: '2026-05-08T14:27:29Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP15
---

# WP15 Review — Cycle 1: REJECTED

**Reviewed commit:** 38b19488e
**Reviewer:** opus
**Verdict:** REJECTED

## Summary

WP15 ports `user:create`, `user:role`, `permission:list` to the native CLI handler pattern and deletes the legacy Symfony `Command` classes. Snapshot byte-parity is preserved, the new handlers and `UserPermissionServiceProvider` look sound, and `phpstan-baseline.neon` correctly shrinks (218 → 100 entries; net -18 baseline lines as advertised). However, the implementer's claim of **"487/487 GREEN"** is **inaccurate at the repo level** — the full PHPUnit run has **4 errors**, and `bin/waaseyaa list` is broken.

## Blocking defects

### 1. Orphaned references to deleted Command classes (BLOCKER)

`tests/Integration/Phase9/CliCommandIntegrationTest.php` still imports and instantiates `UserCreateCommand` and `UserRoleCommand`, both of which were deleted in this commit:

```
tests/Integration/Phase9/CliCommandIntegrationTest.php:19:use Waaseyaa\CLI\Command\UserCreateCommand;
tests/Integration/Phase9/CliCommandIntegrationTest.php:20:use Waaseyaa\CLI\Command\UserRoleCommand;
tests/Integration/Phase9/CliCommandIntegrationTest.php:245:        $command = new UserCreateCommand($this->entityTypeManager);
tests/Integration/Phase9/CliCommandIntegrationTest.php:290:        $createCommand = new UserCreateCommand($this->entityTypeManager);
tests/Integration/Phase9/CliCommandIntegrationTest.php:296:        $roleCommand = new UserRoleCommand($this->entityTypeManager);
```

Running `./vendor/bin/phpunit` from the repo root produces:

```
Tests: 7462, Assertions: 17941, Errors: 4, PHPUnit Warnings: 1, Skipped: 2.
Error: Class "Waaseyaa\CLI\Command\UserCreateCommand" not found
  /tests/Integration/Phase9/CliCommandIntegrationTest.php:290
... (and three more in the same file)
```

The "487/487" figure in the commit message reflected only the cli package suite, not the full repo suite that the constitution / quality gate requires. **Standard testing gate is RED.**

**Fix:** Migrate `testUserCreateCommand` and `testUserRoleAddAndRemove` in `tests/Integration/Phase9/CliCommandIntegrationTest.php` to the `CliTester` + handler pattern (mirroring the new `tests/Unit/Handler/UserCreateHandlerTest.php` / `UserRoleHandlerTest.php`), or delete them if the new handler tests fully cover their cases. The previous WPs that deleted commands handled this same migration — see prior commits in the lane for the established pattern.

### 2. `bin/waaseyaa list` is broken

`./bin/waaseyaa list 2>&1` returns `Unknown command: list`. The list / no-DuplicateCommandException gate cannot be confirmed because the command itself does not resolve. This may be unrelated to WP15 (could be a pre-existing lane state for the minimal console path), but the implementer cannot truthfully assert this gate without showing a working invocation. Please document the canonical "list works" reproduction (e.g., `php bin/waaseyaa list` with appropriate env, or the actual CLI verb) and paste output before re-submitting.

## Non-blocking findings

- **PermissionHandler intelephense flag is a false alarm — NOT a real defect.** I checked every reference in the worktree:
  - `packages/access/src/PermissionHandler.php:10` — sole `class PermissionHandler` declaration, namespace `Waaseyaa\Access`.
  - `packages/foundation/src/Kernel/ConsoleKernel.php:7` — `use Waaseyaa\Access\PermissionHandler;` (correct).
  - `packages/cli/src/CliCommandRegistry.php:8` — `use Waaseyaa\Access\PermissionHandler;` (correct).
  - `packages/cli/tests/Unit/CliCommandRegistryTest.php:11` — `use Waaseyaa\Access\PermissionHandler;` (correct).
  - No file declares or references `Waaseyaa\CLI\PermissionHandler`.

  Intelephense's report of "expects `Waaseyaa\CLI\PermissionHandler`" is a stale-index / namespace-resolution glitch in the LSP, not a code bug. PHP runtime resolves these references correctly. Reinitialize the intelephense workspace cache to clear the warnings.

- **Fixture immutability for WP15 commands: VERIFIED.** `git diff a923be435..HEAD -- packages/cli/tests/Fixtures/snapshots/{user__create,user__role,permission__list}.help.{stdout,stderr,exit}` is empty. The snapshot diff stat shows only *additions* of new snapshots from prior WPs (queue, schedule, telescope) — no modifications to the three WP15 fixtures.

- **Cumulative byte-parity for ported commands: VERIFIED.** `./vendor/bin/phpunit packages/cli/tests/Integration/Snapshot/` → 42/42 OK, 126 assertions.

- **HelpRenderer untouched: VERIFIED.** `git log 38b19488e -1 --name-only` shows no HelpRenderer file in the changed set.

- **No main-repo contamination.** All edits are within the lane-a worktree.

- **phpstan-baseline.neon is deletions-only for the affected commands**, as claimed (218 → 100 entries; net negative). Good.

## What's needed for re-review

1. Migrate or delete `testUserCreateCommand` and `testUserRoleAddAndRemove` in `tests/Integration/Phase9/CliCommandIntegrationTest.php` so the full `./vendor/bin/phpunit` run is GREEN.
2. Demonstrate a successful `list` invocation (no `DuplicateCommandException`) and paste the output in the implementer report.
3. Re-run `composer cs-check`, `composer phpstan`, and full `./vendor/bin/phpunit`; paste tail output of each into the report.

Once the full repo test suite is GREEN and the list gate is demonstrably clean, the WP is otherwise ready.
