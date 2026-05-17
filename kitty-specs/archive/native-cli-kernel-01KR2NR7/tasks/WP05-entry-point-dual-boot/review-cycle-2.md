---
affected_files: []
cycle_number: 2
mission_slug: native-cli-kernel-01KR2NR7
reproduction_command:
reviewed_at: '2026-05-08T04:31:51Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP05
---

# WP05 Review — Cycle 1 (REJECTED)

**Commit reviewed:** `6c499c19e` "feat(WP05): Entry-point + dual-boot adapter"
**Worktree:** `/home/jones/dev/waaseyaa/.worktrees/native-cli-kernel-01KR2NR7-lane-a`

The implementer claims green tests (7446) and the new files exist, but several
DoD items and acceptance criteria do not actually hold when verified against
the real entry point and quality gates. Sending back for fixes.

---

## BLOCKER 1 — Real shipped Symfony commands are NOT actually wired through the dual-boot bridge

**DoD #2:** "All currently-shipped Symfony Console commands continue to run via
the dual-boot adapter (existing Symfony-based command tests still pass)."

Smoke-test from the worktree:

```
$ ./bin/waaseyaa cache:clear
Unknown command: cache:clear

$ ./bin/waaseyaa list
Unknown command: list

$ ./bin/waaseyaa
No commands registered.
```

The WP01 baseline has snapshot fixtures for `cache__clear.help.stdout`,
`audit__log.help.stdout`, `admin__build.help.stdout`, etc. These commands
were registered by Symfony providers via `HasCommandsInterface`. After WP05,
**none** of them are reachable from the entry point.

`CliApplication::main()` builds a registry but the legacy
`HasCommandsInterface` provider iteration is **not happening** in practice —
either `LegacySymfonyCommandRegistrar::registerAll()` is not being called
from `CliApplication`, or the providers are not being discovered, or the
container doesn't have them. The `DualBootTest` integration fixture proves
the *adapter* works in isolation, but does not prove the entry point wires it
up against real providers.

The "FrameworkOperator: same command names, same exit codes, same observable
output" persona contract from spec.md is currently broken. Until
`./bin/waaseyaa cache:clear --help` produces output equivalent (modulo the
WP05 help-renderer differences) to the WP01 baseline snapshot, this WP is
not done.

