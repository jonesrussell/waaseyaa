---
work_package_id: WP02
title: 'PHP 8.5 deprecation sweep: scalar casts, curl_close, OB handlers, shutdown, DateTime'
dependencies:
- WP01
requirement_refs:
- FR-005
planning_base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
merge_target_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-php-8-5-upgrade-01KR8DN2. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-php-8-5-upgrade-01KR8DN2 unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-php-8-5-upgrade-01KR8DN2
base_commit: c1af5ce95263b192cdb3a89aa2e2f067c85db2fd
created_at: '2026-05-10T07:53:00+00:00'
subtasks:
- T020
- T021
- T022
- T023
- T024
- T025
history: []
authoritative_surface: packages/http-client/
execution_mode: code_change
owned_files:
- packages/http-client/src/
- packages/error-handler/src/
- packages/debug/src/
- packages/database-legacy/src/
- packages/typed-data/src/Primitive/
tags:
- audit-driven
- regression-tests-required
---

# WP02 — 8.5 deprecation sweep

## Objective

Locate and fix anything PHP 8.5 deprecates in our code. Audit-first via `rg`
across documented hot zones (see `tasks.md` § WP02 for the 5 audit greps and
the wider hot zone list); regression-test each finding; fix.

Ownership above is the **anchor set**. The audit greps run repo-wide; any
finding outside the owned set requires either expanding ownership in a
follow-up commit or filing a follow-up issue (per `tasks.md`).

## Acceptance

- For each of T020–T024: zero findings (documented in WP closing notes) **or**
  fix commit + regression test that fails on PHP 8.5 against the unfixed code.
- `vendor/bin/phpunit` shows zero PHP 8.5 deprecation notices originating in
  first-party code (vendor deprecations are out of scope; document them).

## Verification

```bash
rg -n '\((int|string|float|bool)\)\s*\$' packages/    # T020
rg -n 'curl_close|curl_share_close' packages/         # T021
rg -n 'ob_start\(' packages/                          # T022
rg -n 'register_shutdown_function' packages/          # T023
rg -n 'new \\?DateTime(Immutable)?\(' packages/       # T024
vendor/bin/phpunit                                    # zero deprecation notices
```

## Risks

- Deprecation surface unknown until measured; timebox each grep+audit at 30 min.
- Vendor-package deprecations leak into our test output; document and skip.
