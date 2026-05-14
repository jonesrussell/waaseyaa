---
work_package_id: WP01
title: Package scaffold, composer config, CI skeleton
dependencies: []
requirement_refs:
- FR-018
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
base_commit: 189475bd1b5d98357e8168d0e89bf74c6586827a
created_at: '2026-05-14T19:18:30.894225+00:00'
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
shell_pid: "121911"
history: []
authoritative_surface: src/ServiceProvider.php
execution_mode: code_change
owned_files:
- composer.json
- phpunit.xml
- .github/workflows/ci.yml
- .github/workflows/release.yml
- .gitignore
- src/ServiceProvider.php
- src/.gitkeep
- tests/.gitkeep
tags: []
agent: "claude"
---

# WP01 — Package scaffold, composer config, CI skeleton

## Objective

Provision the standalone composer package `waaseyaa-migrate-source-wordpress` and its GitHub repo such that `composer install` works and an empty test suite passes in CI. This WP unblocks every other WP in the mission.

## Context

- Mission: M-005, see [`spec.md`](../spec.md) §1 (Goals).
- Plan: see [`plan.md`](../plan.md) §"Project Structure" for the standalone-repo layout.
- Research: [`research.md`](../research.md) §1.9 (standalone-repo decision), §1.10 (independent semver).
- Substrate: depends on `waaseyaa/migration ^0.1.0-alpha.179` (M-002, shipped 2026-05-14).
- The package lives in a SEPARATE repo at `github.com/waaseyaa/migrate-source-wordpress`. NOT in the framework monorepo.

## Implementation command

```
spec-kitty agent action implement WP01 --agent sonnet
```

## Subtask guidance

### T001 — Provision the GitHub repo

```
gh repo create waaseyaa/migrate-source-wordpress \
  --public \
  --description "Waaseyaa migration source: WordPress (WXR) — first-party reader for the Waaseyaa migration platform"
```

Repo settings:
- Default branch: `main`
- Branch protection: require PR reviews + green CI before merge to main
- License: GPL-2.0-or-later (matches framework)

### T002 — composer.json

```json
{
    "name": "waaseyaa/migrate-source-wordpress",
    "description": "WordPress (WXR) source reader for the Waaseyaa migration platform",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.5",
        "ext-libxml": "*",
        "ext-xmlreader": "*",
        "waaseyaa/migration": "^0.1.0-alpha.179",
        "waaseyaa/foundation": "^0.1.0-alpha.179"
    },
    "require-dev": {
        "pestphp/pest": "^4.0",
        "phpstan/phpstan": "^2.0",
        "friendsofphp/php-cs-fixer": "^3.0"
    },
    "autoload": {
        "psr-4": {"Waaseyaa\\Migrate\\Source\\WordPress\\": "src/"}
    },
    "autoload-dev": {
        "psr-4": {"Waaseyaa\\Migrate\\Source\\WordPress\\Tests\\": "tests/"}
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {"pestphp/pest-plugin": true}
    },
    "extra": {
        "waaseyaa": {
            "providers": ["Waaseyaa\\Migrate\\Source\\WordPress\\ServiceProvider"]
        }
    }
}
```

Forbidden constraints (per framework composer policy): no `@dev`, no `*` for waaseyaa/*.

### T003 — README.md

Skeleton sections:
- Tagline: "Migrate your WordPress site to Waaseyaa"
- Quick install: `composer require waaseyaa/migrate-source-wordpress`
- Quick usage: 5-line example showing `bin/waaseyaa import:run-all` after registering the package
- Compatibility table: `waaseyaa/migration ^0.1.0-alpha.179+`
- Links to forthcoming `docs/migrating-from-wordpress.md` and `docs/customization.md` (built in WP10)

Polish in WP10 (T079).

### T004 — CHANGELOG.md

Keep a Changelog format. Initial state:

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
```

WP10 (T080) cuts the first `[0.1.0]` entry.

### T005 — Pest v4 + phpunit config

Default to Pest. `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit"><directory>tests/Unit</directory></testsuite>
        <testsuite name="Conformance"><directory>tests/Conformance</directory></testsuite>
        <testsuite name="Integration"><directory>tests/Integration</directory></testsuite>
    </testsuites>
</phpunit>
```

### T006 — CI workflow (`.github/workflows/ci.yml`)

Matrix: PHP 8.5 only initially (matches framework requirement; expand if needed).

Jobs:
- `lint` — PHP-CS-Fixer dry-run
- `phpstan` — level 5 against `src/` and `tests/`
- `test` — `vendor/bin/pest`
- `dead-code-audit` — warn-only, mirror framework convention

Mirror the framework's `composer-policy` style (CP rules don't apply to a single-package repo, but no-`@dev` and `sort-packages` SHOULD apply locally).

### T007 — release.yml stub

Tag-triggered workflow that:
1. Validates the tag matches semver
2. Posts to Packagist's update-package API (after the manual one-time submission)

Reference the framework's `packagist-update.yml` for the pattern.

### T008 — ServiceProvider.php skeleton

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Migrate\Source\WordPress;

use Waaseyaa\Foundation\ServiceProvider as BaseServiceProvider;
use Waaseyaa\Migration\Plugin\HasMigrationPluginsInterface;
use Waaseyaa\Migration\Plugin\HasMigrationsInterface;

final class ServiceProvider extends BaseServiceProvider implements
    HasMigrationsInterface,
    HasMigrationPluginsInterface
{
    public function migrations(): array
    {
        return []; // Populated by WP09
    }

    public function migrationPlugins(): array
    {
        return []; // Source plugins added in WP03..WP07; process plugins in WP08
    }
}
```

### T009 — public-surface-map.md scaffold

Template per spec §4 stable surface table. Mark every entry as `present: false` initially; later WPs flip them to `true` as they ship.

### T010 — Verification

- `composer install` exits 0
- `vendor/bin/pest` exits 0 (no tests yet → "no tests executed" is acceptable)
- CI green on the no-op commit (push T001..T009 results, watch GitHub Actions)
- Manual: submit `https://github.com/waaseyaa/migrate-source-wordpress` on https://packagist.org/packages/submit (one-time; CI release.yml relies on the package being registered)

## Definition of Done

- [ ] Standalone repo provisioned and accessible at `https://github.com/waaseyaa/migrate-source-wordpress`
- [ ] `composer.json` valid; `composer install` works on a clean machine
- [ ] CI green on initial commit
- [ ] Package registered on Packagist (operator manual step; document in T010 checklist)
- [ ] README, CHANGELOG, phpunit.xml, .gitignore, ServiceProvider stub, public-surface-map.md all in place
- [ ] No source code yet beyond the empty ServiceProvider — that's WP02+

## Risks

- **Packagist registration drift.** If the operator forgets the manual step, all subsequent releases (WP10) will fail at the verify gate. Document prominently in WP01 acceptance.
- **PHP 8.5 availability on CI runners.** ubuntu-latest may not yet ship PHP 8.5 by default. May need `shivammathur/setup-php@v2` action with explicit version.

## Reviewer guidance

Verify:
- composer.json passes `composer validate` strictly
- No `@dev` constraints, sort-packages: true
- ServiceProvider implements both required interfaces (even if methods return empty arrays)
- CI workflow file syntax is valid (`actionlint` if available)
- Repo settings: branch protection enabled, default branch is main

## Activity Log

- 2026-05-14T19:18:32Z – claude – shell_pid=121911 – Assigned agent via action command
