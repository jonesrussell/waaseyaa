# Tasks: Upgrade Waaseyaa to PHP 8.5

**Mission:** `php-8-5-upgrade-01KR8DN2`
**Branch contract:** planning base `main` → merge target `main` (mission branch `kitty/mission-php-8-5-upgrade-01KR8DN2`)

See `spec.md` for goal and acceptance criteria, `plan.md` for inventory and verification matrix.

---

## Subtask Index

| ID   | Description                                                                                                    | WP   | Parallel |
|------|----------------------------------------------------------------------------------------------------------------|------|----------|
| T001 | Bump `require.php` to `>=8.5` / `^8.5` across 62 `composer.json` files (root + 60 packages + consumer-test)    | WP01 | [D]      |
| T002 | `skeleton/Dockerfile`: `php:8.4-fpm-alpine` → `php:8.5-fpm-alpine`                                             | WP01 | [D]      |
| T003 | CI workflows (`ci.yml`, `skeleton-smoke.yml`, `release.yml`): `php-version: '8.4'` → `'8.5'` (4 occurrences)   | WP01 | [D]      |
| T004 | Pin `setup-php` `tools: composer:2.8` in CI workflows                                                          | WP01 | [D]      |
| T005 | `phpstan.neon`: add `parameters.phpVersion: 80500`                                                             | WP01 | [D]      |
| T006 | `composer update --lock`; commit lockfile                                                                      | WP01 | [D]      |
| T007 | README badge + 3 prose lines: `8.4`/`8.4+` → `8.5`/`8.5+`                                                      | WP01 | [D]      |
| T008 | `CLAUDE.md` Code Style: `PHP 8.4+` → `PHP 8.5+`                                                                | WP01 | [D]      |
| T009 | `docs/specs/*.md` grep + edit any `PHP 8.4` references                                                         | WP01 | [D]      |
| T010 | Stage charter changes (`.kittify/charter/charter.md` + regenerated YAML) into WP01 commit                      | WP01 | [D]      |
| T011 | Verify `bin/check-composer-policy` and `bin/check-package-layers` green on WP01 branch                         | WP01 | [D]      |
| T012 | Open draft PR against `main` once WP01 commits land                                                            | WP01 | [D]      |
| T020 | `rg` audit: non-canonical scalar casts (`(int|string|float|bool) $var`) in hot zones; fix surfaced cases       | WP02 | [P] [D]  |
| T021 | `rg` audit: `curl_close` / `curl_share_close` calls; remove                                                    | WP02 | [P] [D]  |
| T022 | `rg` audit: custom `ob_start` handlers; verify return semantics for 8.5                                        | WP02 | [P] [D]  |
| T023 | `rg` audit: `register_shutdown_function` callers; verify no double-trace under 8.5                             | WP02 | [P] [D]  |
| T024 | `rg` audit: `new \DateTime(Immutable)?(...)` with ambiguous formats; spot-test                                 | WP02 | [P] [D]  |
| T025 | Per finding in T020–T024: regression test + fix commit                                                         | WP02 | [D]      |
| T030 | Add `#[\NoDiscard]` to `Waaseyaa\Access\AccessResult`; fix surfaced ignored-return call sites                  | WP03 | [P] [D]  |
| T031 | Add `#[\NoDiscard]` to `ValidationResult` and `Result`-shaped types in `packages/typed-data/`                  | WP03 | [P] [D]  |
| T032 | Add `#[\NoDiscard]` to query-builder fluent methods (`DBALSelect`, entity query builders)                      | WP03 | [P] [D]  |
| T033 | Add `#[\NoDiscard]` to `EntityRepository::find*()` returning entities                                          | WP03 | [P] [D]  |
| T040 | `array_first()` / `array_last()` swap for `reset()` / `end()` value-only patterns                              | WP04 | [P] [D]  |
| T041 | `array_find()` swap for bespoke `foreach { if return }` first-match loops (PHP 8.4 opportunistic)              | WP04 | [P] [D]  |
| T042 | Pipe operator (`|>`) adoption where it improves readability (ingestion validators, typed-data transforms)      | WP04 | [P] [D]  |
| T050 | Add `'@PHP85Migration' => true` to `.php-cs-fixer.dist.php`; run `composer cs-fix`; commit auto-rewrites separately | WP05 | [D]      |
| T051 | Convert lazy-init `static ?\Closure $foo = null` to `private const \Closure FOO = ...` where applicable       | WP05 | [P] [D]  |
| T052 | Audit attribute classes for callable-like metadata that simplifies under 8.5; apply only if clearly cleaner    | WP05 | [P] [D]  |
| T060 | `CHANGELOG.md` `[Unreleased]`: single bullet describing bump + adopted features                                | WP06 | [D]      |
| T061 | Run full mission verification matrix (see `plan.md`); capture command output in WP closing notes               | WP06 | [D]      |
| T062 | File follow-up issues: `#[\Override]` sweep, native `Uri` adoption, `EntityRepository` typing                  | WP06 | [D]      |
| T063 | Mark PR ready for review                                                                                       | WP06 | [D]      |

