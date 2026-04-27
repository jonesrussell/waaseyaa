---
work_package_id: "WP09"
title: "Documentation: static analysis of #[Field]"
dependencies: ["WP08"]
planning_base_branch: "main"
merge_target_branch: "main"
branch_strategy: "Planning artifacts were generated on main; completed changes must merge back into main."
subtasks:
  - "T023"
  - "T024"
phase: "Phase 4 - Documentation"
assignee: ""
agent: ""
shell_pid: ""
authoritative_surface: "docs/specs/entity-system"
execution_mode: "code_change"
mission_id: "01KQ6XW7Y3QD0JJ7JTP9JCSDPM"
mission_slug: "attribute-first-entity-static-analysis-01KQ6XW7"
owned_files:
  - "docs/specs/entity-system.md"
  - "CHANGELOG.md"
tags: []
history:
  - timestamp: "2026-04-27T07:42:00Z"
    agent: "system"
    action: "Prompt generated via /spec-kitty.tasks"
---

# Work Package Prompt: WP09 — Documentation

## Objective

Satisfy C-005: announce the static analysis surface to framework consumers.

## Subtask Guidance

### T023 — entity-system.md section

Add a section "Static analysis of `#[Field]`" near the existing
`#[Field]` discussion. Cover:

- The 6 detection rules (one short bullet each, matching FR-001..FR-006).
- The opt-in line for downstream consumers' `phpstan.neon`:

  ```neon
  includes:
      - vendor/waaseyaa/entity/phpstan-rules.neon
  ```

- That FR-007 guarantees PHPStan errors match runtime errors byte-for-byte.
- Pointer to `packages/entity/src/PhpStan/FieldAttributeRule.php` for source.

### T024 — CHANGELOG.md entry

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
