---
work_package_id: WP07
title: 'Source plugin: WordPressCommentSource'
dependencies:
- WP02
requirement_refs:
- FR-007
- FR-010
- FR-011
- FR-012
- FR-037
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
subtasks:
- T050
- T051
- T052
- T053
- T054
- T055
- T056
history: []
authoritative_surface: src/Source/WordPressCommentSource.php
execution_mode: code_change
owned_files:
- src/Source/WordPressCommentSource.php
- tests/Unit/Source/WordPressCommentSourceTest.php
- tests/Conformance/CommentSourceConformanceTest.php
tags: []
agent: "claude"
shell_pid: "145812"
---

# WP07 — Source plugin: `WordPressCommentSource`

## Objective

Yields one `SourceRecord` per WP comment. Comments are nested under post `<item>` elements in WXR, so iteration order requires re-emitting WxrReader per-post or filtering after the read.

## Context

- Spec: [`spec.md`](../spec.md) §3.2 (FR-007, FR-010..FR-012).
- Data model: [`data-model.md`](../data-model.md) §1.5.
- Research: [`research.md`](../research.md) §1.12 (preserve raw `comment_parent`, no flattening).
- Parallelizable with WP03..WP06 after WP02.

## Implementation command

```
spec-kitty agent action implement WP07 --agent sonnet
```

## Subtask guidance

### T050 — `WordPressCommentSource` skeleton

Comments are NESTED under `<item>` elements in WXR (1+ comments per post). WxrReader's discriminator emits `'comment'` records with `post_id` already extracted from the parent item. Filter `$record['type'] === 'comment'`.

If WxrReader doesn't emit comments yet, this WP also requires a small WP02 amendment — coordinate with WP02 reviewer if so. The WxrReader spec (FR-004) names `comment` as a discriminator value, so it should be in scope.

### T051 — Record shape per data-model.md §1.5

- `id`, `post_id`, `parent_id`, `author`, `author_email`, `author_url`, `author_ip`, `content`, `published_at`, `approved`, `comment_type`, `user_login`, `_extra`

The `post_id` is the parent post's WP id (extracted by WxrReader from the enclosing `<item>`).

### T052 — `approved` field mapping

WP's `<wp:comment_approved>` is a string: `1` (approved), `0` (pending), `spam`, `trash`. Map to a structured representation:

```php
$approved = match ((string) $node->{'wp:comment_approved'}) {
    '1' => true,
    '0', 'spam', 'trash' => false,
    default => false,
};
```

For consumers that care about moderation state, surface the raw value as `_extra['approved_raw']` so they can distinguish spam from pending.

### T053 — Preserve raw `comment_parent`

`<wp:comment_parent>` may be `0` (top-level) or another comment's id (threaded reply). Map `0` → `null`. Otherwise preserve the integer.

DO NOT flatten threading. Even if the destination engagement entity doesn't support trees, preserve the raw reference for downstream feature work (research §1.12).

### T054 — `sourceIdFor()`

`SourceId::fromCanonical(['type' => 'wp_comment', 'id' => $rawData['id']])`.

### T055 — Conformance test

Small-site fixture has 4 comments (1 thread of 2 replies + 2 standalone). Conformance test extends `SourceConformanceTestCase`.

### T056 — Unit tests

- 4 comments extracted from small-site fixture
- Threading: parent comment yields `parent_id: null`; reply yields `parent_id: <comment_id>`
- Spam comment yields `approved: false` AND `_extra['approved_raw'] === 'spam'`
- Pingback (`<wp:comment_type>pingback</wp:comment_type>`) extracted with `comment_type: 'pingback'`
- Registered-user comment (`<wp:comment_user_id>` non-zero) yields `user_login` populated via cross-reference (note: this requires user records to be parsed first — document the pre-condition; resolved at WP09 by migration ordering)

## Definition of Done

- [ ] All 4 comments extracted from small-site fixture
- [ ] Threading preserved (parent_id null for top-level, integer for replies)
- [ ] Approved-state mapping covers all 4 raw values + preserves raw in _extra
- [ ] Pingback/trackback extracted with correct comment_type
- [ ] Conformance test passes
- [ ] `WordPressCommentSource` listed on public-surface-map.md

## Reviewer guidance

- WP07 is the only source plugin with NESTED parent context (`post_id` from enclosing `<item>`). Verify WxrReader actually emits this — if not, this WP needs to handle it itself by re-iterating.
- The user_login cross-reference (T056) is informational at this stage — actual lookup happens in WP09's WpCommentsToEngagement migration.

## Activity Log

- 2026-05-14T22:21:17Z – claude – shell_pid=145812 – Started implementation via action command
- 2026-05-14T22:23:01Z – claude – shell_pid=145812 – Out-of-tree WP: deliverables at standalone-repo commit 6121800. Files: src/Source/WordPressCommentSource.php (filters WxrReader to type=comment, ISO 8601 normalization, preserves threading verbatim), tests/Unit/Source/WordPressCommentSourceTest.php (14 tests covering threading, approved-state mapping for 1/0/spam/trash, pingbacks, user_login cross-ref), tests/Conformance/CommentSourceConformanceTest.php (8 C1-C8 gates green). pest: 128 passed (25343 assertions). phpstan --level=5: clean.