Legend: `[D]` = description finalized. `[P]` = independently parallelizable inside the WP.

---

## WP01 — Constraint bump + CI + Docker + lockfile + phpstan pin + docs + charter

**Goal**: Mechanical floor bump across all 62 manifests, CI workflows, the skeleton Dockerfile, README/CLAUDE.md/charter prose, and the regenerated lockfile. Foundational; blocks all later WPs.

**Priority**: P0 (foundational).
**Independent test**: `bin/check-composer-policy`, `bin/check-package-layers` green; `composer install` succeeds; `composer phpstan` runs (output may surface deprecations cleared in WP02).
**Estimated prompt size**: ~250 lines.
**Dependencies**: none.

### Included subtasks

- [ ] T001 — `composer.json` × 62 (`>=8.4`/`^8.4`/`>=8.3` → `>=8.5`/`^8.5`)
- [ ] T002 — `skeleton/Dockerfile` PHP base image
- [ ] T003 — CI workflow `php-version` (4 occurrences across 3 files)
- [ ] T004 — Pin `setup-php` `tools: composer:2.8` in CI
- [ ] T005 — `phpstan.neon` `phpVersion: 80500`
- [ ] T006 — `composer update --lock`; commit `composer.lock`
- [ ] T007 — README badge + prose
- [ ] T008 — `CLAUDE.md` Code Style line
- [ ] T009 — `docs/specs/*.md` grep + edit
- [ ] T010 — Stage charter changes + regenerated YAML
- [ ] T011 — Run `bin/check-composer-policy` and `bin/check-package-layers`
- [ ] T012 — Push branch and open draft PR

### Implementation sketch

1. `find packages -name composer.json -exec sed -i 's/"php": ">=8\.4"/"php": ">=8.5"/' {} +`
2. Hand-edit the two `^8.4` packages and `examples/consumer-test/composer.json` (`>=8.3` → `>=8.5`).
3. Hand-edit root `composer.json`.
4. `sed -i 's/php:8.4-fpm-alpine/php:8.5-fpm-alpine/' skeleton/Dockerfile`.
5. Edit each `.github/workflows/*.yml` — change `php-version: '8.4'` to `'8.5'`; add or update `tools: composer:2.8`.
6. Edit `phpstan.neon` — add `parameters.phpVersion: 80500`.
7. Run `composer update --lock` (no actual install needed for path repos; lockfile regenerates the platform requirement).
8. Hand-edit `README.md` (badge + 2 prose lines) and `CLAUDE.md` (Code Style section).
9. `rg -l 'PHP 8\.4' docs/specs/` and bulk-edit any matches.
10. Stage `.kittify/charter/charter.md`, `.kittify/charter/governance.yaml`, `.kittify/charter/directives.yaml`, `.kittify/charter/metadata.yaml` (already updated).
11. `bin/check-composer-policy && bin/check-package-layers` — both green or fail loudly.
12. Commit per logical chunk: composer manifests, CI/Docker, phpstan/cs-fixer, docs, charter. Push branch, open draft PR.

