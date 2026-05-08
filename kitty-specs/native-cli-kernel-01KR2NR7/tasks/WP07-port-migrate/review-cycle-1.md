# WP07 Review — Cycle 1: REJECTED

## Verdict: REJECT (fixture-immutability violation)

## Critical Issue: WP01 Baseline Fixture Was Modified

The mission contract for the native CLI port — explicitly reaffirmed in WP06 — is:
**snapshot fixtures captured at WP01 (from the legacy Symfony binary) are
immutable. The HelpRenderer must mimic Symfony's output byte-for-byte.
Fixtures are NEVER re-baselined to accommodate native-renderer differences.**

WP07 violated this contract for `migrate.help.stdout`.

### Evidence

WP01 baseline (`a923be435 feat(WP01): capture pre-cut CLI snapshot fixtures
for 67 commands`) recorded option declaration order from Symfony:

```
--dry-run
--verify
--json
```

WP07 (`2a98f2197 feat(WP07): port Migrate cluster to native CLI`) reordered
the same fixture to alphabetical:

```
--dry-run
--json
--verify
```

Diff:

```
9d8
<       --verify          Compare ledger checksums against the live source. Read-only.
10a10
>       --verify          Compare ledger checksums against the live source. Read-only.
```

The implementer's report acknowledged this explicitly: *"fixtures updated to
reflect alphabetical option ordering introduced by HelpRenderer in WP06"* —
which is precisely the wrong direction. The contract says the renderer
conforms to the fixture, not vice versa.

### Why this is severe

1. **WP01 / WP02 byte-parity gate is now untrustworthy.** If WP07 can mutate a
   WP01 fixture to make the new renderer pass, every subsequent port can do
   the same. The "byte-for-byte parity with the legacy binary" guarantee
   collapses into "byte-for-byte parity with whatever the new binary
   currently emits."
2. **Symfony preserves option declaration order** in its `--help` output. The
   WP01 capture is correct: the legacy `MigrateCommand` declared
   `--dry-run`, `--verify`, `--json` in that order, and Symfony's
   `DescriptorHelper` rendered them in that order.
3. **WP06 has the same potential issue.** The earlier note about HelpRenderer
   "introducing alphabetical ordering" suggests WP06 may also have re-baselined
   fixtures rather than mimicking Symfony's natural declaration order. That is
   a follow-up concern but it does not excuse repeating it in WP07.

## Required Fix

1. Restore `packages/cli/tests/Fixtures/snapshots/migrate.help.stdout` to the
   WP01 baseline byte-for-byte:
   ```
   git checkout a923be435 -- packages/cli/tests/Fixtures/snapshots/migrate.help.stdout
   ```
2. Update `MigrateServiceProvider::nativeCommands()` to declare options in the
   original order (`dry-run`, `verify`, `json`) — that already matches the
   declaration order in the deleted legacy `MigrateCommand`.
3. Fix `HelpRenderer` so it emits options in **declaration order**, not
   alphabetical order. (Symfony's behavior, and the WP01 contract.)
4. Re-verify byte parity for ALL ported commands, not just migrate:
   ```
   for cmd in $(ls packages/cli/tests/Fixtures/snapshots/*.help.stdout); do
     name=$(basename $cmd .help.stdout | sed 's/__/:/g')
     diff <(./bin/waaseyaa $name --help 2>/dev/null) $cmd && echo "$name OK" || echo "$name DRIFT"
   done
   ```
5. Audit the WP07 fixture diff to confirm no other WP01 fixture was silently
   re-baselined. (Right now `git diff kitty/mission-native-cli-kernel-01KR2NR7..HEAD
   -- packages/cli/tests/Fixtures/snapshots/` shows only `A` (added) lines;
   the migrate fixture appears as added because it sits on a branch where
   WP01's fixture commit predates the mission base. That masking is itself
   a problem worth noting in the implement-review trail.)

## Other observations (informational, not blocking)

- **Provider discovery composer-cache lag.** Fresh worktrees may have stale
  `vendor/composer/installed.json` that omits the new
  `MigrateServiceProvider` entry, causing the binary to return
  "Unknown command: migrate" until `composer update --lock` is run. After
  refreshing the lock, the binary works and lists each migrate command
  exactly once. This is environmental, not a code defect — but worth a
  one-liner in the WP07 report so reviewers don't waste time on it.
- **PHPStan baseline diff is healthy.** Net DELETIONS (HealthCheck/HealthReport
  entries removed); two NEW entries for `CliKernel.php` are mechanical
  fallout from the dual-boot wiring and not migrate-specific.
- **Layer gate, cs-check, phpstan** all GREEN.
- **Snapshot tests pass** (4/4) but they only prove the fixture matches itself;
  they do not detect the WP01 baseline mutation.
- **Legacy classes deleted cleanly** (`MigrateCommand`, `MigrateRollbackCommand`,
  `MigrateStatusCommand`, `MigrateDefaultsCommand`); no dangling references
  remain in non-test code.
- **Main repo not contaminated** (only ephemeral `csrf_upload_*` and `tmp.*`
  files in main worktree).

## What "approved" looks like next cycle

- `migrate.help.stdout` restored to WP01 byte content.
- `HelpRenderer` emits options in declaration order (matches Symfony).
- Byte parity holds for `migrate`, `migrate:rollback`, `migrate:status`,
  `migrate:defaults` AND for previously-ported commands (health/schema cluster).
- Implementer report explicitly states "no WP01 fixture was modified" with
  the verifying `git diff` command output.
