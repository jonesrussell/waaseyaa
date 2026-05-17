---
affected_files: []
cycle_number: 2
mission_slug: config-management-v1-01KRCDEC
reproduction_command:
reviewed_at: '2026-05-17T00:17:17Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP03
---

# WP03 — Review Cycle 1 — REJECT

**Commit:** 300473c8c
**Reviewer:** opus reviewer
**Date:** 2026-05-16
**Verdict:** REJECT

## Blocking issue: inline interface breaks PSR-4 autoloading

`ConfigSyncFileSourceInterface` is declared inline inside
`packages/config/src/Sync/ConfigExporter.php` (lines 121–127), alongside two
other public types (`ConfigExportFileResult`, `ConfigExportResult`).

The implementer's docblock (lines 16–23) acknowledges this and rationalises it
as "PSR-4 still autoloads them as a side effect of resolving `ConfigExporter`."

**That rationalisation is false.** Composer's PSR-4 autoloader maps an FQCN to a
specific file path: it expects
`Waaseyaa\Config\Sync\ConfigSyncFileSourceInterface` to live in
`packages/config/src/Sync/ConfigSyncFileSourceInterface.php`. When the
interface is referenced before `ConfigExporter` has been autoloaded, the
autoloader cannot find it.

### Reproduction

Run **only** the CLI command test (without the ConfigExporterTest preload):

```
./vendor/bin/phpunit packages/cli/tests/Unit/Command/Config/
```

Output:

```
Tests: 4, Assertions: 0, Errors: 4, PHPUnit Warnings: 1.
Error: Interface "Waaseyaa\Config\Sync\ConfigSyncFileSourceInterface" not found
```

All four `ConfigExportCommandTest` tests fail with autoload errors.

### Why the implementer's run looked green

Running both test files together (`packages/config/tests/Unit/Sync/...` plus
`packages/cli/tests/Unit/Command/Config/`) loads `ConfigExporterTest.php` first
alphabetically, which `use`s `Waaseyaa\Config\Sync\ConfigExporter`, which
triggers Composer to require `ConfigExporter.php`, which **side-loads** the
inline interface into the class table. When the CLI tests subsequently
reference `ConfigSyncFileSourceInterface`, it is already declared in memory and
the test passes.

This is brittle — it depends on alphabetical test-file ordering. The full
suite run (`./vendor/bin/phpunit`) also exhibits the failure (4 errors
attributable to WP03, plus 1 unrelated PublicSurfaceVerificationTest failure
that is not WP03's responsibility).

### Required fix

Split `ConfigExporter.php` into one-class-per-file under
`packages/config/src/Sync/`:

- `packages/config/src/Sync/ConfigExportFileResult.php`
- `packages/config/src/Sync/ConfigExportResult.php`
- `packages/config/src/Sync/ConfigSyncFileSourceInterface.php`
- `packages/config/src/Sync/ConfigExporter.php` (orchestrator only)

The WP03 `owned_files` manifest is a planning artifact, not a PSR-4 constraint.
Adding the three new files does not introduce scope creep — they are the four
public types this WP is responsible for, just in their canonical locations.
If a manifest update is needed, add them to `owned_files` in
`WP03-config-export.md` (same task surface).

## Non-blocking observations

1. **CLI shape matches `contracts/cli-namespace.md`** — flags (`--diff`,
   `--dry-run`), exit codes (0 / 1), per-file `<verb> <filename>` output, and
   the `X created, Y updated, Z unchanged.` summary line all conform.
2. **Audit-log entry deferred** — the contract specifies a
   `config.audit` channel log line on success; this WP does not emit it.
   That is acceptable if a downstream WP wires the audit channel (the
   contract lists it across every `config:*` verb); confirm and add a FR
   forward-reference if so, otherwise add it here.
3. **Scope clean** — exactly the 4 owned files modified; no leakage.
4. **FR coverage in tests** — once the autoload issue is fixed, FR-017
   through FR-021 are individually exercised by named tests (
   `fresh_export_prints_created_lines_and_zero_exit`,
   `dry_run_does_not_write_files_and_marks_output`,
   `summary_line_matches_canonical_format`, etc.).
5. **PHPStan / cs-check / layers — all clean** (level 5; package layers
   green; no upward `waaseyaa/*` edges).

## Re-review checklist

- [ ] Split the inline types into one file per FQCN.
- [ ] Update `WP03-config-export.md` `owned_files` to include the new files
      (if charter-required).
- [ ] Verify `./vendor/bin/phpunit packages/cli/tests/Unit/Command/Config/`
      passes in isolation.
- [ ] Verify `./vendor/bin/phpunit` runs the 15 WP03 tests with zero errors
      attributable to WP03.
- [ ] Re-run `composer phpstan`, `composer cs-check`, `bin/check-package-layers`.
