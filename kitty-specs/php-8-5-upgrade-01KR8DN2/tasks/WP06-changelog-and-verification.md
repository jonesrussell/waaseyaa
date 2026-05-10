---
work_package_id: WP06
title: CHANGELOG [Unreleased] bullet + full verification matrix + follow-up issues + PR ready
dependencies:
- WP01
- WP02
- WP03
- WP04
- WP05
requirement_refs:
- FR-009
- FR-010
planning_base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
merge_target_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-php-8-5-upgrade-01KR8DN2. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-php-8-5-upgrade-01KR8DN2 unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
base_commit: c1af5ce95263b192cdb3a89aa2e2f067c85db2fd
created_at: '2026-05-10T07:53:00+00:00'
subtasks:
- T060
- T061
- T062
- T063
history: []
authoritative_surface: CHANGELOG.md
execution_mode: code_change
owned_files:
- CHANGELOG.md
tags:
- closing
---

# WP06 — CHANGELOG + verification + closeout

## Objective

Append the `[Unreleased]` bullet, run the full mission verification matrix,
file follow-up issues for deferred work, and mark the PR ready for review.

See `tasks.md` § WP06 and `plan.md` § Verification matrix.

## Acceptance

- `CHANGELOG.md` `[Unreleased]` includes one bullet covering: required PHP
  bump to 8.5, adopted `#[\NoDiscard]`, `array_first`/`array_last`,
  `array_find`, pipe operator; removed deprecated `curl_close()` calls;
  fixed scalar-cast warnings.
- All hard-gate verification commands green (see `plan.md`).
- Follow-up issues filed: `#[\Override]` sweep, native `Uri` adoption,
  `EntityRepository` typing.
- PR marked ready (`gh pr ready`).

## Verification

```bash
composer phpstan
vendor/bin/phpunit                    # NO -v flag (PHPUnit 10.5 rejects it)
composer cs-check
bin/check-composer-policy
bin/check-package-layers
bin/audit-dead-code
tools/drift-detector.sh
php -v                                # 8.5.x
gh pr ready <PR_NUMBER>
```

## Risks

- Verification failure should trigger a return-to-WP02/WP03 cycle, not a
  rush to merge. Add a follow-up issue if a finding is genuinely out of
  scope.
