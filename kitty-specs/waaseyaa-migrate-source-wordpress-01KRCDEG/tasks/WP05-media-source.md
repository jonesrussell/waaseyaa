---
work_package_id: WP05
title: 'Source plugin: WordPressMediaSource + media copy primitive'
dependencies:
- WP02
requirement_refs:
- FR-008
- FR-010
- FR-011
- FR-012
- FR-026
- FR-027
- FR-028
- FR-029
- FR-034
- FR-035
- FR-046
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
subtasks:
- T034
- T035
- T036
- T037
- T038
- T039
- T040
- T041
- T042
history: []
authoritative_surface: src/Source/WordPressMediaSource.php
execution_mode: code_change
owned_files:
- src/Source/WordPressMediaSource.php
- src/Media/MediaCopier.php
- src/Media/MediaCopyResult.php
- src/Exception/WordPressMediaCopyException.php
- tests/Unit/Source/WordPressMediaSourceTest.php
- tests/Unit/Media/MediaCopierTest.php
- tests/Conformance/MediaSourceConformanceTest.php
tags: []
agent: "claude"
shell_pid: "141979"
---

# WP05 — Source plugin: `WordPressMediaSource` + media copy primitive

## Objective

Yields one `SourceRecord` per WP attachment AND ships the `MediaCopier` primitive that handles idempotent local + HTTP copy of media files. Heavier than other source plugins because of the I/O surface.

## Context

- Spec: [`spec.md`](../spec.md) §3.2 (FR-008..FR-012) + §3.5 (FR-026..FR-029).
- Data model: [`data-model.md`](../data-model.md) §1.3.
- Research: [`research.md`](../research.md) §1.5 (idempotency strategy).
- Parallelizable with WP03, WP04, WP06, WP07. WP08 process plugins consume Media records.

## Implementation command

```
spec-kitty agent action implement WP05 --agent sonnet
```

## Subtask guidance

### T034 — `WordPressMediaSource` skeleton

Filter `$record['type'] === 'attachment'`. Same iterator pattern as WP03/WP04.

### T035 — Record shape per data-model.md §1.3

- `id` (int) — `<wp:post_id>`
- `file_path` (string) — `<wp:attachment_url>` minus host prefix
- `mime_type` (string) — derived from `_wp_attached_file` extension; fallback `application/octet-stream`
- `alt_text` (?string) — postmeta key `_wp_attachment_image_alt`
- `caption` (?string) — `<excerpt:encoded>`
- `description` (?string) — `<content:encoded>`
- `parent_post_id` (?int) — `<wp:post_parent>`; null if 0
- `original_url` (string) — full `<wp:attachment_url>`
- `size_bytes` (?int) — null until MediaCopier fills it post-fetch
- `_extra` (array)

### T036 — Postmeta extraction (handles two locations)

WP attachment metadata can live in `<wp:meta>` siblings OR inline as `<wp:postmeta><wp:meta_key>...</wp:meta_key><wp:meta_value>...</wp:meta_value></wp:postmeta>`. Implement extraction that tries both locations and merges.

This resolves research question Q1.

### T037 — `MediaCopier` with idempotency

```php
final class MediaCopier {
    public function copy(string $sourcePath, string $targetPath): MediaCopyResult {
        // 1. If target exists and size matches source: return MediaCopyResult::skipped()
        // 2. If target exists and size differs: warn, replace (treat as update)
        // 3. If target absent: stream-copy
        // 4. Throw WordPressMediaCopyException on I/O failure
    }
}
```

### T038 — Local-filesystem source

Source path like `/wp-content/uploads/2024/01/photo.jpg` resolves to `<source.media_path><file_path>`. Use `copy()` with stream context for large files.

### T039 — HTTP source

Source URL like `https://example.com/wp-content/uploads/2024/01/photo.jpg` resolves via streaming download. Use `psr/http-client` with retry (3 attempts, exponential backoff). Stream to a temp file, then atomic-rename to target.

### T040 — Optional hash verification

When WXR exposes `<wp:meta>` keys like `_wp_attachment_metadata.size` or `_wp_attached_file_hash`, verify size + hash post-copy. Otherwise skip (FR-029 SHOULD, not MUST).

### T041 — `WordPressMediaCopyException`

```php
final class WordPressMediaCopyException extends \RuntimeException {
    public const CODE_SOURCE_NOT_FOUND = 'wp_media.source_not_found';
    public const CODE_TARGET_WRITE_FAILED = 'wp_media.target_write_failed';
    public const CODE_HTTP_FETCH_FAILED = 'wp_media.http_fetch_failed';
    public const CODE_HASH_MISMATCH = 'wp_media.hash_mismatch';

    public static function sourceNotFound(string $path): self { /* */ }
    // etc.
}
```

### T042 — Conformance + unit tests

- Conformance: `MediaSourceConformanceTest extends SourceConformanceTestCase` against small-site fixture (3 attachments)
- Unit tests for `MediaCopier`:
  - First copy: target absent → stream copy → succeeds
  - Re-copy with same size: skipped (idempotent)
  - Re-copy with different size: replace + warn
  - Source not found: throws `CODE_SOURCE_NOT_FOUND`
  - HTTP retry: 2 failures + 1 success → ultimately succeeds (use a mock `HttpClientInterface`)
  - Hash mismatch when WXR exposes hash: throws `CODE_HASH_MISMATCH`

## Definition of Done

- [ ] All 3 attachments in small-site fixture extracted correctly
- [ ] `MediaCopier` is idempotent across re-runs (verified by test)
- [ ] HTTP source path works against a mock HTTP client
- [ ] Per-record error path: throwing in MediaCopier surfaces as a per-record warning in M-002's runner, doesn't halt the run unless `--halt-on-error`
- [ ] All 4 stable-surface entries (`WordPressMediaSource`, `MediaCopier`, `MediaCopyResult`, `WordPressMediaCopyException`) listed on public-surface-map.md

## Risks

- **HTTP fetch can take HOURS for large sites.** Document operator pre-flight in WP10: rsync the wp-content/uploads/ dir first, point `source.media_path` at the local copy.
- **Idempotency vs replace ambiguity.** When sizes differ, we replace. If the operator EXPECTS no-op on re-run (treats source as authoritative), the warn + replace surprises them. Document this in WP10's customization guide.

## Reviewer guidance

- The WP05 surface is bigger than other source-plugin WPs. Watch for sloppy error-code coverage in `WordPressMediaCopyException`.
- Verify the HTTP retry logic uses streaming (not in-memory buffering) — large attachments would OOM otherwise.
- Test the postmeta-from-two-locations extraction (T036) against a fixture variant where it appears in only one location.

## Activity Log

- 2026-05-14T21:37:33Z – claude – shell_pid=141979 – Started implementation via action command
