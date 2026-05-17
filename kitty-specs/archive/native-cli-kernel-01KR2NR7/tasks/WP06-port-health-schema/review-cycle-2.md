---
affected_files: []
cycle_number: 2
mission_slug: native-cli-kernel-01KR2NR7
reproduction_command:
reviewed_at: '2026-05-08T05:14:55Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP06
---

# WP06 Review Feedback (Cycle 1 — REJECT, return to planned)

## Verdict: REJECT — snapshot byte-for-byte parity is broken on all four commands.

The implementation is structurally sound (provider wiring, deletes, baseline cleanup, signature-change ripple all clean), and the four gates are GREEN, but the **WP01 snapshot-parity contract is violated on every single command in the cluster**. The integration tests pass because they assert metadata via HelpRenderer rather than diffing against the captured fixtures — exactly the failure mode the WP01 contract was written to prevent.

## Hard evidence (run from worktree at `13750b212`)

For each of `health:check`, `health:report`, `schema:check`, `schema:list`:

```
$ diff <(bin/waaseyaa <cmd> --help) packages/cli/tests/Fixtures/snapshots/<cmd>__help.help.stdout
EXIT=1   # all four
```

Representative diff (`health:check --help`, identical pattern on the other three):

- **Option set drift.** Live native output emits a stripped option block:
  - `--no-interaction`, `-q, --quiet "Do not output any message"`, `-v, --verbose`, `--version`
- Captured fixture (Symfony legacy) emits the full block:
  - `--silent`, `-q, --quiet "Only errors are displayed. All other output is suppressed"`, `-V, --version`, `--ansi|--no-ansi`, `-n, --no-interaction`, `-v|vv|vvv, --verbose "Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug"`
- **Layout drift.** Live output puts `Usage:` before `Description:`; fixture puts `Description:` first then `Usage:` (blank-line ordering differs at lines 1–6).
- **Short-flag drift.** Live output is missing `-V` (version), `-vv`, `-vvv`, and the `--ansi|--no-ansi` line entirely.

These are not cosmetic — they change what scripts/operators can pipe into `--help` parsers, and they break the WP01 contract clause: *"verify byte-for-byte parity against the captured fixtures at `packages/cli/tests/Fixtures/snapshots/{cmd}.help.stdout`."*

The integration tests added in this WP (`HealthCheckSnapshotTest`, `HealthReportSnapshotTest`, `SchemaCheckSnapshotTest`, `SchemaListSnapshotTest`) assert via the `HelpRenderer` API surface, not via a diff against the on-disk `*.help.stdout` files, so they all pass while the actual byte-for-byte fixtures fail.

## Other items (all PASS — retain on next cycle)

- **Dual-boot collision avoided.** Four legacy command files deleted (`HealthCheckCommand`, `HealthReportCommand` via rename, `SchemaCheckCommand`, `SchemaListCommand`). `grep -rn 'HealthCheckCommand|HealthReportCommand|SchemaCheckCommand|SchemaListCommand' packages/ --include='*.php'` returns no dangling references. `bin/waaseyaa list` shows each of the four names exactly once.
- **CliCommandRegistry signature ripple.** `grep -rn 'coreCommands\|new CliCommandRegistry' packages/` confirms all callers updated; ConsoleKernel call sites match new signature; no compile errors.
- **PHPStan baseline drains correctly.** Diff shows ONLY DELETIONS (6 entries removed for the four deleted command classes) — no new suppressions added.
- **Gates all GREEN:** `composer cs-check` exit 0; `composer phpstan` `[OK] No errors`; `./vendor/bin/phpunit` 7455 tests / 17937 assertions (no regressions vs. WP05 baseline of 7222+233).
- **No main-repo contamination.** `cd /home/jones/dev/waaseyaa && git status -s` clean of foundation/cli leakage.
- **No out-of-scope changes.** Diff touches only health/schema cluster + signature ripple + baseline.
- **Commands run.** `bin/waaseyaa health:check; echo $?` produces the expected human-readable output and exits 0; `--json` produces well-formed JSON. (Behavioral parity not exhaustively diffed against legacy because legacy is deleted — acceptable per contract.)

## Required to clear (next cycle)

The native rendering pipeline (`HelpRenderer` and the application-level option registration in `CliKernel`) is producing a different `--help` shape than Symfony Console did. Two acceptable paths:

1. **Make `HelpRenderer` produce Symfony-equivalent help.** Wire the standard application options (`--silent`, `--ansi|--no-ansi`, `-V`/`--version`, `-n`/`--no-interaction`, `-vv`/`-vvv` verbosity tiers) and match the Description→Usage→Options block ordering and the long verbosity description. Keep the captured fixtures byte-frozen as the source of truth.
2. **Re-baseline the fixtures and document the deviation.** If WP01 contract intent is "match new native shape," update all `*.help.stdout` fixtures in this WP, **and** update the WP01 contract / occurrence_map to record that the native shape supersedes the Symfony shape. This is a wider blast radius — it affects every other ported cluster (WP07–WP22) too — so it is likely the wrong call.

Whichever path is chosen, **the snapshot tests must diff against the on-disk `*.help.stdout` files byte-for-byte, not just exercise `HelpRenderer` metadata**. A passing test must imply `diff <(bin/waaseyaa X --help) fixture` returns exit 0.

## Gates to re-run on next cycle

- `composer cs-check`
- `composer phpstan`
- `bin/check-composer-policy`
- `bin/check-package-layers`
- `./vendor/bin/phpunit`
- `tools/drift-detector.sh`
- **NEW (must add):** `for c in health:check health:report schema:check schema:list; do diff <(bin/waaseyaa $c --help) "packages/cli/tests/Fixtures/snapshots/$(echo $c | tr ':' '_')_.help.stdout" || exit 1; done` — must exit 0.

## Pointers

- Live shim: `bin/waaseyaa` → `packages/cli/bin/waaseyaa` (correct; PHP-invocation via `php bin/waaseyaa` does NOT work because the shim is `#!/usr/bin/env bash`; use `bin/waaseyaa <cmd>` directly).
- Native handler dir: `packages/cli/src/Handler/{HealthCheck,HealthReport,SchemaCheck,SchemaList}Handler.php`.
- Provider: `packages/cli/src/Provider/HealthSchemaServiceProvider.php` (correctly implements `HasNativeCommandsInterface`, registered in `packages/cli/composer.json`).
- HelpRenderer (likely the place to fix): grep `class HelpRenderer` under `packages/cli/src/`.
- Application option registration: `packages/cli/src/CliKernel.php` (or wherever `CommandDefinition` global options are seeded).
