---
work_package_id: WP04
title: 'Documentation: static analysis of #[Field]'
dependencies:
- WP03
requirement_refs:
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T022
- T023
phase: Phase 4 - Documentation
assignee: ''
agent: "claude"
shell_pid: "36192"
history:
- timestamp: '2026-04-27T07:42:00Z'
  agent: system
  action: Prompt generated via /spec-kitty.tasks
authoritative_surface: docs/specs/entity-system
execution_mode: code_change
mission_id: 01KQ6XW7Y3QD0JJ7JTP9JCSDPM
mission_slug: attribute-first-entity-static-analysis-01KQ6XW7
owned_files:
- docs/specs/entity-system.md
- CHANGELOG.md
tags: []
---

# Work Package Prompt: WP04 — Documentation

## Objective

Satisfy C-005: announce the static analysis surface to framework consumers.

## Subtasks

### T022 — entity-system.md section

Add a section "Static analysis of `#[Field]`" near the existing `#[Field]`
discussion. Cover:

- The 6 detection rules (one short bullet each, matching FR-001..FR-006).
- The opt-in line for downstream consumers' `phpstan.neon`:

  ```neon
  includes:
      - vendor/waaseyaa/entity/phpstan-rules.neon
  ```

- That FR-007 guarantees PHPStan errors match runtime errors byte-for-byte.
- Pointer to `packages/entity/src/PhpStan/FieldAttributeRule.php` for source.

### T023 — CHANGELOG.md entry

Under the next unreleased version add:

```markdown
### Added
- PHPStan rule `Waaseyaa\Entity\PhpStan\FieldAttributeRule` lints `#[Field]`
  attribute usage at static-analysis time, mirroring `FieldTypeInferrer`'s
  runtime checks. Downstream consumers opt in by adding
  `includes: [vendor/waaseyaa/entity/phpstan-rules.neon]` to their
  `phpstan.neon`.
```

## Validation

- [ ] `docs/specs/entity-system.md` includes the new section with all 6 rules listed.
- [ ] `CHANGELOG.md` entry present under unreleased.
- [ ] No code changes in this WP.

## Activity Log

- 2026-04-27T08:15:41Z – claude – shell_pid=36192 – Started implementation via action command
