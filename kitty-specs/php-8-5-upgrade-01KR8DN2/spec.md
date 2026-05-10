# Spec: Upgrade Waaseyaa to PHP 8.5

**Mission ID:** `01KR8DN2YHQJDH36FGHNTW9958`
**Slug:** `php-8-5-upgrade-01KR8DN2`
**Mission type:** `software-dev`
**Target branch:** `main`
**Created:** 2026-05-10

---

## Why

PHP 8.5 went GA on 2025-11-20; today is 2026-05-10 (~6 months in). 8.5 is past
the early-adopter risk window and into the stable patch series. Waaseyaa
currently requires PHP 8.4. Bumping the floor to 8.5:

- Unlocks `#[\NoDiscard]` (textbook fit for `AccessResult`, query builders).
- Unlocks the pipe operator (`|>`), `array_first()`, `array_last()`.
- Brings fatal-error backtraces and the bundled `Uri` extension into the
  base platform we test against.
- Fixes deprecation warnings 8.5 introduces (non-canonical scalar casts,
  `curl_close()` no-ops, output-buffer handler return semantics).
- Lets us drop the 8.4 CI matrix slot — no dual-version maintenance.

This is a framework-floor bump. Downstream consumers on 8.4 will be unable to
upgrade to the next Waaseyaa minor; we communicate this in the changelog and
the next release notes.

---

## Goal

Raise the PHP requirement from 8.4 to 8.5 across the entire monorepo, fix any
deprecations 8.5 surfaces, and apply a focused 8.5 feature-adoption pass.
Ship as a single PR to `main`.

## User scenarios

- **Framework maintainer** runs `composer install` on a fresh checkout with
  PHP 8.5 installed, and the install succeeds with no platform warnings.
- **Framework maintainer** runs `vendor/bin/phpunit` on PHP 8.5 and sees zero
  deprecation notices in the output.
- **Downstream consumer** on PHP 8.4 attempts to upgrade to the next Waaseyaa
  release and is cleanly blocked by Composer with an actionable error
  ("requires php >=8.5"), with the changelog explaining why.
- **CI** runs the matrix and only the 8.5 job appears; no leftover 8.4 job.
- **Skeleton consumer** (`composer create-project waaseyaa/skeleton`) builds
  on `php:8.5-fpm-alpine` without modification.

## Functional requirements

- **FR-001** — All 62 first-party `composer.json` files require `>=8.5` (or `^8.5` where the caret form was already in use).
- **FR-002** — All GitHub Actions workflows (`ci.yml`, `skeleton-smoke.yml`, `release.yml`) set `php-version: '8.5'` with no leftover 8.4 jobs.
- **FR-003** — Skeleton Dockerfile uses `php:8.5-fpm-alpine`.
- **FR-004** — `phpstan.neon` declares `parameters.phpVersion: 80500`.
- **FR-005** — Zero PHP 8.5 deprecation notices in first-party code (scalar casts, `curl_close`, OB handler returns, shutdown handlers, `DateTime` parsing). Each fix carries a regression test.
- **FR-006** — `#[\NoDiscard]` applied to `AccessResult`, `ValidationResult` and other Result-shaped types, query-builder fluent methods, and `EntityRepository::find*()`. Surfaced ignored-return call sites are fixed, not suppressed.
- **FR-007** — 8.5 ergonomic features adopted where they improve readability: `array_first`/`array_last`, `array_find` (PHP 8.4 opportunistic), pipe operator (selective).
- **FR-008** — `.php-cs-fixer.dist.php` includes `@PHP85Migration`; lazy-init closures converted to const where applicable.
- **FR-009** — `CHANGELOG.md` `[Unreleased]` includes one bullet describing the bump and adopted features. README, `CLAUDE.md`, charter, and `docs/specs/*` reference PHP 8.5 throughout.
- **FR-010** — All hard-gate verification commands pass: `composer phpstan`, `vendor/bin/phpunit`, `composer cs-check`, `bin/check-composer-policy`, `bin/check-package-layers`, `bin/audit-dead-code`, `tools/drift-detector.sh`. CI matrix is 8.5-only.

## Acceptance criteria

1. All 62 first-party `composer.json` files require `>=8.5` (or `^8.5` where
   the caret form was already in use). Verified by `bin/check-composer-policy`.
2. `.github/workflows/ci.yml`, `.github/workflows/skeleton-smoke.yml`, and
   `.github/workflows/release.yml` set `php-version: '8.5'`. No 8.4 jobs
   remain in any matrix.
