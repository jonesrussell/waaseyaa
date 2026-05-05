# Quickstart: Verifying Post-#1390 Dispatcher Reconciliation

**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`
**Audience**: framework maintainers and reviewers running the mission's verification commands locally.

## Prerequisites

- PHP 8.4+
- Composer
- A clone of `waaseyaa/framework` at the mission's target branch (`main` plus any in-flight WP branches).

## Phase A — WP01 (analysis only, runs immediately)

WP01 is markdown-only. Verification is review-based:

```bash
# 1. Pull main with the latest mission artifacts.
git checkout main
git pull

# 2. Read WP01's three artifacts in the mission directory.
ls kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/
#   post-1390-dispatcher-contract.md
#   controller-shape-audit.md
#   minoo-resume-verification.md
```

Reviewer pass criteria:

- `post-1390-dispatcher-contract.md` matches the merged shape of framework#1390.
- `controller-shape-audit.md` enumerates every framework-shipped controller method, classified.
- `minoo-resume-verification.md` is runnable end-to-end by an operator without escalation (NFR-004).

## Phase B — WP02..WPn (gated until #1390 merges)

### Gate check

Before any Phase B WP starts, confirm framework#1390 is merged on `main`:

```bash
git log --oneline main | grep -i '1390\|dispatcher.*shim\|implicit.*array' | head -5
```

If no relevant commit appears, **stop**. Phase B WPs do not begin until #1390 lands.

### Static gates

Run on the WP branch before requesting review:

```bash
composer cs-check                   # PHP-CS-Fixer dry-run
composer phpstan                    # static analysis (level 5)
bin/check-composer-policy           # composer manifest policy
bin/check-package-layers            # internal layer discipline
```

All four must exit zero.

### Dynamic gates

```bash
./vendor/bin/phpunit packages/ssr/tests/         # SSR package tests
./vendor/bin/phpunit                              # full suite
```

Both must pass. **Do not pass `-v`** (PHPUnit 10.5 rejects it).

### Contract test verification

The new dispatcher-deprecation contract tests live in `packages/ssr/tests/Contract/`:

```bash
./vendor/bin/phpunit packages/ssr/tests/Contract/
```

Each of the seven tests in `contracts/dispatcher-deprecation-contract.md` §"Test contract" must pass.

### Manual smoke (optional)

Boot the framework dev server against a fixture controller using an implicit-array signature:

```bash
composer dev   # starts PHP built-in server
# In another terminal:
curl -sS http://127.0.0.1:8000/__test__/legacy-array-params | head
# Expected: HTTP 200, response body matches fixture, log file shows one
#          dispatcher.deprecation event for the fixture controller.
```

## Mission-level acceptance

Once Phase A and Phase B WPs are merged on `main`:

```bash
# 1. CHANGELOG carries the [Unreleased] bullet referencing #1390 (and #1388).
grep -A 2 '## \[Unreleased\]' CHANGELOG.md | head -20

# 2. docs/specs/api-layer.md cross-links the dispatcher contract.
grep -n 'dispatcher-deprecation-contract\|post-1390' docs/specs/api-layer.md

# 3. Mission status all-green.
spec-kitty agent tasks status --mission post-1390-dispatcher-reconciliation-01KQTTJS --json
```

When all three pass:

- Run `release-cut.yml` (or its equivalent) to promote `[Unreleased]` to the next alpha tag.
- Close the mission's tracking GitHub issue (per `feedback_pr_traceability_signals.md`).
- Edit the GitHub Release notes to surface the dispatcher reconciliation entry.

## Rolling Minoo's resume verification

After the next alpha tag ships, hand `kitty-specs/.../artifacts/minoo-resume-verification.md` to the Minoo team. Minoo's frozen `upgrade-waaseyaa-alpha-171-01KQTDC2` mission resumes by running that checklist; that activity happens in the Minoo repo, not here.
