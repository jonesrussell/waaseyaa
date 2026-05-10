# Spec: Auto-sync internal `waaseyaa/*` version constraints at release time

**Mission ID:** `01KR96NAPQY0RSTKMY286NV16W`
**Slug:** `composer-internal-version-sweep-01KR96NA`
**Mission type:** `software-dev`
**Target branch:** `main`
**Created:** 2026-05-10

---

## Why

Every per-package `composer.json` in `packages/*/` hard-pins its internal
`waaseyaa/*` siblings to the literal `^0.1.0-alpha.150`. Today the latest
release is alpha.176 (post-PHP-8.5 merge `e0f8cb570`), so every package's
internal constraint is **26 alpha bumps stale** and counting.

The constraint isn't broken — `^0.1.0-alpha.150` resolves to
`>=0.1.0-alpha.150 <0.2.0`, so newer alphas and the eventual `0.1.0`
release all satisfy it. But it is **misleading documentation**: it implies
"this package was last verified to work with siblings at alpha.150", which
hasn't been true since 2025-12 or so. It also drifts further every release,
which is the opposite of what version constraints should do under a healthy
release process.

Root `composer.json` solves this with `self.version` for every sibling — at
publish time Packagist crawls it and resolves `self.version` to the exact
tag (e.g. `0.1.0-alpha.176`). That trick is unavailable to per-package
manifests because **CP006 forbids `self.version` outside the root**: each
package's manifest is published as its own Packagist entry and a
`self.version` string there would resolve to *that package's* version, not
the framework release.

The durable fix is to update the per-package literal in lock-step with the
release. `scripts/release.sh` (and/or the `release-cut.yml` workflow) is
already the choke point that bumps the root version and the changelog; it
should also do a one-line sweep across `packages/*/composer.json` to update
the literal to match the just-cut tag. After this mission lands, every
release will leave the per-package internal constraints exactly matching
the new tag — same shape as today, fresh contents.

This is bookkeeping plumbing, not a behavior change. Existing alphas and
the 0.1.0 release will continue to work for downstream consumers either
way.

---

## Goal

Make the per-package internal `waaseyaa/*` version literal advance
automatically with every release tag, and bring the current state up to
date as the first run of the new tooling.

---

## Non-goals

- Replacing the literal pattern with anything else. CP006 forbids
  `self.version` per-package; CP003 forbids wildcards (`*`, `dev-*`).
  Stay on the `^X.Y.Z[-prerelease]` shape.
- Changing root `composer.json` — `self.version` is correct there.
- Promoting `[Unreleased]` CHANGELOG content (`release-cut.yml` already
  owns that step).
- Adding new release-time validation gates beyond the sweep itself.
- Multi-track release support (no current need).

---

## User scenarios

- **Release manager** runs `scripts/release.sh 0.1.0-alpha.177` (or the
  workflow equivalent). The script bumps the root version, sweeps every
  `packages/*/composer.json` internal `waaseyaa/*` literal to
  `^0.1.0-alpha.177`, regenerates `composer.lock`, runs hard gates, and
  commits in one logical step. The release tag points at a tree where
  every per-package internal constraint matches the tag.
- **Downstream consumer** installs `waaseyaa/foo` directly (not via the
  framework metapackage) and Composer resolves siblings correctly because
  the literal is current.
- **Reader** opening any `packages/*/composer.json` sees an internal
  constraint that documents the *current* compatible-with floor, not a
  six-month-stale historical artifact.
- **CI on a feature branch** that touches one package's manifest doesn't
  have to bump the literal mid-mission — the sweep is a release-time-only
  step. Branches in flight before a release inherit the new literal at
  merge → release boundary.

---

## Functional requirements

- **FR-001** — A `bin/sync-internal-versions` script (or equivalent
  invocation already present in `scripts/release.sh`) accepts a target
  version string (e.g. `0.1.0-alpha.177`) and rewrites every internal
  `waaseyaa/*` constraint across `packages/*/composer.json` to
  `^<target>`. Idempotent — re-running with the same version is a no-op.
- **FR-002** — `scripts/release.sh` invokes the sync as part of the
  cut-a-release flow, before regenerating `composer.lock` and committing.
- **FR-003** — `release-cut.yml` either inherits this via
  `scripts/release.sh` or invokes the sync directly; whichever entry point
  is canonical, both paths must end with the same end state.
