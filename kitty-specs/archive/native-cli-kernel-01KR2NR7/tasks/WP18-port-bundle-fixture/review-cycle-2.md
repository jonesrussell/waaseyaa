---
affected_files: []
cycle_number: 2
mission_slug: native-cli-kernel-01KR2NR7
reproduction_command:
reviewed_at: '2026-05-08T15:55:57Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP18
---

# WP18 Review — Cycle 1: REJECTED

Commit reviewed: `a62a987a44642cf4727d728759042c0376c384b6`
Reviewer: spec-kitty-runtime-review (opus)
Date: 2026-05-08

## Verdict

**REJECTED.** Two blocking issues, both rooted in an incorrect premise: the implementer asserted the four ported commands had no WP01 baseline fixtures, but three of them clearly do — under the canonical `__` (double-underscore) naming convention used everywhere else in the snapshot suite.

## Blocking issues

### B1. Fixture naming convention violation (`_` instead of `__`)

The established convention in `packages/cli/tests/Fixtures/snapshots/` is **double underscore** as the `:` separator for multi-segment command names. Evidence from the live snapshot directory:

```
admin__build.help.{exit,stderr,stdout}
admin__dev.help.{exit,stderr,stdout}
audit__log.help.{exit,stderr,stdout}
cache__clear.help.{exit,stderr,stdout}
config__export.help.{exit,stderr,stdout}
config__import.help.{exit,stderr,stdout}
db__init.help.{exit,stderr,stdout}
debug__context.help.{exit,stderr,stdout}
entity__create.help.{exit,stderr,stdout}
entity__list.help.{exit,stderr,stdout}
entity-type__list.help.{exit,stderr,stdout}
event__list.help.{exit,stderr,stdout}
health__check.help.{exit,stderr,stdout}
health__report.help.{exit,stderr,stdout}
ingest__dashboard.help.{exit,stderr,stdout}
...
```

WP18 introduced single-underscore variants:

```
fixture_generate.help.stdout
fixture_pack_refresh.help.stdout
fixture_scaffold.help.stdout
scaffold_bundle.help.stdout
```

This breaks the convention and is inconsistent with WP02–WP17 work in the same lane. **Required fix:** rename WP18 fixtures to use `__`:
- `scaffold_bundle.*` → `scaffold__bundle.*` (this command was new — confirm; see B2)
- `fixture_generate.*` → align with existing `fixture__generate.*` (already exists — see B2)
- `fixture_pack_refresh.*` → align with existing `fixture__pack__refresh.*` (already exists — see B2)
- `fixture_scaffold.*` → align with existing `fixture__scaffold.*` (already exists — see B2)

### B2. False "no baseline" claim — orphan fixtures created

The commit message states the fixtures are "not in WP01 baseline a923be435", but the current worktree's snapshot directory already contains:

- `fixture__generate.help.{exit,stderr,stdout}`
- `fixture__pack__refresh.help.{exit,stderr,stdout}`
- `fixture__scaffold.help.{exit,stderr,stdout}`

(`scaffold__bundle.*` was not visible in the listing — it may indeed be new.)

Whether these existed in `a923be435` itself or were added by an earlier WP in the same lane is moot: they exist *now*, they follow the canonical `__` convention, and they include the full `.exit/.stderr/.stdout` triplet that the snapshot harness expects. WP18 created **parallel single-underscore stubs** containing only `.stdout`, leaving the original `__` files untouched as orphans referenced by older tests (or worse, doubly asserted).

**Required fixes:**
1. Confirm whether `scaffold__bundle.*` is genuinely missing — if so, add it (full triplet) under the `__` convention.
2. For the three already-existing fixtures, **reuse the existing `__` files** rather than creating `_` duplicates. Update `BundleFixtureSnapshotTest` to point at the canonical names.
3. Either delete the four newly added `_` files, or — if regeneration changed expected output — overwrite the existing `__` files (with full `.exit/.stderr/.stdout` triplets, not stdout-only).
4. Re-verify byte-parity for the snapshot suite after rename.

## Non-blocking observations

- HelpRenderer untouched. ✅
- phpstan-baseline.neon: net -42 lines (deletions only per commit message). ✅
- Main repo contamination: only the expected ignored noise (`perf-baseline-*`, `waaseyaa_audit_handler_*`). ✅
- Handler/Provider structure (BundleFixtureServiceProvider wiring four handlers) follows the WP02–WP17 pattern. ✅
- IngestionFixturePackRegressionTest migration to CliTester + handler is consistent with other ports. ✅

## Path to approval

1. Rename / consolidate the four WP18 fixtures to `__` convention.
2. Ensure each command has the full `.exit/.stderr/.stdout` triplet, not stdout-only.
3. Remove orphan `_` files.
4. Update `BundleFixtureSnapshotTest` assertions to canonical names.
5. Re-run `./vendor/bin/phpunit packages/cli/tests/Integration/Snapshot/` and confirm byte-parity.
6. Re-run full phpunit + phpstan + ghost-import grep.
7. Push amended commit; request re-review.

Dependency note: WP23 depends on WP18; notify that lane to rebase once approved.
