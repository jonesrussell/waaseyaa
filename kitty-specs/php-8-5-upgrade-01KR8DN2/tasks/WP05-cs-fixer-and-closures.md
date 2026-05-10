---
work_package_id: WP05
agent: "opus"
shell_pid: "0"
title: CS-fixer @PHP85Migration rule + closures-in-const + attribute simplification
dependencies:
- WP01
requirement_refs:
- FR-008
planning_base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
merge_target_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-php-8-5-upgrade-01KR8DN2. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-php-8-5-upgrade-01KR8DN2 unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
base_commit: c1af5ce95263b192cdb3a89aa2e2f067c85db2fd
created_at: '2026-05-10T07:53:00+00:00'
subtasks:
- T050
- T051
- T052
history: []
authoritative_surface: .php-cs-fixer.dist.php
execution_mode: code_change
owned_files:
- .php-cs-fixer.dist.php
tags:
- mechanical
- opportunistic
---

# WP05 — CS-fixer migration + closures-in-const

## Objective

Add `@PHP85Migration` to `.php-cs-fixer.dist.php` and let it auto-rewrite.
Convert lazy-init closures to const where applicable. Audit attribute
classes for callable-like simplifications.

See `tasks.md` § WP05.

The auto-rewrite step (T050) will touch many files repo-wide; that's
expected and traceable through `git diff` rather than ownership lists.

## Acceptance

- `.php-cs-fixer.dist.php` includes `'@PHP85Migration' => true` (or
  equivalent set inclusion).
- Auto-rewrites committed in their own commit (separate from manual edits).
- Lazy-init `static ?\Closure $foo = null` patterns converted to
  `private const \Closure FOO = ...` where applicable.
- Attribute simplifications applied only if the resulting code is clearly
  cleaner; otherwise documented as "audited, no change".
- `composer cs-check` clean; `vendor/bin/phpunit` green.

## Verification

```bash
composer cs-check
vendor/bin/phpunit
```

## Risks

- `@PHP85Migration` may rewrite more than expected; review the diff before
  committing the auto-pass.
