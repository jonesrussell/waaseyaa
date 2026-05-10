# Tasks: Auto-sync internal `waaseyaa/*` version constraints at release time

**Mission:** `composer-internal-version-sweep-01KR96NA`
**Branch contract:** planning base `main` → merge target `main` (mission branch `kitty/mission-composer-internal-version-sweep-01KR96NA`)

See `spec.md` for goal and acceptance criteria, `plan.md` for inventory, design decisions, and verification matrix.

---

## Subtask Index

| ID   | Description                                                                                                  | WP   | Parallel |
|------|--------------------------------------------------------------------------------------------------------------|------|----------|
| T010 | Create `bin/lib/internal-version-sync.php` with `resolveCurrentVersion()`, `expectedConstraint()`, `findInternalDeps()` | WP01 | [D]      | [D] |
| T011 | Create `bin/sync-internal-versions` CLI script (`#!/usr/bin/env php`) wrapping the lib; argv parsing, exit codes, idempotent rewrite via `Composer\Json\JsonFile` | WP01 | [D] |
| T012 | Create test fixtures under `tests/Fixtures/release-tooling/` (manifest with trailing comma, manifest with unusual key order, manifest with `require-dev` siblings) | WP01 | [D] |
| T013 | `tests/Integration/ReleaseTooling/SyncInternalVersionsTest.php`: idempotency, JSON formatting preservation, refusal of `""` / `dev-main` / `self.version` / `^*` / `0.1.x` | WP01 | [D] |
| T014 | Verify WP01 against gates: phpunit (new tests pass), phpstan, cs-check, package-layers, composer-policy (existing rules; CP-NEW lands in WP03) | WP01 | [D] |
| T020 | Add `Sync internal versions` step to `.github/workflows/release-cut.yml` before the CHANGELOG-promotion + commit + tag steps; runs `bin/sync-internal-versions ${{ inputs.version }}` minus the leading `v` | WP02 | [P] [D]  | [D] |
| T021 | Mirror the same step in `scripts/release.sh` (deprecated but kept as fallback): bash function calling the same script with the parsed `$SEMVER` | WP02 | [P] [D]  | [D] |
| T022 | Manual dry-run verification: invoke release-cut.yml against a sandbox tag (or local equivalent); confirm tree shape matches `scripts/release.sh` output | WP02 | [D] |
| T030 | Extend `bin/check-composer-policy` with **CP-NEW**: every `waaseyaa/*` constraint in `packages/*/composer.json` must equal `^<resolveCurrentVersion()>`; reuse `bin/lib/internal-version-sync.php` | WP03 | [D] |
| T031 | Unit tests for CP-NEW: tampered file produces non-zero exit with file path + expected value; matched files produce zero exit | WP03 | [D] |
| T032 | `.github/workflows/ci.yml`: add `fetch-tags: true` (or `fetch-depth: 0`) to the `composer-policy` job's `actions/checkout` step so CP-NEW can resolve the latest tag | WP03 | [D] |
| T033 | Verify on a real PR: open a draft, push, confirm CI's `composer-policy` job sees the tag and CP-NEW passes | WP03 | [D] |
| T040 | Run `bin/sync-internal-versions 0.1.0-alpha.175` (or the current latest tag) against `packages/*/composer.json`; expect 56 files modified, 210 line edits | WP04 | [D] |
| T041 | Run `composer update --lock --no-install --ignore-platform-req=ext-gmp`; commit `composer.lock` alongside the manifest sweep | WP04 | [D] |
| T042 | Single commit: `chore(composer-policy): backfill internal version constraints to ^0.1.0-alpha.175` (adjust tag in subject if a newer alpha was cut between WP03 and WP04) | WP04 | [D] |
| T043 | Local verification: `bin/check-composer-policy` (incl. CP-NEW) passes; `composer phpstan`, `vendor/bin/phpunit`, `composer cs-check` all green | WP04 | [D] |
| T050 | `CLAUDE.md`: add one line under "Composer policy is codified and gated via `bin/check-composer-policy`" pointing at `bin/sync-internal-versions` and CP-NEW | WP05 | [P] [D]  |
| T051 | `CHANGELOG.md` `[Unreleased]` `### Changed`: bullet describing the mechanism (sync script, CP-NEW gate, backfill commit), referencing this mission slug | WP05 | [P] [D]  |
| T052 | Drift-detector pass: stamp any `docs/specs/*.md` that maps to `bin/check-composer-policy` or `scripts/release.sh` if drift-detector flags them | WP05 | [D]      |
| T053 | Mark PR ready for review; verify all hard gates green on the final commit | WP05 | [D]      |

