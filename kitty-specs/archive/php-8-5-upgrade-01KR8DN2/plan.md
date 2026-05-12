# Plan: Upgrade Waaseyaa to PHP 8.5

**Mission:** `php-8-5-upgrade-01KR8DN2` (`software-dev`)
**Target branch:** `main`
**Mission branch:** `kitty/mission-php-8-5-upgrade-01KR8DN2`

See `spec.md` for goal, acceptance criteria, and constraints. This document is
the implementation plan: critical files, sequencing, verification matrix, and
risks.

---

## Inventory (verified during plan-mode exploration)

| Surface | Count | Current value | Target |
|---|---:|---|---|
| `composer.json` files with `>=8.4` | 57 | `>=8.4` | `>=8.5` |
| `composer.json` files with `^8.4` | 2 (`packages/attachment/`, `packages/structured-import/`) | `^8.4` | `^8.5` |
| `composer.json` with `>=8.3` | 1 (`examples/consumer-test/`) | `>=8.3` | `>=8.5` |
| Root `composer.json` | 1 | `>=8.4` | `>=8.5` |
| GitHub Actions workflows | 3 (`ci.yml`, `skeleton-smoke.yml`, `release.yml`) | `php-version: '8.4'` | `'8.5'` (4 occurrences) |
| Skeleton Dockerfile | 1 (`skeleton/Dockerfile`) | `php:8.4-fpm-alpine` | `php:8.5-fpm-alpine` |
| README PHP references | 3 lines (badge + prose) | `8.4` / `8.4+` | `8.5` / `8.5+` |
| `CLAUDE.md` Code Style | 1 line | `PHP 8.4+` | `PHP 8.5+` |
| `.kittify/charter/charter.md` | 6 lines (already updated this mission) | — | — |
| `phpstan.neon` `phpVersion` | not set | — | `80500` |
| `.php-cs-fixer.dist.php` | no PHP target | — | add `@PHP85Migration` |
| Local PHP runtime | — | 8.5.6 | sufficient for verification |

No `platform.php` config in any composer.json. No `.tool-versions` or
`.php-version` files. No `PHP_VERSION_ID` / `version_compare()` runtime checks
in framework source.

---

## Critical files

### Constraint bump (WP01)

- `composer.json` (root)
- `packages/*/composer.json` × 60
- `examples/consumer-test/composer.json`
- `skeleton/Dockerfile`
- `.github/workflows/ci.yml`, `skeleton-smoke.yml`, `release.yml`
- `phpstan.neon` (add `parameters.phpVersion: 80500`)
- `composer.lock` (regenerate)
- `README.md`, `CLAUDE.md`, `docs/specs/*.md` (grep + edit)

### Deprecation sweep audit zones (WP02)

- `packages/typed-data/src/Primitive/`
- `packages/database-legacy/src/Query/`
- `packages/foundation/src/Ingestion/`, `packages/ingestion/`
- `packages/http-client/`, `packages/foundation/src/Http/`
- `packages/foundation/src/Kernel/` (`HttpKernel.php`, `ConsoleKernel.php`,
  `Bootstrap/Error*` if any)
- `packages/error-handler/`, `packages/debug/`

### `#[\NoDiscard]` targets (WP03)

- `packages/access/src/AccessResult.php`
- `packages/validation/`
- `packages/typed-data/`
- `packages/database-legacy/src/Query/DBALSelect.php` and entity query
  builders in `packages/entity-storage/`
- `packages/api/` `EntityRepository::find*()`

### Adoption hot zones (WP04)

- `packages/foundation/src/Ingestion/` validators
- `packages/typed-data/` transforms
- string-normalization helpers (grep-driven)

### CS-fixer rule + closures (WP05)

- `.php-cs-fixer.dist.php`
- attribute classes under `packages/routing/src/Attribute/`,
  `packages/typed-data/src/Attribute/` (verify locations via grep)

### Changelog + verification (WP06)

- `CHANGELOG.md` `[Unreleased]` section only

---

## Reused utilities and conventions

