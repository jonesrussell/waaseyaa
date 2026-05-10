---
work_package_id: WP01
title: 'Constraint bump: composer.json × 62, CI, Docker, lockfile, phpstan pin, docs, charter'
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-009
planning_base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
merge_target_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-php-8-5-upgrade-01KR8DN2. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-php-8-5-upgrade-01KR8DN2 unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
base_commit: c1af5ce95263b192cdb3a89aa2e2f067c85db2fd
created_at: '2026-05-10T07:53:00+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
- T008
- T009
- T010
- T011
- T012
history: []
authoritative_surface: composer.json
execution_mode: code_change
owned_files:
- composer.json
- composer.lock
- phpstan.neon
- README.md
- CLAUDE.md
- skeleton/Dockerfile
- examples/consumer-test/composer.json
- .github/workflows/ci.yml
- .github/workflows/skeleton-smoke.yml
- .github/workflows/release.yml
- .kittify/charter/charter.md
- .kittify/charter/governance.yaml
- .kittify/charter/directives.yaml
- .kittify/charter/metadata.yaml
- packages/access/composer.json
- packages/admin-surface/composer.json
- packages/ai-agent/composer.json
- packages/ai-observability/composer.json
- packages/ai-pipeline/composer.json
- packages/ai-schema/composer.json
- packages/ai-vector/composer.json
- packages/analytics/composer.json
- packages/api/composer.json
- packages/attachment/composer.json
- packages/auth/composer.json
- packages/billing/composer.json
- packages/bimaaji/composer.json
- packages/cache/composer.json
- packages/cli/composer.json
- packages/cms/composer.json
- packages/config/composer.json
- packages/core/composer.json
- packages/database-legacy/composer.json
- packages/debug/composer.json
- packages/deployer/composer.json
- packages/engagement/composer.json
- packages/entity/composer.json
- packages/entity-storage/composer.json
- packages/error-handler/composer.json
- packages/field/composer.json
- packages/foundation/composer.json
- packages/full/composer.json
- packages/genealogy/composer.json
- packages/geo/composer.json
- packages/github/composer.json
- packages/graphql/composer.json
- packages/groups/composer.json
- packages/http-client/composer.json
- packages/i18n/composer.json
- packages/inertia/composer.json
- packages/ingestion/composer.json
- packages/mail/composer.json
- packages/mcp/composer.json
- packages/media/composer.json
- packages/menu/composer.json
- packages/mercure/composer.json
- packages/messaging/composer.json
- packages/node/composer.json
- packages/northcloud/composer.json
- packages/notification/composer.json
- packages/note/composer.json
- packages/oauth-provider/composer.json
- packages/oidc/composer.json
- packages/path/composer.json
- packages/plugin/composer.json
- packages/queue/composer.json
- packages/relationship/composer.json
- packages/routing/composer.json
- packages/scheduler/composer.json
- packages/search/composer.json
- packages/seo/composer.json
- packages/ssr/composer.json
- packages/state/composer.json
- packages/structured-import/composer.json
- packages/taxonomy/composer.json
- packages/telescope/composer.json
- packages/testing/composer.json
- packages/typed-data/composer.json
- packages/user/composer.json
- packages/validation/composer.json
- packages/workflows/composer.json
tags:
- foundational
- mechanical
---

# WP01 — Constraint bump

## Objective

Mechanical floor bump from PHP 8.4 to PHP 8.5 across all 62 first-party
manifests, CI workflows, the skeleton Dockerfile, README/CLAUDE.md/charter
prose, and the regenerated lockfile. This WP is the foundation; every later
WP depends on it landing on the mission branch.

See `tasks.md` for the full subtask index and implementation sketch.

## Acceptance

- All 62 `composer.json` files require `>=8.5` (or `^8.5` where caret was used).
- `examples/consumer-test/composer.json` `>=8.3` → `>=8.5`.
- `skeleton/Dockerfile` uses `php:8.5-fpm-alpine`.
- All 3 CI workflows set `php-version: '8.5'`; `setup-php` `tools` includes `composer:2.8` (or higher).
- `phpstan.neon` has `parameters.phpVersion: 80500`.
- `composer.lock` regenerated; `bin/check-composer-policy` green.
- README, CLAUDE.md reference PHP 8.5.
- Charter changes (already on disk) included in WP01 commit.
- Draft PR opened against `main`.

## Verification

```bash
bin/check-composer-policy
bin/check-package-layers
composer install   # or composer update --lock
php -v             # confirm 8.5.x in local env
```

## Risks

- Multi-line `require` formatting in any one manifest could break a `sed` pass — guard with `bin/check-composer-policy` on each batch.
- CI matrix changes ripple through `release.yml` (which uses `setup-php` twice) — easy to miss one occurrence.