- **FR-004** — Backfill: as part of this mission, run the sync once
  against the *current* unreleased state to advance the literal from
  `^0.1.0-alpha.150` to the current alpha (or the next one to be cut).
  This produces a one-time mechanical commit that updates ~60 files.
- **FR-005** — `bin/check-composer-policy` is extended (or a new check
  added) to enforce **CP-NEW**: every internal `waaseyaa/*` constraint
  across `packages/*/composer.json` must use the same literal value, and
  that literal must match the root `composer.json` `version` field
  (modulo the caret prefix). Run as a hard gate in `ci.yml`. Drift
  between root and per-package becomes a CI failure, so missing the sync
  at release time is caught.
- **FR-006** — Unit tests for the sync script: idempotency, refusal on
  invalid version strings (rejects `*`, `dev-*`, `self.version`,
  whitespace, empty), correct caret-prerelease behavior, JSON formatting
  preserved.
- **FR-007** — Documentation: `CLAUDE.md`'s composer-policy discussion
  gains a one-line note pointing at the sync script and CP-NEW. The
  CHANGELOG `[Unreleased]` entry describes the mechanism.
- **FR-008** — All hard-gate verification commands pass:
  `bin/check-composer-policy` (extended), `bin/check-package-layers`,
  `composer phpstan`, `vendor/bin/phpunit`, `composer cs-check`,
  `tools/drift-detector.sh`. CI matrix unchanged.

---

## Quality gates

- `bin/check-composer-policy` with the new CP-NEW rule — green.
- `bin/check-package-layers` — green (no layer changes expected).
- `composer phpstan` — green.
- `vendor/bin/phpunit` — green; new sync-script tests pass.
- `composer cs-check` — green.
- `tools/drift-detector.sh` — green; stamp specs that map to
  `scripts/release.sh` or `bin/check-composer-policy` if any.

---

## Acceptance criteria

- **AC-001** — A fresh `git grep -F '^0.1.0-alpha' packages/*/composer.json`
  on `main` after the backfill commit shows a single value across all
  matches, equal to the current alpha.
- **AC-002** — Tampering with one package's literal (e.g. manually
  editing `packages/access/composer.json` to `^0.1.0-alpha.99`) makes
  `bin/check-composer-policy` exit non-zero with a CP-NEW failure citing
  the offending file and the expected value.
- **AC-003** — Running the sync twice with the same target version
  produces no diff on the second run.
- **AC-004** — Running the sync with an invalid argument (`""`,
  `dev-main`, `self.version`, `^*`, `0.1.x`) exits non-zero with an
  actionable error message; no files are modified.
- **AC-005** — A simulated release-cut (real `scripts/release.sh`
  invocation against a sandbox branch, or `--dry-run` mode if added)
  produces a tree where root `version` and every per-package internal
  constraint match the cut version.

---

## Implementation hints

- The literal lives in many positions per file (every `require` /
  `require-dev` entry that names another `waaseyaa/` package). A regex
  over each manifest is sufficient — there are no other constraint
  shapes today — but the sync should preserve JSON formatting (spaces,
  trailing-comma rules). Prefer a real JSON load → mutate → dump
  round-trip with the project's existing manifest-handling tooling, not
  raw sed.
- `scripts/release.sh` is bash; the sync helper can be a small PHP
  script invoked from there. Keeping it in PHP lets
  `bin/check-composer-policy` share the same parser/validator logic.
- CP-NEW's cross-file consistency check is the most novel piece. Every
  other CP rule is a per-file invariant; CP-NEW compares all manifests
  against root. Implementation: load root version once, compare every
  per-package manifest's `waaseyaa/*` constraints against `^<root>`.
- The three metapackages (`packages/cms`, `packages/core`, `packages/full`)
  DO declare internal `waaseyaa/*` deps and they all currently pin to
  the same `^0.1.0-alpha.150` literal — they ARE in scope for the sweep.
  What CP006 says they don't have is a `php` key. Don't conflate the
  two policies.
- Watch for `require-dev` entries naming siblings (e.g. testing fixtures)
  — those use the same shape and should sweep alongside `require`.

---

## Out of scope (filed-for-later)

- Replacing `^0.1.0-alpha.150`-style constraints with `~` or `>=` shapes.
- Multi-track release support (e.g. backporting fixes to alpha.150 while
  alpha.180 is current).
- Changing how root `composer.json` advertises siblings. `self.version`
  stays.
- Removing the literal pattern entirely in favor of some new Composer
  feature (we're not aware of one that beats the current shape under
  CP003 + CP006).