**Required fix:** Actually wire `LegacySymfonyCommandRegistrar::registerAll(...)`
into `CliApplication::main()` (or the kernel's boot path) using the booted
service provider instances + the real `EntityTypeManager`/`DatabaseInterface`/
`EventDispatcherInterface` from the container. Add an integration test that
runs `bin/waaseyaa <legacy-command-name>` end-to-end via the actual entry
point (not synthetic fixture providers) and compares against the WP01
snapshot.

---

## BLOCKER 2 — `composer cs-check` is RED

DoD #5: "`composer cs-check`, `composer phpstan` clean."

Running it from the worktree:

```
[ERROR] (cs-check) — 5 files require formatting changes
```

Affected files include the WP05-introduced `Compat/LegacySymfonyCommandAdapter.php`
plus collateral churn in `Help/HelpRenderer.php`, `Testing/CliTester.php`,
`CliKernel.php`, `Provider/CliKernelServiceProvider.php` (`fn ()` → `fn()`,
import ordering, named-arg whitespace).

Run `composer cs-fix` then re-verify.

---

## BLOCKER 3 — `composer phpstan` is RED with 23 errors

DoD #5 again. PHPStan is unhappy because the codebase mixes
`Waaseyaa\Cli\…` and `Waaseyaa\CLI\…` namespace casing inconsistently:

```
118  Enum Waaseyaa\Cli\OptionMode referenced with incorrect case: Waaseyaa\CLI\OptionMode.
12   Class Waaseyaa\Cli\CommandRegistry referenced with incorrect case: Waaseyaa\CLI\CommandRegistry.
... (×23 across CliApplication, Compat/, DualBootTest, etc.)
```

`packages/cli/src/OptionMode.php` and `ArgumentMode.php` declare
`namespace Waaseyaa\Cli;` (lower-case `Cli`). The new `Compat/` files,
`CliApplication`, and `DualBootTest` import them as `Waaseyaa\CLI\OptionMode`
(upper-case `CLI`). PHP is case-insensitive at runtime so tests pass, but
PHPStan's `class.nameCase` and `enum.nameCase` rules reject this — and these
checks are part of the gate.

Pick one canonical casing for the package namespace (the rest of the
package uses `Waaseyaa\CLI`) and either rename the `Cli` declarations to
`CLI` or correct the imports. Re-run `composer phpstan` to confirm clean.

(The intelephense "Undefined class constant 'Array_'" diagnostic flagged in
the prompt is a **false alarm**: `OptionMode::Array_` and `ArgumentMode::Array_`
both exist as enum cases. The real defect is the namespace-case mismatch
PHPStan caught above.)

---

## BLOCKER 4 — Native-vs-legacy collision policy contradicts the WP contract

WP05 task `T024` says explicitly:

> `CliKernelServiceProvider` (from WP04) is amended to call
> `LegacySymfonyCommandRegistrar` AFTER iterating native providers, so name
> collisions surface as `DuplicateCommandException` (correct behaviour: a
> command must be ported, not double-registered).

The implementer instead made `LegacySymfonyCommandRegistrar` **silently
swallow** the `DuplicateCommandException` and emit a `$logger->warning(...)`
("Duplicate legacy command … skipped"). The commit message advertises this
as the design ("Duplicate commands skipped with warning (native wins)") and
`DualBootTest::duplicateLegacyCommandIsSkippedNotThrown()` cements it as
spec.

This silently masks porting mistakes — a duplicate command is exactly the
signal that a port is half-done. The WP requires the kernel to throw so the
mistake is loud.

**Required fix:** Either (a) let `DuplicateCommandException` propagate
(matches the WP contract), or (b) if the team genuinely wants
silent-skip-with-warn, update `spec.md`/`tasks/WP05-entry-point-dual-boot.md`
T024 first and surface that change in the activity log so the deviation is
deliberate, not drift.

---

## NON-BLOCKER NOTES (fix while you're in there)

- `bin/waaseyaa` (root) is now a bash shim (`#!/usr/bin/env bash`) that `exec`s
  `packages/cli/bin/waaseyaa`. This is fine for direct execution
  (`./bin/waaseyaa …`), but `php bin/waaseyaa …` no longer works — PHP just
  cats the bash comments. WP01 baseline was a PHP file. Operators who scripted
  `php bin/waaseyaa …` (CI, Deployer, cron) will silently break with exit 0
  and zero output. Either keep `bin/waaseyaa` as a PHP file that requires the
  package script, or document the shim contract change in `occurrence_map.yaml`
  reasoning and the FrameworkOperator persona note in spec.md.
- No kill switch for the legacy bridge. The dual-boot contract (C-006) calls
  for the bridge to be removed in WP23, but until then it should be runtime-
  toggleable (env var, e.g. `WAASEYAA_CLI_LEGACY_BRIDGE=0`) so operators can
  spot-check what would happen post-WP23. Add a single `getenv()` guard around
  the `registerAll()` call.
- Adapter files DO carry the `@internal Deleted in WP23` docblock — good.
- `symfony/console` still in `composer.json` and `packages/cli/composer.json`
  — correct, WP23 owns removal.
- DualBootTest line 144: the `EventDispatcher` type juggling
  (`SymfonyEventDispatcherAdapter` to satisfy two contracts) is correct;
  the prompt's intelephense flag was a false alarm.
- Main repo (`/home/jones/dev/waaseyaa`) is clean of WP05 file leakage —
  only stray `csrf_upload_*` / `php*` temp files unrelated to this WP.

---

## Required actions before resubmit

1. Wire the legacy registrar into the real `CliApplication`/kernel boot so
   `./bin/waaseyaa cache:clear --help` (and the rest of the WP01 snapshot
   set) produce equivalent output. Add an end-to-end test using the real
   entry point.
2. `composer cs-fix` && `composer cs-check` clean.
3. Resolve the `Cli` vs `CLI` namespace casing across the package; `composer
   phpstan` clean.
4. Either honour T024's `DuplicateCommandException` semantics or amend the
   WP contract first.
5. Decide and document the `bin/waaseyaa` bash-vs-php shim contract change.
6. (Recommended) Add an env-var kill switch for the legacy bridge.

When all six are addressed, push and re-request review.
