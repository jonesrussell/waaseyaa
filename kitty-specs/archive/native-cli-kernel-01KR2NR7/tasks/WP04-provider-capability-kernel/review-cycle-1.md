# WP04 Review — Cycle 1 (REJECTED)

**Reviewer:** Opus 4.7
**Commit reviewed:** `446f88ed3`
**Date:** 2026-05-08

## Summary

Implementation is largely sound, but two blocking issues prevent approval.
Both are recoverable with small targeted changes — not a rewrite.

---

## BLOCKER 1 — Worktree-to-main sync hack leaves uncommitted state in main repo

**Severity:** High. This is the highest-risk item flagged in the review prompt
and it is real.

`git status -s` in `/home/jones/dev/waaseyaa` (NOT the worktree) shows:

```
 M packages/foundation/src/Discovery/PackageManifest.php
 M packages/foundation/src/Discovery/PackageManifestCompiler.php
?? packages/foundation/src/ServiceProvider/Capability/HasNativeCommandsInterface.php
```

These are exactly the foundation files modified by commit `446f88ed3` in the
lane-a worktree. The implementer's report acknowledged this: "foundation
changes were synced to main repo so autoloader resolves correctly."

**Why this fails review:**

1. The 7440/7440 green test result is not reproducible from a clean checkout
   of the lane branch. Tests pass only because main-repo working tree is
   contaminated with un-committed copies of the same files.
2. Any other agent operating on main (or a fresh worktree of main) will not
   see `HasNativeCommandsInterface` and will fail to boot when the
   `PackageManifestCompiler` references it via the string constant.
3. Spec Kitty's lane discipline requires that a WP commit be self-contained
   inside its lane worktree. Mutating the main checkout to make tests pass
   violates that contract.

**Required fix:**

- Revert the unstaged/untracked foundation files in `/home/jones/dev/waaseyaa`
  (`git restore packages/foundation/src/Discovery/PackageManifest.php`,
  `git restore packages/foundation/src/Discovery/PackageManifestCompiler.php`,
  `rm packages/foundation/src/ServiceProvider/Capability/HasNativeCommandsInterface.php`).
  Also clean up the unrelated tmp/csrf_upload artifacts shown by `git status`.
- Re-run `composer dump-autoload --optimize` from inside the worktree only,
  and re-run `./vendor/bin/phpunit packages/cli packages/foundation` from the
  worktree to prove tests pass without main-repo contamination.
- If the tests then fail because the worktree's autoloader cannot find
  `HasNativeCommandsInterface`, the fix is to ensure the worktree has its
  own `vendor/composer/autoload_*` files (run `composer dump-autoload` inside
  the worktree, never against main). Do not "sync" sources sideways.

---

## BLOCKER 2 — Namespace casing mismatch in CliKernelServiceProvider

**File:** `packages/cli/src/Provider/CliKernelServiceProvider.php` line 8

```php
use Waaseyaa\CLI\CliKernel;   // <-- uppercase CLI
```

But every other class in the package, including the target itself
(`packages/cli/src/CliKernel.php` line 5), declares
`namespace Waaseyaa\Cli;` (mixed case). All sibling files
(`CommandRegistry`, `CommandDefinition`, `ArgumentDefinition`, `CliIO`,
`OptionMode`, etc.) use `Waaseyaa\Cli`.

PHP's autoloader is case-insensitive on most filesystems, so the runtime
silently resolves it. But:

- intelephense flags it (real signal, not noise — the IDE cannot find the type).
- A future case-sensitive autoloader, a Composer optimization pass, or a move
  to a case-sensitive filesystem will break this import.
- It violates the existing `Waaseyaa\Cli` convention established in WP02.

**Required fix:** change line 8 to `use Waaseyaa\Cli\CliKernel;` and audit
the rest of `CliKernelServiceProvider.php` for any other `Waaseyaa\CLI\*`
imports (the prompt mentioned lines 92 and 113 as well — verify and fix).

---

## Items verified as OK

- `HasNativeCommandsInterface` lives in Foundation L0 with no upward import
  (uses FQN `\Waaseyaa\Cli\CommandDefinition` in docblock only). Layer
  discipline preserved.
- `CliKernel::run(array $argv): int` signature matches R-04 contract.
- `nativeCommands()` capability coexists with the existing
  `HasCommandsInterface` (no removal in this WP — WP23 territory).
- Commit scope is correctly limited to T017–T021; no WP05 entry-point or
  WP23 hard-cut leakage detected in the diff (`git show --name-status
  446f88ed3` shows only the 9 expected files).
- `PackageManifest::nativeCommandProviders` field appears to be added as
  optional/backward-compatible per the commit message (could not fully
  verify line-by-line because the constructor file query returned empty —
  please re-confirm during fix that the named-argument call at compiler
  line 163 matches the actual constructor signature; intelephense flagged it
  and that warning may be a transient parser issue OR a real defect masked
  by the same main-repo file contamination).

---

## Action for implementer

1. Clean main repo working tree (Blocker 1).
2. Fix namespace casing in `CliKernelServiceProvider.php` (Blocker 2).
3. Re-run `composer dump-autoload` and full test suite **from inside the
   worktree only** to confirm green without external state.
4. While you are there: confirm `PackageManifest::__construct` actually
   accepts the `$nativeCommandProviders` named argument used at
   `PackageManifestCompiler.php:163`. If the constructor signature does not
   include it, add it (with a sensible default) so the call is valid even
   under strict static analysis.
5. Amend the WP04 commit (or add a fixup commit) and request re-review.
