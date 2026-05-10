# Plan: Auto-sync internal `waaseyaa/*` version constraints at release time

**Mission:** `composer-internal-version-sweep-01KR96NA` (`software-dev`)
**Target branch:** `main`
**Mission branch:** `kitty/mission-composer-internal-version-sweep-01KR96NA`

See `spec.md` for goal, FRs, ACs, and constraints. This document is the
implementation plan: critical files, sequencing, design decisions, and
verification matrix.

---

## Inventory (verified during plan-mode exploration on 2026-05-10)

| Surface | Count | Current value | Target |
|---|---:|---|---|
| `packages/*/composer.json` files containing `^0.1.0-alpha.150` | **56** | `^0.1.0-alpha.150` | `^<latest-tag>` |
| Total internal `waaseyaa/*` constraint lines across those files | **210** | `^0.1.0-alpha.150` | `^<latest-tag>` |
| Root `composer.json` `version` field | — | **absent** | leave absent (uses `self.version` mechanism) |
| Latest git tag | — | `v0.1.0-alpha.175` | (reference point only — backfill will use this or its successor) |
| Canonical release entry point | — | `.github/workflows/release-cut.yml` (#1385) | extend |
| Fallback release entry point | — | `scripts/release.sh` (DEPRECATED but kept) | extend in lockstep |
| Composer policy gate | — | `bin/check-composer-policy` | extend with CP-NEW |
| CI wiring for the gate | — | `.github/workflows/ci.yml` `composer-policy` job | inherits via the gate |

**Key fact**: root `composer.json` has **no `version` field**. The framework's
"current version" lives in **git tags** (e.g. `v0.1.0-alpha.175`).
`self.version` works at the root because Packagist substitutes the tag at
crawl time. CP-NEW therefore needs a different reference than "root
composer.json `version`" (as the spec hinted): it must compare per-package
literals against the **most recent annotated git tag** (or a release-time
sentinel — see Design Decisions below).

---

## Critical files

### Sync helper (WP01)

- `bin/sync-internal-versions` (new) — PHP script, hashbang `#!/usr/bin/env php`
- `bin/lib/internal-version-sync.php` (new) — extracted core for shared use
  by `bin/check-composer-policy`
- `tests/Integration/ReleaseTooling/SyncInternalVersionsTest.php` (new) —
  idempotency, invalid-input refusal, JSON formatting preservation
- `tests/Fixtures/release-tooling/` (new) — small per-test fixture manifests

### Release entry-point integration (WP02)

- `.github/workflows/release-cut.yml` — add a `Sync internal versions` step
  before the CHANGELOG promotion + commit + tag steps
- `scripts/release.sh` — mirror the same step; both paths must end at the
  same tree shape

### Policy gate extension (WP03)

- `bin/check-composer-policy` — extend with **CP-NEW**: every internal
  `waaseyaa/*` constraint across `packages/*/composer.json` must be
  `^<reference>` where `<reference>` matches the resolved current version
  (see Design Decisions)
- `.github/workflows/ci.yml` — verify `composer-policy` job has tag access
  (see Risk R2)

### Backfill (WP04)

- All 56 `packages/*/composer.json` files — single mechanical commit
  produced by running `bin/sync-internal-versions <tag>` against the
  current state, then `composer update --lock --no-install` to refresh
  the lockfile

### Docs + CHANGELOG (WP05)

- `CLAUDE.md` — add one line under the "Composer policy is codified" bullet
  pointing at the sync script and CP-NEW
- `CHANGELOG.md` `[Unreleased]` — new bullet describing the mechanism
- `docs/specs/workflow.md` — if it references release ritual, add CP-NEW
  to the cut-release checklist; otherwise leave alone

---

## Design decisions

### D1: Reference for CP-NEW comparison

CP-NEW needs to know "what version should the per-package literal point
at?" Three candidates:

1. **Latest annotated git tag matching `v[0-9]+.[0-9]+.[0-9]+(-.+)?`** —
   simplest. CP-NEW runs `git describe --tags --abbrev=0 --match='v*.*.*'`
   and strips the leading `v`. After backfill, the literal matches; after
   each release-cut, the new tag is created and the per-package literals
   were already updated to it (FR-002), so the gate stays green.
2. A repo-tracked **`VERSION` / `version.txt`** file that release-cut.yml
   writes alongside the tag. Cleaner CI ergonomics (no git history needed
   in shallow clones), but adds an artifact whose only job is to mirror
   git tags.
3. Embed the version in **root `composer.json`** explicitly. Smallest
   change in mechanism but **rejected**: it would conflict with the
   `self.version` strategy (Packagist needs the version to come from the
   tag, not the manifest, for `self.version` substitution to work
   correctly across release boundaries).

**Choice: D1.1 (latest git tag)**, with `fetch-tags: true` (or
`fetch-depth: 0`) added to the CI job that runs `composer-policy`.
Detailed cost-benefit can move to `research.md` if the choice is
revisited during WP01.

### D2: Sync helper — sed vs JSON round-trip

Spec says "prefer JSON round-trip, not raw sed." Confirmed:

- The codebase has no precedent for raw sed against composer.json files.
- Composer's own `\Composer\Json\JsonFile` (vendor) preserves formatting
  reliably and is already a dependency; the helper can `require` Composer's
  autoloader and use `JsonFile::read()` / `JsonFile::write()`.
- Tests must include a fixture file with a trailing comma and an
  unusual key order, asserting they're preserved post-sync.

### D3: Sync vs gate — separation of concerns

The sync **mutates**; the gate **detects drift**. Both share the
"what should the literal be?" logic — extracted to
`bin/lib/internal-version-sync.php`:

- `resolveCurrentVersion(): string` — runs git, returns the tag minus `v`.
- `expectedConstraint(string $version): string` — returns `"^$version"`.
- `findInternalDeps(array $manifest): array` — returns the set of
  `waaseyaa/*` keys in `require` and `require-dev`.

Both `bin/sync-internal-versions` and the new CP-NEW check call into
this shared module. Tests cover the module directly; CLI scripts get
integration coverage only.

### D4: Backfill timing and commit shape

WP04 produces one commit on the mission branch:
`chore(composer-policy): backfill internal version constraints to ^0.1.0-alpha.175`.
56 manifest files plus `composer.lock`. No code changes. After this
commit lands on `main`, CP-NEW will pass on every PR until the next
release-cut, at which point release-cut.yml updates everything to the
new tag and CP-NEW continues to pass.

### D5: Branches in flight across a release-cut boundary

After the next release cuts (say `v0.1.0-alpha.177`), CP-NEW will expect
per-package literals to be `^0.1.0-alpha.177`. Any feature branch
created **before** the cut still has `^0.1.0-alpha.176` in its tree;
when it merges to `main`, the merge will succeed (literal is older but
still valid under caret) but **CP-NEW will fail on the next CI run on
main**.

Two ways to handle this:

- (a) CP-NEW runs in advisory/warn-only mode for one alpha cycle after
  each release-cut, then escalates to hard-gate on the next cut.
- (b) Branches that lag behind a release rebase before merging, picking
  up the new literal naturally. Standard practice for any monorepo with
  release-time mutations.

**Choice: (b).** Simpler. The pre-merge rebase requirement is normal
hygiene; CP-NEW's hard-gate behavior surfaces exactly the right alarm
when it's skipped.

---

## Sequencing

| WP | Title | Depends on | Owns |
|---|---|---|---|
| WP01 | Sync helper + tests | (none) | `bin/sync-internal-versions`, `bin/lib/internal-version-sync.php`, related tests |
| WP02 | Release entry-point integration | WP01 | edits to `release-cut.yml` and `scripts/release.sh` |
| WP03 | CP-NEW gate + CI tag access | WP01 (shares lib) | edits to `bin/check-composer-policy`, `ci.yml` |
| WP04 | Backfill | WP01, WP03 | mechanical update of 56 manifests + lockfile |
| WP05 | Docs + CHANGELOG | WP04 | `CLAUDE.md`, `CHANGELOG.md`, `[Unreleased]` bullet |

WP01 and WP03 share `bin/lib/internal-version-sync.php` — WP01 ships the
module; WP03 imports it. Building the gate before the backfill is
intentional: WP04 verifies the gate passes against the newly-synced state
as part of its acceptance.

---

## Verification matrix

| Check | When | Pass condition |
|---|---|---|
| `vendor/bin/phpunit tests/Integration/ReleaseTooling/` | every WP after WP01 | new sync-helper tests pass |
| `vendor/bin/phpunit` (full) | WP04, WP05 | 7497+ tests, 0 failures, 0 deprecations |
| `vendor/bin/phpstan analyse --memory-limit=512M` | every WP | 0 errors |
| `composer cs-check` | every WP | clean |
| `bin/check-composer-policy` | WP03+ | passes including new CP-NEW |
| `bin/check-package-layers` | every WP | passes |
| `tools/drift-detector.sh` | WP04, WP05 | no STALE specs (or stamps applied) |
| `bin/sync-internal-versions <tag>` (idempotency) | WP04 | second run produces no diff |
| `bin/sync-internal-versions ""` / `dev-main` / `self.version` | WP01 tests | exits non-zero, no files changed |
| Manual: tamper one file, run gate | WP03 acceptance | exit non-zero with file path + expected value |

---

## Risks

- **R1: `composer.lock` regeneration cost.** After WP04 the lockfile has
  to be regenerated. Locally this is fast; in CI it's a few seconds.
  Not a real blocker; flagged so reviewers don't flinch at the lockfile
  diff size.
- **R2: Shallow CI clones don't have tags.** `actions/checkout@v4` defaults
  to `fetch-depth: 1` and **does not fetch tags by default**. CP-NEW will
  fail with "no tags found." Mitigation: in `.github/workflows/ci.yml`'s
  `composer-policy` job, add `fetch-tags: true` (or `fetch-depth: 0`).
  Verify on a real PR before WP03 closes.
- **R3: Branches in flight at release-cut time.** See Design Decision D5.
  Surfaces as CP-NEW failures on stale branches; documented in WP05's
  CHANGELOG entry.
- **R4: Race between release-cut.yml and a concurrent merge.** If a PR
  merges between the workflow's "checkout" and "tag push" steps, the
  per-package literal mutation might be pushed atop a tree that already
  diverged. Mitigation: the workflow already uses `concurrency:
  release-cut` (verified in `release-cut.yml`). Adding the sync step
  inside that critical section is sufficient.
- **R5: Spec drift on `bin/check-composer-policy`.** If any `docs/specs/`
  spec maps to that file, drift-detector will flag it after WP03. Stamp
  the affected spec or update it for real.

---

## Out of scope (filed-for-later)

- Replacing the literal pattern with a different shape (`~`, `>=`, etc.).
- Multi-track release support.
- Removing `scripts/release.sh` entirely (deprecated but kept as fallback
  per its own header).
- Changing root `composer.json`'s use of `self.version`.

---

## Estimated size

- WP01: ~200 LOC (script + lib + tests + fixtures)
- WP02: ~30 LOC (one workflow step + one bash function call)
- WP03: ~80 LOC (gate extension + tests + ci.yml fetch-tags)
- WP04: ~210 line edits (mechanical) + 1 lockfile diff
- WP05: ~5 LOC (one CLAUDE.md line + one CHANGELOG bullet)

Total: roughly **one focused PR**, similar in shape to the WP04/WP05 work
in `php-8-5-upgrade-01KR8DN2`. Single lane; no parallel work.
