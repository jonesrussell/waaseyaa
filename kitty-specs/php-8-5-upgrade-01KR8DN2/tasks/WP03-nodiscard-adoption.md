---
work_package_id: WP03
agent: "opus"
shell_pid: "0"
title: '#[\NoDiscard] adoption on AccessResult, ValidationResult, query builders, EntityRepository::find*()'
dependencies:
- WP01
requirement_refs:
- FR-006
planning_base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
merge_target_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-php-8-5-upgrade-01KR8DN2. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-php-8-5-upgrade-01KR8DN2 unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
base_commit: c1af5ce95263b192cdb3a89aa2e2f067c85db2fd
created_at: '2026-05-10T07:53:00+00:00'
subtasks:
- T030
- T031
- T032
- T033
history: []
authoritative_surface: packages/access/src/
execution_mode: code_change
owned_files:
- packages/access/src/
- packages/validation/src/
- packages/entity-storage/src/
- packages/api/src/
tags:
- adoption
- fix-cascade-expected
---

# WP03 — `#[\NoDiscard]` adoption

## Objective

Apply `#[\NoDiscard]` to result-bearing APIs where silently dropping the
return is a bug. Each surfaced ignored-return call site is a real bug to fix
in this WP, not a warning to suppress.

See `tasks.md` § WP03 for targets and approach.

The owned set is the **anchor**. Call-site fixes triggered by the
attribute additions may extend into other packages; treat those as
in-scope here (commit alongside) since they exist solely as a consequence
of the WP03 change.

## Acceptance

- `#[\NoDiscard]` present on: `AccessResult`, `ValidationResult` and
  typed-data Result shapes, `DBALSelect` and entity query-builder fluent
  methods, `EntityRepository::find*()` returning entities.
- All call sites surfaced by `composer phpstan` (or PHP runtime warnings)
  fixed in this WP — no warning suppression.
- `vendor/bin/phpunit` green; zero new `[\NoDiscard]` warnings in output.

## Verification

```bash
composer phpstan
vendor/bin/phpunit
rg -n '#\[\\NoDiscard' packages/   # confirm attributes present
```

## Risks

- `AccessResult` cascade across API and middleware may surface dozens of
  call sites. Treat as known scope expansion; don't suppress.
