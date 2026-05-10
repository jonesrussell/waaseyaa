---
work_package_id: WP04
agent: "opus"
shell_pid: "0"
title: Pipe operator + array_first/array_last + array_find adoption
dependencies:
- WP01
requirement_refs:
- FR-007
planning_base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
merge_target_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-php-8-5-upgrade-01KR8DN2. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-php-8-5-upgrade-01KR8DN2 unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
base_commit: c1af5ce95263b192cdb3a89aa2e2f067c85db2fd
created_at: '2026-05-10T07:53:00+00:00'
subtasks:
- T040
- T041
- T042
history: []
authoritative_surface: packages/foundation/src/Ingestion/
execution_mode: code_change
owned_files:
- packages/foundation/src/Ingestion/
- packages/ingestion/src/
- packages/typed-data/src/Transform/
tags:
- adoption
- ergonomic
- selective
---

# WP04 — Pipe / array_first / array_last / array_find

## Objective

Apply 8.5 ergonomic features (pipe operator, `array_first`/`array_last`)
plus opportunistic 8.4 `array_find` adoption where it improves readability.
Skip cases that genuinely use the array internal pointer or that are already
fluent and clean.

See `tasks.md` § WP04 for grep patterns and selection criteria.

The owned set is the **anchor**. Other clear-win sites discovered during
the sweep may be picked up if they are obviously cleaner; cap total
commits at 5.

## Acceptance

- `array_first()` / `array_last()` swaps applied where intent is "first/last
  value, no pointer mutation".
- `array_find()` swaps applied to first-match `foreach { if return }` patterns
  where the predicate fits cleanly.
- Pipe operator (`|>`) applied only to clear-win sites (ingestion validators,
  typed-data transforms). Bounded to ≤5 commits.
- `vendor/bin/phpunit` green; behavior unchanged; no new tests required
  unless a swap revealed a latent bug.

## Verification

```bash
vendor/bin/phpunit
composer cs-check
```

## Risks

- Pipe operator is new syntax; reviewers may push back. Cap at 5 commits and
  pick only obvious wins.
