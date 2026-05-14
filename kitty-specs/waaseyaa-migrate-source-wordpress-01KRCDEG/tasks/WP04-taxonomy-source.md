---
work_package_id: WP04
title: 'Source plugin: WordPressTaxonomySource'
dependencies:
- WP02
requirement_refs:
- FR-009
- FR-010
- FR-011
- FR-012
- FR-037
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
subtasks:
- T028
- T029
- T030
- T031
- T032
- T033
history: []
authoritative_surface: src/Source/WordPressTaxonomySource.php
execution_mode: code_change
owned_files:
- src/Source/WordPressTaxonomySource.php
- tests/Unit/Source/WordPressTaxonomySourceTest.php
- tests/Conformance/TaxonomySourceConformanceTest.php
tags: []
agent: "claude"
shell_pid: "140720"
---

# WP04 — Source plugin: `WordPressTaxonomySource`

## Objective

Yields one `SourceRecord` per WP term, handling the three WXR element variants (`<wp:category>`, `<wp:tag>`, `<wp:term>`).

## Context

- Spec: [`spec.md`](../spec.md) §3.2 (FR-009..FR-012).
- Data model: [`data-model.md`](../data-model.md) §1.2.
- Parallelizable with WP03, WP05, WP06, WP07 after WP02.

## Implementation command

```
spec-kitty agent action implement WP04 --agent sonnet
```

## Subtask guidance

### T028 — `WordPressTaxonomySource` skeleton

Same shape as `WordPressUserSource` (WP03 reference). Filter `$record['type'] === 'term'`. Construct receives a `WxrReader`.

### T029 — Record shape per data-model.md §1.2

- `id` (int) — `<wp:term_id>` or implicit for legacy `<wp:category>`
- `taxonomy_name` (string) — `<wp:category_taxonomy>` or fixed (`category`/`post_tag`)
- `name` (string) — `<wp:cat_name>` / `<wp:tag_name>` / `<wp:term_name>`
- `slug` (string) — `<wp:category_nicename>` / `<wp:tag_slug>` / `<wp:term_slug>`
- `description` (?string) — optional
- `parent_slug` (?string) — for hierarchical terms; resolved via lookup downstream
- `_extra` (array) — pass-through for unknown attrs

### T030 — Legacy implicit term ids (WXR 1.0/1.1)

Some old `<wp:category>` elements lack explicit `<wp:term_id>`. Generate a stable synthetic id from `crc32($taxonomy_name . ':' . $slug)`. Document this in code comments — it's a non-obvious back-compat hack.

### T031 — `sourceIdFor()`

`SourceId::fromCanonical(['type' => 'wp_term', 'id' => $rawData['id']])` per data-model §2.

### T032 — Conformance test

Mirror WP03's pattern. Extends `SourceConformanceTestCase` against the small-site fixture (which has 6 terms: 4 categories, 2 tags).

### T033 — Unit tests

- All 3 element variants extracted correctly
- Hierarchical: parent slug extraction + null for top-level
- Implicit ids in legacy fixture variants generate stable synthetic ids
- Custom taxonomy name (`<wp:category_taxonomy>` non-default) preserved

## Definition of Done

- [ ] All 6 terms in small-site fixture extracted with correct fields
- [ ] Conformance test passes
- [ ] Unit tests cover all 3 element variants + legacy implicit ids
- [ ] `WordPressTaxonomySource` listed on public-surface-map.md (`present: true`)

## Reviewer guidance

- The 3-element-variant handling is the trap — easy to special-case the dispatch wrong. Verify exhaustive coverage in tests.
- Implicit-id synthesis MUST be deterministic (same fixture twice → same SourceId).

## Activity Log

- 2026-05-14T21:34:41Z – claude – shell_pid=140720 – Started implementation via action command
- 2026-05-14T21:36:28Z – claude – shell_pid=140720 – Out-of-tree WP: deliverables at standalone-repo commit fbf234f. Files: src/Source/WordPressTaxonomySource.php, tests/Unit/Source/WordPressTaxonomySourceTest.php (12 Pest tests covering all 3 WXR element variants + legacy implicit-id determinism), tests/Conformance/TaxonomySourceConformanceTest.php (8 C1-C8 gates green with 5000-term large fixture). pest: 51 passed (10149 assertions). phpstan --level=5 src+tests: clean.