- `bin/check-composer-policy` — invariant gate for the manifest changes
- `bin/check-package-layers` — runs unchanged; no edges shift
- `bin/audit-dead-code` — baseline-aware, must stay green
- `tools/drift-detector.sh` — last-mile spec drift check
- Per `feedback_regression_tests.md`: every deprecation fix in WP02 ships with
  a regression test
- Per `feedback_changelog_release_workflow.md`: only `[Unreleased]` is edited;
  release-cut.yml promotes at tag time
- Per `feedback_pr_traceability_signals.md`: PR body references mission slug;
  after merge, manually close any tracking issue and edit GitHub Release notes

---

## Work-package sequencing

```
WP01 (constraint bump + CI + Docker + lockfile + phpstan pin + docs + charter)
   │
   ├──► WP02 (8.5 deprecation sweep)         ┐
   ├──► WP03 (#[\NoDiscard] adoption)        │
   ├──► WP04 (pipe / array_first / array_find) ├─ parallel after WP01
   └──► WP05 (closures-in-const + cs-fixer)  ┘
                          │
                          ▼
                    WP06 (CHANGELOG + verification + follow-up issues)
```

WP01 is foundational and lands first. WP02–WP05 can run in parallel
worktrees (or sequentially in this mission, since the work is small enough).
WP06 closes the mission.

---

## Verification matrix (mission-level)

Run after WP06 completes; gates the `accept` step.

| Command | Expected | Gate |
|---|---|---|
| `composer phpstan` | clean at level 5, `phpVersion: 80500` | hard |
| `vendor/bin/phpunit` (no `-v` flag) | full suite green, **zero** PHP 8.5 deprecation notices | hard |
| `composer cs-check` | clean | hard |
| `bin/check-composer-policy` | green (CP002/CP003/CP006) | hard |
| `bin/check-package-layers` | green (no edges change) | hard |
| `bin/audit-dead-code` | no new findings beyond baseline | hard |
| `tools/drift-detector.sh` | no drift | hard |
| `php -v` | `PHP 8.5.x` | hard (sanity) |
| `php --ri uri` (info) | bundled `Uri` extension reported | informational |
| `composer create-project waaseyaa/skeleton` against branch | installs cleanly on PHP 8.5 host | manual smoke |
| CI matrix | only PHP 8.5 jobs run; no 8.4 jobs | hard (CI) |
| Skeleton smoke workflow | green on `php:8.5-fpm-alpine` | hard (CI) |

---

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Unknown deprecation surface in WP02 | Audit-first (`rg`), timebox each grep; document zero-finding hot zones explicitly |
| `#[\NoDiscard]` cascade across API/middleware | Treat surfaced ignored-return call sites as bugs to fix in WP03, not warnings to suppress |
| Composer < 2.8 in CI breaks platform repo handling on 8.5 | Pin `setup-php` `tools: composer:2.8` (or run `composer self-update --2`) in WP01 |
| Downstream consumer breakage on 8.4 | Out of scope; CHANGELOG bullet flags the floor bump; full migration note in v1.x release notes |
| Working-tree leakage (other missions' `status.json` artifacts) | Stage explicitly per file in commits; do not `git add -A` |
| Local PHP differs from CI PHP | Confirmed local is 8.5.6; CI also uses 8.5 once WP01 lands |

---

## Out of scope (file as follow-up issues in WP06)

- `#[\Override]` adoption sweep (PHP 8.3 feature; large mechanical diff;
  separate mission)
- Migration to bundled native `Uri` extension as a first-class HTTP/IRI
  primitive (contract-affecting; needs its own spec)
- `EntityRepository` `mixed` → typed/generic return tightening beyond what
  `#[\NoDiscard]` work touches
- Consumer migration guide for downstream apps still on 8.4 (release-notes
  artifact at tag time)

---

## PR strategy

Single PR `chore(php-8.5): upgrade required PHP version to 8.5` against
`main`. Body references mission slug per `docs/specs/workflow.md` and the
PR template. Each WP lands as one or more commits; the PR is opened in
draft after WP01 commits, marked ready when WP06 verification passes.