### Risks

- `sed` on 60 files is fragile if any manifest has multiline `require` formatting. Run `bin/check-composer-policy` to catch JSON parse failures.
- Composer 2.8 platform-repo handling: if CI's existing pin is older, the `php >=8.5` requirement may resolve oddly without the pin update.

### Prompt file

See `tasks/WP01-constraint-bump.md`.

---

## WP02 — 8.5 deprecation sweep

**Goal**: Locate and fix anything PHP 8.5 deprecates (scalar casts, `curl_close`, OB handler returns, shutdown handlers, `DateTime` parsing). Audit-first, fix-second; per-finding regression test.

**Priority**: P1 (post-bump, pre-adoption).
**Independent test**: `vendor/bin/phpunit` shows zero PHP 8.5 deprecation notices in output.
**Estimated prompt size**: ~300 lines.
**Dependencies**: WP01 (must be merged or in branch).

### Included subtasks

- [ ] T020 Non-canonical scalar casts
- [ ] T021 `curl_close` / `curl_share_close`
- [ ] T022 `ob_start` handler return semantics
- [ ] T023 `register_shutdown_function` double-trace
- [ ] T024 `DateTime` ambiguous parsing
- [ ] T025 Regression tests for each fix

### Implementation sketch

For each audit (T020–T024), `rg` first across the relevant hot zones (see `plan.md` § Critical files). Document zero-finding zones explicitly in the WP closing notes. For each non-zero finding: write a regression test that fails on PHP 8.5 against the unfixed code, then fix.

### Risks

- Surface may be larger or smaller than estimated; timebox each grep+audit at 30 minutes.
- Some 8.5 deprecation notices come from vendor packages (Symfony, Doctrine) — those are not our scope; document and move on.

### Prompt file

See `tasks/WP02-deprecation-sweep.md`.

---

## WP03 — `#[\NoDiscard]` adoption

**Goal**: Add `#[\NoDiscard]` to result-bearing APIs where silently dropping the return is a bug. Each surfaced ignored-return call site is a real bug to fix in this WP, not a warning to suppress.

**Priority**: P1 (high signal/value).
**Independent test**: `composer phpstan` clean; `vendor/bin/phpunit` green; `vendor/bin/phpunit` output shows no new `[\NoDiscard]` warnings.
**Estimated prompt size**: ~280 lines.
**Dependencies**: WP01.

### Included subtasks

- [ ] T030 `AccessResult`
- [ ] T031 `ValidationResult` + typed-data `Result` shapes
- [ ] T032 Query builders (`DBALSelect`, entity query builders)
- [ ] T033 `EntityRepository::find*()`

### Implementation sketch

For each target: add `#[\NoDiscard]` attribute to the relevant methods/classes. Run `composer phpstan` to surface ignored-return call sites. For each surfaced site, fix the caller (use the return, log it, or assign to `$_` with comment). Regression tests where the fix was load-bearing.

### Risks

- `AccessResult` cascade across API and middleware — could surface dozens of sites. Treat as a known scope expansion within this WP.

### Prompt file

See `tasks/WP03-nodiscard-adoption.md`.

---

## WP04 — Pipe operator + `array_first` / `array_last` + `array_find`

**Goal**: Apply 8.5 ergonomic features (pipe operator, `array_first`/`array_last`) plus opportunistic 8.4 `array_find` adoption where it improves readability.

**Priority**: P2 (ergonomic; bounded).
**Independent test**: `vendor/bin/phpunit` green; no behavior change.
**Estimated prompt size**: ~250 lines.
**Dependencies**: WP01.

### Included subtasks

- [ ] T040 `array_first` / `array_last` swap
- [ ] T041 `array_find` opportunistic swap
- [ ] T042 Pipe operator adoption (selective)

