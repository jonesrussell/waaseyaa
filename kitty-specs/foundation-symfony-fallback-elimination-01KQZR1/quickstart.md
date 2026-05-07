# Quickstart: foundation-symfony-fallback-elimination-01KQZR1

## Preconditions

- Waaseyaa monorepo clone with Composer and PHP 8.4+
- Spec Kitty CLI installed (`pip install spec-kitty-cli` or `uv tool install spec-kitty-cli`)

## Operator flow

1. Read `spec.md` and `checklists/requirements.md`.
2. Run `/spec-kitty.plan` (or `spec-kitty plan`) to expand `plan.md` from the spec.
3. Run `/spec-kitty.tasks` / finalize-tasks to materialize `tasks/WP*.md` and `lanes.json`.
4. Execute WPs in lane order; after WP01, treat `artifacts/fallback-inventory.md` as authoritative scope control.

## Verification (after implementation)

```bash
cd /path/to/waaseyaa
./vendor/bin/phpunit
composer phpstan
composer cs-check
```

If SSR or routing packages change, add targeted PHPUnit paths per WP prompts.
