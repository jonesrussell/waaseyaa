---
work_package_id: WP06
title: 'Source plugin: WordPressPostSource'
dependencies:
- WP02
requirement_refs:
- FR-005
- FR-010
- FR-011
- FR-012
- FR-037
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
subtasks:
- T043
- T044
- T045
- T046
- T047
- T048
- T049
history: []
authoritative_surface: src/Source/WordPressPostSource.php
execution_mode: code_change
owned_files:
- src/Source/WordPressPostSource.php
- tests/Unit/Source/WordPressPostSourceTest.php
- tests/Conformance/PostSourceConformanceTest.php
tags: []
---

# WP06 — Source plugin: `WordPressPostSource`

## Objective

Yields one `SourceRecord` per WP post (all post types except `attachment`). Single source plugin handles posts, pages, AND custom post types — consumers filter downstream by `post_type` field.

## Context

- Spec: [`spec.md`](../spec.md) §3.2 (FR-005, FR-010..FR-012).
- Data model: [`data-model.md`](../data-model.md) §1.4.
- Research: [`research.md`](../research.md) §1.4 (single PostSource + downstream filter, NOT per-CPT plugins).
- Parallelizable with WP03, WP04, WP05, WP07. WP08 process plugins consume Post records.

## Implementation command

```
spec-kitty agent action implement WP06 --agent sonnet
```

## Subtask guidance

### T043 — `WordPressPostSource` skeleton

Filter `$record['type'] === 'post'` (this includes posts, pages, AND CPTs since WxrReader's discriminator excludes attachments).

### T044 — Record shape per data-model.md §1.4

The richest record shape in the mission. Required fields:
- `id`, `post_type`, `title`, `slug`, `content`, `excerpt`, `status`, `published_at`, `modified_at`, `author_login`, `parent_id`, `terms`, `comment_status`, `password`, `_extra`

See data-model §1.4 for WXR sources of each.

### T045 — `<category>` extraction

Posts have multiple `<category>` elements with `domain` attribute (taxonomy name) and `nicename` attribute (slug). Extract as:

```php
$terms = [];
foreach ($simpleXml->category as $cat) {
    $terms[] = ['taxonomy' => (string) $cat['domain'], 'slug' => (string) $cat['nicename']];
}
```

### T046 — `published_at` fallback chain

```php
$published = (string) ($node->{'wp:post_date_gmt'} ?? '');
if ($published === '' || $published === '0000-00-00 00:00:00') {
    $published = (string) ($node->{'wp:post_date'} ?? '');
}
// Convert to ISO 8601
```

`0000-00-00 00:00:00` is WP's "no date set" sentinel; treat as missing.

### T047 — `sourceIdFor()`

`SourceId::fromCanonical(['type' => 'wp_post', 'id' => $rawData['id']])`.

### T048 — Conformance test

Mirror WP03's pattern. Small-site fixture has 5 posts (mix of `post` and `page` types).

### T049 — Unit tests

- 5 posts in small-site fixture extracted
- Mix of `post`, `page` post_types preserved (not filtered out)
- CPT post (synthesize one in a test fixture variant) extracted with non-default `post_type`
- Empty content doesn't crash
- Password-protected post: `password` field populated
- Missing `post_date_gmt` falls through to `post_date`
- Multiple `<category>` elements all captured in `terms` array
- `parent_id` for child pages preserved

## Definition of Done

- [ ] All 5 posts extracted with correct fields
- [ ] CPT support verified (test fixture variant)
- [ ] Conformance test passes
- [ ] published_at fallback chain handles WP's `0000-00-00` sentinel
- [ ] `WordPressPostSource` listed on public-surface-map.md

## Reviewer guidance

- Watch for the `dc:creator` namespace handling — needs explicit XPath registration in some libxml versions
- Verify CPT support isn't accidentally filtered out (the discriminator is "not attachment", not "is post")
- The `terms` array shape is a downstream interface — must match what WP09's WpPostsToArticles expects
