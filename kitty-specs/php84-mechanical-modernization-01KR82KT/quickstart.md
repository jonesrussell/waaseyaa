# Quickstart — Verification

**Mission**: php84-mechanical-modernization-01KR82KT

After applying any work package's edits, run from the repo root:

```bash
# 1. Targeted test re-run (per-WP fast feedback)
./vendor/bin/phpunit packages/<package>/tests/

# 2. Full unit + integration suite
./vendor/bin/phpunit

# 3. Static analysis
composer phpstan

# 4. Code style
composer cs-check

# 5. Layer discipline
bin/check-package-layers

# 6. Composer policy
composer check-composer-policy
```

All six MUST exit zero before the WP is marked ready for review.

## Common pitfalls

- `array_find` returns `null` on no-match; previous `array_values(array_filter)[0]` raised offset errors. If a test relied on the error path, update or document the new behavior.
- `composer cs-check` (PHP-CS-Fixer) may reformat surrounding lines — keep diffs minimal by running `composer cs-fix` only on the file you edited.
- `#[\Deprecated]` emits `E_USER_DEPRECATED` at use site. Confirm test suites do not promote deprecations to fatals (`error_reporting` / `set_error_handler`).
- `json_validate()` is PHP 8.3+; safe under our 8.4+ minimum.
