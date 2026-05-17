---
work_package_id: WP05
title: "Access policy composition: view_revision + translate operations on translation instance (with ?RevisionableEntityInterface $revision)"
dependencies:
- WP04
requirement_refs:
- FR-020
- FR-021
- FR-022
- FR-023
- FR-024
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: main
base_commit: 3b2af0d9aacac8436de314a5a402e1ba24b73cc0
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T030
- T031
- T032
- T033
- T034
shell_pid: "130869"
history: []
authoritative_surface: packages/access/src/Policy/RevisionPolicyComposition.php
execution_mode: code_change
owned_files:
- packages/access/src/Policy/RevisionPolicyComposition.php
- packages/access/tests/Unit/Policy/RevisionPolicyCompositionTest.php
- packages/access/tests/Unit/Policy/TwoAxisPolicyFallbackTest.php
- tests/Integration/Phase29/TwoAxisAccessPolicyIntegrationTest.php
agent: "claude:sonnet:python-implementer:implementer"
---

# Work Package Prompt: WP05 — Access policy composition for view_revision + translate on translation instance

## Mission context

- **Mission:** M-004 — Entity Storage Translatable Revisions (`entity-storage-translatable-revisions-01KRCDEE`)
- **Spec:** [`../spec.md`](../spec.md) §3.4 (access policy composition), §12.1 R-07
- **Plan:** [`../plan.md`](../plan.md)
- **Contract:** [`../contracts/access-policy-revision.md`](../contracts/access-policy-revision.md)

## Summary

Compose existing `view_revision` and `translate` access operations onto the translation instance — no new `view_translation_revision` operation (FR-023). Policy methods receive the translation instance plus an optional `?RevisionableEntityInterface $revision = null` parameter (resolves §9 Q7), enabling introspection of `revisionAuthor()` / `revisionCreatedAt()` without a second lookup. Implement fallback semantics per ADR 016 / ADR 017: missing `view_revision` falls back to `view`; missing `translate` falls back to `edit`. Worked example fixture (Coordinator vs Knowledge-Keeper on Anishinaabemowin) ships in the integration test as preview for the WP08 validation gate.

## Requirements covered

- FR-020 — `view_revision` and `translate` operations apply to translation instance; policies may introspect `activeLangcode()`
- FR-021 — missing `view_revision` falls back to `view`
- FR-022 — missing `translate` falls back to `edit`
- FR-023 — no new `view_translation_revision` operation
- FR-024 — Minoo Coordinator-vs-Knowledge-Keeper worked example

## Dependencies

This WP depends on: WP04 (load semantics — composition consumes `getTranslation()->loadRevision()`).

## Subtasks

- T030 — Implement `RevisionPolicyComposition` helper / policy resolver that routes `view_revision` and `translate` operations to the translation instance (FR-020).
- T031 — Implement `view_revision` → `view` fallback per ADR 016 FR-040 (FR-021).
- T032 — Implement `translate` → `edit` fallback per ADR 017 (FR-022).
- T033 — Add optional `?RevisionableEntityInterface $revision = null` parameter to the revision-aware policy signature; document in `contracts/access-policy-revision.md`. Existing single-axis policies continue to work (parameter is optional).
- T034 — Write `RevisionPolicyCompositionTest`, `TwoAxisPolicyFallbackTest`, `TwoAxisAccessPolicyIntegrationTest` (Coordinator vs Knowledge-Keeper Anishinaabemowin fixture — preview of WP08 gate) (FR-024).

## Owned files

- `packages/access/src/Policy/RevisionPolicyComposition.php`
- `packages/access/tests/Unit/Policy/RevisionPolicyCompositionTest.php`
- `packages/access/tests/Unit/Policy/TwoAxisPolicyFallbackTest.php`
- `tests/Integration/Phase29/TwoAxisAccessPolicyIntegrationTest.php`

## Acceptance

- No new top-level access operation registered; composition uses existing `view_revision` + `translate`.
- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green.
- No modifications outside `owned_files`.

## Activity Log

(populated by implement-review loop)
- 2026-05-17T03:26:43Z – claude:sonnet:python-implementer:implementer – shell_pid=130869 – Started implementation via action command
- 2026-05-17T03:34:17Z – claude:sonnet:python-implementer:implementer – shell_pid=130869 – WP05 ready: access policy composition (T030-T034 covered by RevisionPolicyComposition + 3 tests; all gates green)
