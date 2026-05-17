---
work_package_id: WP02
title: Contract ratification
dependencies:
- WP01
requirement_refs:
- FR-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T005
- T006
history: []
authoritative_surface: kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/contracts/
execution_mode: planning_artifact
mission_id: 01KQZR1ELIMSYMFALLBACKPATH01
mission_slug: foundation-symfony-fallback-elimination-01KQZR1
owned_files:
- kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/contracts/**
tags: []
---

# WP02 — Contract ratification

## Objective

Turn the draft contract into the **approved** behavioral spec for resolver + routing + dispatch. **Do not** edit `docs/specs/infrastructure.md` here — WP05 syncs the canonical spec after code lands.

## Dependencies

WP01’s `artifacts/fallback-inventory.md` must exist.

## Subtasks

### T005 — Review draft

Read `contracts/resolution-and-dispatch.md` against the inventory. Mark each section **approved** or **revised** in the contract file.

### T006 — Finalize contract prose

Update `contracts/resolution-and-dispatch.md` with: exact outcome for routing misses; exact `_controller` rule; resolver delegation rule (no duplicate kernel maps).

## Validation

- [ ] Contract file has no unresolved `draft` markers for WP03–WP04.
