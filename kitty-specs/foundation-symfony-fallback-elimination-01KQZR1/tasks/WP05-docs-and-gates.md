---
work_package_id: WP05
title: Docs changelog and repo gates
dependencies:
- WP04
requirement_refs:
- FR-004
- FR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T016
- T017
- T018
- T019
- T020
history: []
authoritative_surface: docs/
execution_mode: code_change
mission_id: 01KQZR1ELIMSYMFALLBACKPATH01
mission_slug: foundation-symfony-fallback-elimination-01KQZR1
owned_files:
- CHANGELOG.md
- docs/specs/infrastructure.md
tags: []
---

# WP05 — Docs, changelog, and gates

## Objective

Sync canonical docs with merged code, ship CHANGELOG notes, run full repo gates. If `packages/ssr` was modified during WP03–WP04, include `./vendor/bin/phpunit packages/ssr` in T019.

## Subtasks

### T016 — SSR follow-up (conditional)

If WP03–WP04 touched `packages/ssr`, run its PHPUnit subset and fix failures; if not touched, skip with a one-line note in the WP completion comment.

### T017 — CHANGELOG

`[Unreleased]` bullets for resolver, routing outcomes, controller contract; link mission slug `foundation-symfony-fallback-elimination-01KQZR1`.

### T018 — infrastructure.md

Reconcile `docs/specs/infrastructure.md` with final code (kernel-services bus + HTTP resolver + dispatch boundary).

### T019 — Full gates

```bash
./vendor/bin/phpunit
composer phpstan
composer cs-check
```

### T020 — Lifecycle drift

Run `scripts/check-lifecycle-drift.sh` when present; if the script requires a lifecycle doc update per repo policy, apply it (path is defined by the script / `CLAUDE.md` — may not exist until created by policy).

## Validation

- [ ] T019 commands pass.
- [ ] Lifecycle script passes or lifecycle doc updated with rationale.