3. `skeleton/Dockerfile` uses `FROM php:8.5-fpm-alpine`.
4. `phpstan.neon` declares `parameters.phpVersion: 80500`.
5. `composer phpstan`, `vendor/bin/phpunit`, `composer cs-check`,
   `bin/check-composer-policy`, `bin/check-package-layers`, and
   `bin/audit-dead-code` all pass on PHP 8.5.
6. `vendor/bin/phpunit` output contains zero PHP 8.5 deprecation notices.
7. `CHANGELOG.md` `[Unreleased]` includes a single bullet describing the bump
   and the adopted 8.5 features. (Per `feedback_changelog_release_workflow.md`,
   only `[Unreleased]` is edited; `release-cut.yml` promotes at tag time.)
8. `README.md` (badge + prose), `CLAUDE.md` (Code Style section), and any
   `docs/specs/*.md` references state PHP 8.5 as the minimum.
9. `bin/check-package-layers` is unaffected (no layer edges change).
10. `tools/drift-detector.sh` reports no spec drift introduced.

## Non-goals (deferred to follow-up issues, filed in WP06)

- `#[\Override]` adoption sweep (PHP 8.3 feature; large mechanical diff
  unrelated to the 8.5 floor bump).
- Migration to the bundled native `Uri` extension as a first-class HTTP/IRI
  primitive. Touches contracts; needs its own spec.
- Type-tightening `EntityRepository` (`mixed` → typed/generic returns) beyond
  what `#[\NoDiscard]` work touches opportunistically.
- A consumer migration guide for downstream apps still on 8.4. Flagged in
  CHANGELOG; full migration docs ship with the v1.x release notes.

## Constraints

- **CI matrix:** Drop 8.4 entirely. Test only on PHP 8.5.
- **Scope:** Bump + deprecation fixes + focused 8.5 adoption pass. No
  unrelated refactors slipped in.
- **Adoption discipline:** Every `#[\NoDiscard]` add that surfaces a call
  site ignoring a return is treated as a real bug to fix in this mission,
  not a warning to silence.
- **Audit before changing:** The deprecation sweep (WP02) does not assume
  any specific code pattern exists. Each item is `rg`-discovered first.
- **Regression tests:** Per `feedback_regression_tests.md`, every
  deprecation fix gets a regression test covering the previously-broken
  behavior on PHP 8.5.
- **PHPUnit invocation:** Do not pass `-v` (PHPUnit 10.5 rejects it).

## Risks

- **Unknown deprecation surface.** The WP02 sweep may surface more or fewer
  fixes than estimated. Mitigation: timebox each grep+audit; if a hot zone
  produces zero findings, document and move on.
- **`#[\NoDiscard]` cascade.** Adding `#[\NoDiscard]` to `AccessResult` may
  surface dozens of call sites across the API and middleware layers that
  silently dropped results. Treat as a known scope expansion within WP03;
  don't suppress warnings to escape it.
- **Composer Composer plugin compat.** Composer 2.8+ is recommended for
  PHP 8.5 platform repo handling. WP01 pins it explicitly in CI.
- **Downstream consumer breakage.** Out of scope for this mission, but
  needs a clear CHANGELOG note and a release-notes call-out at tag time.

## Verification (mission-level)

See plan.md `## Verification` for the full command matrix. Summary:

- `composer phpstan` — clean at level 5 with `phpVersion: 80500`.
- `vendor/bin/phpunit` — full suite green, zero deprecation notices.
- `composer cs-check` — clean.
- `bin/check-composer-policy`, `bin/check-package-layers`,
  `bin/audit-dead-code`, `tools/drift-detector.sh` — all green.
- CI workflows green with 8.5-only matrix.
- Skeleton smoke workflow green on the new Dockerfile base.
- Image spot-check: `docker run --rm <skeleton-image> php -v` reports 8.5.x;
  `php --ri uri` shows the bundled extension is present (informational).

## Source authority

- Charter / governance: `CLAUDE.md`, `docs/specs/workflow.md`.
- Approved mission plan (session artifact): `~/.claude/plans/i-want-to-upgrade-sequential-hummingbird.md`.
- Composer policy: `bin/check-composer-policy` (CP002, CP003, CP006 must
  remain green throughout).
- Layer policy: `bin/check-package-layers` (no edges change in this mission).