### Implementation sketch

Use grep patterns from `plan.md`. Skip cases that genuinely use the array internal pointer (T040) or that are already fluent/clean (T042). Each swap covered by existing tests; no new tests needed unless a swap reveals a latent bug.

### Risks

- Pipe operator is new syntax; unfamiliar reviewers may push back. Limit to clear-win sites (3–5 commits max).

### Prompt file

See `tasks/WP04-adoption-pass.md`.

---

## WP05 — CS-fixer migration rule + closures-in-const

**Goal**: Add `@PHP85Migration` to `.php-cs-fixer.dist.php` and let it auto-rewrite. Convert lazy-init closures to const where applicable. Audit attribute classes for callable-like simplifications.

**Priority**: P2 (mechanical + opportunistic).
**Independent test**: `composer cs-check` clean; `vendor/bin/phpunit` green.
**Estimated prompt size**: ~200 lines.
**Dependencies**: WP01.

### Included subtasks

- [ ] T050 `@PHP85Migration` cs-fixer rule + auto-rewrite commit
- [ ] T051 Closures in const expressions
- [ ] T052 Attribute argument simplifications

### Implementation sketch

1. Edit `.php-cs-fixer.dist.php`, add `'@PHP85Migration' => true` to the rule set.
2. Run `composer cs-fix`. Commit the auto-rewrites in their own commit so review is easy.
3. Grep for `static ?\Closure $foo = null` lazy-init patterns. Convert.
4. Audit attribute classes; apply only if clearly cleaner.

### Risks

- `@PHP85Migration` may rewrite more than expected; review the diff carefully before committing.

### Prompt file

See `tasks/WP05-cs-fixer-and-closures.md`.

---

## WP06 — CHANGELOG + verification + follow-up issues

**Goal**: Append `[Unreleased]` bullet to `CHANGELOG.md`; run the full mission verification matrix; file follow-up issues for deferred work; mark PR ready.

**Priority**: P0 (closing).
**Independent test**: All verification commands green; PR marked ready.
**Estimated prompt size**: ~150 lines.
**Dependencies**: WP01 + WP02 + WP03 + WP04 + WP05 (all merged on the mission branch).

### Included subtasks

- [ ] T060 `CHANGELOG.md` `[Unreleased]` bullet
- [ ] T061 Full verification matrix
- [ ] T062 File follow-up issues
- [ ] T063 Mark PR ready

### Implementation sketch

1. Append one bullet to `CHANGELOG.md` `[Unreleased]` (per `feedback_changelog_release_workflow.md`, only `[Unreleased]` is edited; release-cut.yml promotes at tag time).
2. Run the verification matrix from `plan.md`. Capture command names + result in WP closing notes.
3. `gh issue create` for each deferred item: `#[\Override]` sweep, native `Uri` adoption, `EntityRepository` typing.
4. `gh pr ready` to flip the PR out of draft.

### Risks

- Verification may fail; treat any failure as a return-to-WP02/WP03 cycle rather than rushing through.

### Prompt file

See `tasks/WP06-changelog-and-verification.md`.

---

## Execution order & parallelism

- **Lane A**: WP01 (foundational; lands first).
- **Lane B**: WP02 + WP03 + WP04 + WP05 (parallel after WP01; in this mission they will likely run sequentially in one worktree since the work is small).
- **Lane C**: WP06 (closing; runs after all prior WPs are committed on the mission branch).

MVP scope (smallest reviewable shippable slice): **WP01 + WP06**. WP02–WP05 are scope-expansion within the same PR; if any blow up unexpectedly, defer to a follow-up mission and ship the bump alone.

## Branch contract (final)

- **Planning base**: `main`
- **Merge target**: `main`
- **Mission branch**: `kitty/mission-php-8-5-upgrade-01KR8DN2`
- **branch_matches_target**: `false` while mission is in flight; `true` after squash-merge into `main`
