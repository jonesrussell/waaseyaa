---
work_package_id: WP07
title: 'Listing integration: TwoAxisFilterResolver routes Filter::langcode() through per-(entity, langcode) current-revision pointer; verify M-007 cache-tag emission'
dependencies:
- WP03
- WP04
requirement_refs:
- FR-030
- FR-031
- FR-032
- FR-033
- FR-033a
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: main
base_commit: 3b2af0d9aacac8436de314a5a402e1ba24b73cc0
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T041
- T042
- T043
- T044
- T045
agent: "claude:opus:python-reviewer:reviewer"
history: []
authoritative_surface: packages/entity-storage/src/Listing/TwoAxisFilterResolver.php
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Listing/TwoAxisFilterResolver.php
- packages/entity-storage/tests/Unit/Listing/TwoAxisFilterResolverTest.php
- tests/Integration/Phase29/TwoAxisListingInvalidationIntegrationTest.php
- tests/Integration/Phase29/TwoAxisListingFilterIntegrationTest.php
tags: []
shell_pid: "139519"
---

# Work Package Prompt: WP07 — Listing integration (TwoAxisFilterResolver + M-007 cache-tag verification)

## Mission context

- **Mission:** M-004 — Entity Storage Translatable Revisions (`entity-storage-translatable-revisions-01KRCDEE`)
- **Spec:** [`../spec.md`](../spec.md) §3.6 (listing pipeline integration), §12.2 (M-007 substrate audit), §12.5 R-08
- **Plan:** [`../plan.md`](../plan.md)

## Summary

M-007 already shipped `Filter::langcode()`, `ListingCacheInvalidator` (emits `entity:<type>:<id>:<langcode>` + langcode-less tags), and `language.content` cache-context auto-injection. M-004 WP07 is **verify + integrate** rather than design + build. Ship one new component: `TwoAxisFilterResolver` that routes `Filter::langcode('oj')` against a two-axis entity type to read each result entity at the per-`(entity, langcode)` current-revision pointer (FR-033a — the genuinely new contract). Verify M-007's `AfterSaveEvent::affectedLangcodes()` flow produces the right cache tags for two-axis saves (single + multi-language). No new `ListingDefinition::langcode` value-object field (FR-030 — M-007's canonical filter wins). No parallel `language.requested` context (FR-033 — `language.content` is canonical).

## Requirements covered

- FR-030 — consume M-007's `Filter::langcode()`; no new `ListingDefinition::langcode` field
- FR-031 — `Filter::langcode('oj')` returns only entities with the `(entity_id, 'oj')` translation row
- FR-032 — saves emit both `entity:<type>:<id>` and `entity:<type>:<id>:<langcode>` cache tags via `AfterSaveEvent::affectedLangcodes()` → `ListingCacheInvalidator`
- FR-033 — `language.content` cache context (M-007 canonical token); no `language.requested`
- FR-033a — filter resolver reads each result entity at the langcode's current revision

## Dependencies

This WP depends on: WP03 (save events for cache-tag emission), WP04 (read-at-langcode-revision semantics).

## Subtasks

- T041 — Implement `TwoAxisFilterResolver` (or equivalent hook) that intercepts `Filter::langcode($code)` on a two-axis entity type and routes the read to the per-`(entity, langcode)` current-revision pointer (FR-033a).
- T042 — Verify exclusion semantics: entities without the requested-langcode translation row are excluded (FR-031).
- T043 — Verify M-007's `ListingCacheInvalidator` emits both langcode-less and langcode-scoped tags from a two-axis save's `affectedLangcodes()` (FR-032).
- T044 — Verify `language.content` cache-context auto-injection from M-007's `ListingDefinition` triggers on two-axis types (FR-033).
- T045 — Write `TwoAxisFilterResolverTest`, `TwoAxisListingInvalidationIntegrationTest`, `TwoAxisListingFilterIntegrationTest` (Phase 29).

## Owned files

- `packages/entity-storage/src/Listing/TwoAxisFilterResolver.php`
- `packages/entity-storage/tests/Unit/Listing/TwoAxisFilterResolverTest.php`
- `tests/Integration/Phase29/TwoAxisListingInvalidationIntegrationTest.php`
- `tests/Integration/Phase29/TwoAxisListingFilterIntegrationTest.php`

## Acceptance

- M-007's existing `Filter::langcode()` + `ListingCacheInvalidator` behaviour unchanged for single-axis translatable types.
- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green.
- No modifications outside `owned_files`.

## Activity Log

(populated by implement-review loop)
- 2026-05-17T03:48:20Z – claude:sonnet:python-implementer:implementer – shell_pid=136822 – Started implementation via action command
- 2026-05-17T03:57:13Z – claude:sonnet:python-implementer:implementer – shell_pid=136822 – WP07 ready: listing integration (TwoAxisFilterResolver + M-007 substrate verification) - acae7dbc3
- 2026-05-17T03:57:50Z – claude:opus:python-reviewer:reviewer – shell_pid=139519 – Started review via action command
- 2026-05-17T03:59:05Z – claude:opus:python-reviewer:reviewer – shell_pid=139519 – WP07 review passed: TwoAxisFilterResolver in L1 entity-storage correctly wires per-(entity,langcode) current-revision read on top of M-007 listing surface; FR-031 + FR-033a satisfied; single-axis no-op verified; M-007 source untouched; 982 listing+entity-storage tests, 33 Phase29 tests, cs-check, phpstan, package-layers all green.
- 2026-05-17T04:20:24Z – claude:opus:python-reviewer:reviewer – shell_pid=139519 – Done override: M-004 merged to main as 70b867c39