Legend: `[D]` = description finalized. `[P]` = independently parallelizable inside the WP.

---

## WP01 — Sync helper + library + tests

| Subtask | Description |
|---|---|
| T010 | Create `bin/lib/internal-version-sync.php`: `resolveCurrentVersion()`, `expectedConstraint()`, `findInternalDeps()` | [D] |
| T011 | Create `bin/sync-internal-versions` CLI wrapper using `Composer\Json\JsonFile` for round-trip | [D] |
| T012 | Test fixtures (`tests/Fixtures/release-tooling/`): trailing-comma, unusual key order, require-dev sibling | [D] |
| T013 | `tests/Integration/ReleaseTooling/SyncInternalVersionsTest.php`: idempotency, formatting preservation, invalid-input refusal | [D] |
| T014 | Verify WP01 against gates (phpunit, phpstan, cs-check, package-layers, existing composer-policy) | [D] |

---

## WP02 — Release entry-point integration

| Subtask | Description |
|---|---|
| T020 | Add `Sync internal versions` step to `.github/workflows/release-cut.yml` inside the existing concurrency group | [D] |
| T021 | Mirror the step in `scripts/release.sh` (deprecated fallback per #1385) | [D] |
| T022 | Manual dry-run: invoke release-cut.yml against a sandbox; confirm both paths converge to the same tree | [D] |

---

## WP03 — CP-NEW gate + CI tag access

| Subtask | Description |
|---|---|
| T030 | Extend `bin/check-composer-policy` with **CP-NEW**, reusing `bin/lib/internal-version-sync.php` | [D] |
| T031 | `tests/Integration/ReleaseTooling/CpNewCheckTest.php`: tampered file fails; matched files pass | [D] |
| T032 | `.github/workflows/ci.yml`: `fetch-tags: true` on the `composer-policy` job's checkout step (Risk R2) | [D] |
| T033 | Verify on a real PR — CI's `composer-policy` job sees the tag and CP-NEW passes | [D] |

---

## WP04 — Backfill: 56 manifests + composer.lock

| Subtask | Description |
|---|---|
| T040 | Run `bin/sync-internal-versions <current-tag>` against `packages/*/composer.json` (56 files, ~210 line edits) | [D] |
| T041 | `composer update --lock --no-install --ignore-platform-req=ext-gmp`; commit `composer.lock` alongside | [D] |
| T042 | Single commit: `chore(composer-policy): backfill internal version constraints to ^0.1.0-alpha.<NNN>` | [D] |
| T043 | Local verification: `bin/check-composer-policy` (incl. CP-NEW), `composer phpstan`, `vendor/bin/phpunit`, `composer cs-check` all green | [D] |

---

## WP05 — Docs + CHANGELOG + close-out

| Subtask | Description |
|---|---|
| T050 | `CLAUDE.md`: add one line under "Composer policy is codified" pointing at `bin/sync-internal-versions` and CP-NEW |
| T051 | `CHANGELOG.md` `[Unreleased]` `### Changed`: bullet describing the mechanism, referencing this mission slug |
| T052 | Drift-detector pass: stamp specs that map to `bin/check-composer-policy` or `scripts/release.sh` if flagged |
| T053 | Mark PR ready for review; verify all hard gates green on the final commit |

---

## Dependencies

- **WP01** has no dependencies — can start immediately.
- **WP02** depends on WP01 (uses `bin/sync-internal-versions`).
- **WP03** depends on WP01 (shares `bin/lib/internal-version-sync.php`).
- **WP04** depends on WP01 and WP03 (gate must exist before backfill is verified).
- **WP05** depends on WP04 (CHANGELOG describes the now-completed state).

WP02 and WP03 can run in parallel after WP01.

---

## Functional requirement coverage

| FR | Tasks |
|---|---|
| FR-001 | T010, T011, T013 |
| FR-002 | T021 | [D] |
| FR-003 | T020 | [D] |
| FR-004 | T040, T041, T042 |
| FR-005 | T030, T031, T032, T033 |
| FR-006 | T012, T013 |
| FR-007 | T050, T051 |
| FR-008 | T014, T043, T053 |
