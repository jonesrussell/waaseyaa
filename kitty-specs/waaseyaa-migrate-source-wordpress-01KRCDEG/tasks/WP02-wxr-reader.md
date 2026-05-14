---
work_package_id: WP02
title: WXR streaming parser (WxrReader)
dependencies:
- WP01
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-034
- FR-035
- FR-036
- FR-038
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
subtasks:
- T011
- T012
- T013
- T014
- T015
- T016
- T017
- T018
- T019
- T020
- T021
history: []
authoritative_surface: src/Wxr/
execution_mode: code_change
owned_files:
- src/Wxr/WxrReader.php
- src/Wxr/WxrVersion.php
- src/Exception/WxrParseException.php
- tests/Unit/Wxr/WxrReaderTest.php
- testing/Fixtures/small-site.xml
- testing/Fixtures/edge-cases/malformed-entries.xml
- testing/Fixtures/edge-cases/unicode.xml
- testing/Fixtures/edge-cases/plugin-namespaces.xml
tags: []
agent: "claude"
shell_pid: "123052"
---

# WP02 — WXR streaming parser (`WxrReader`)

## Objective

Ship a memory-bounded, streaming WXR XML parser that yields raw record arrays + type discriminator for downstream source plugins. This unblocks WP03..WP07 (five parallel source plugins).

## Context

- Mission: M-005, see [`spec.md`](../spec.md) §3.1 (FR-001..FR-004).
- Research: [`research.md`](../research.md) §1.1..§1.3 (XMLReader choice, version support, recovery model).
- WXR specification: https://wordpress.org/documentation/article/wxr-files/
- WordPress core importer (prior art): `wp-admin/includes/class-wp-importer.php`

## Implementation command

```
spec-kitty agent action implement WP02 --agent sonnet
```

## Subtask guidance

### T011 — `WxrVersion` enum

```php
<?php
declare(strict_types=1);
namespace Waaseyaa\Migrate\Source\WordPress\Wxr;

enum WxrVersion: string {
    case V_1_0 = '1.0';
    case V_1_1 = '1.1';
    case V_1_2 = '1.2';

    public static function fromString(string $raw): self {
        return self::tryFrom($raw)
            ?? throw WxrParseException::unsupportedVersion($raw);
    }
}
```

### T012 — `WxrReader` core

Public surface:

```php
<?php
declare(strict_types=1);
namespace Waaseyaa\Migrate\Source\WordPress\Wxr;

final class WxrReader {
    public function __construct(
        private readonly string $filePath,
        private readonly bool $strict = false,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /** @return iterable<array{type: string, data: array<string, mixed>}> */
    public function records(): iterable {
        // XMLReader pull-parsing; yield {type, data} per top-level entity
    }

    public function version(): WxrVersion { /* set on first record */ }
}
```

Use `XMLReader::open($this->filePath)` + `XMLReader::read()` loop. For each `<wp:author>`, `<wp:term>`/`<wp:category>`/`<wp:tag>`, or `<item>`, call `XMLReader::expand()` → `SimpleXMLElement` for field extraction. Yield as `{type, data}`.

### T013 — Version detection

On document open, look for `<wp:wxr_version>` (in the `wp` namespace) before yielding any record. If present and supported, set `$this->version`. If unsupported, throw `WxrParseException::unsupportedVersion($raw)`. If absent, default to V_1_2 with a warning log entry (some pre-WP-3.0 exports omit the version element).

### T014 — Recovery model

```php
libxml_use_internal_errors(true);
// per-record:
$libxml_errors = libxml_get_errors();
libxml_clear_errors();
if (!empty($libxml_errors)) {
    if ($this->strict) {
        throw WxrParseException::recordParseFailure($recordIndex, $libxml_errors);
    }
    $this->logger?->warning(
        'WXR record skipped due to parse errors',
        ['record_index' => $recordIndex, 'errors' => array_map(fn($e) => $e->message, $libxml_errors)],
    );
    continue; // skip this record
}
```

### T015 — Type discriminator

For each yielded record, the `type` field is one of: `'post'`, `'user'`, `'comment'`, `'attachment'`, `'term'`. Derive from:
- `<wp:author>` → `'user'`
- `<wp:term>` / `<wp:category>` / `<wp:tag>` → `'term'`
- `<item>` with `<wp:post_type>attachment</wp:post_type>` → `'attachment'`
- `<item>` with any other `<wp:post_type>` → `'post'`
- `<wp:comment>` (nested under `<item>`) → `'comment'`

### T016 — Opaque pass-through for unknown namespaces

After extracting known fields, capture any unrecognized namespaced child elements as raw XML strings under a `_extra` key in the record data. Don't fail on unknowns. WooCommerce, Yoast, etc. inject custom XML; ignoring them is unacceptable, failing on them blocks the import.

### T017 — Memory safety

Every 100 records, call `gc_collect_cycles()`. Document the choice with an inline comment citing research §3 risk 2.

### T018 — `WxrParseException`

```php
final class WxrParseException extends SourceReadException {
    public const CODE_UNSUPPORTED_VERSION = 'wxr.unsupported_version';
    public const CODE_RECORD_PARSE_FAILURE = 'wxr.record_parse_failure';
    public const CODE_FILE_NOT_FOUND = 'wxr.file_not_found';

    public static function unsupportedVersion(string $raw): self { /* */ }
    public static function recordParseFailure(int $index, array $errors): self { /* */ }
    public static function fileNotFound(string $path): self { /* */ }
}
```

Extends `SourceReadException` from M-002 substrate per FR-036.

### T019 — Small-site fixture

Hand-author `testing/Fixtures/small-site.xml` (WXR 1.2):
- 5 posts (mix of `post` and `page` types)
- 2 users (1 admin, 1 author)
- 3 attachments (jpg, png, pdf)
- 4 comments (1 thread of 2 replies, 2 standalone)
- 6 terms (4 categories, 2 tags)

Keep the file under 50 KB. This is the WP09 acceptance gate's primary fixture.

### T020 — Edge-case fixtures

- `malformed-entries.xml`: One valid post + one with broken CDATA + one with NUL byte. Tests T014 recovery.
- `unicode.xml`: Multibyte UTF-8 chars in title, content, author name. Tests no charset corruption.
- `plugin-namespaces.xml`: Valid post + WooCommerce-style `<wc:product_id>` elements + Yoast-style `<yoast:focus_keyword>`. Tests T016 pass-through.

### T021 — Unit tests

Test cases:
- Parses small-site.xml with expected record counts (5 + 2 + 3 + 4 + 6 = 20 records, broken down by type)
- Parses unicode.xml without corruption
- Parses plugin-namespaces.xml with `_extra` populated
- Skips malformed-entries.xml's broken record + warns; counts good records correctly
- Strict mode: malformed-entries.xml throws `WxrParseException`
- Rejects WXR 0.9 and WXR 2.0 with `WxrParseException::unsupportedVersion`
- Rejects nonexistent file with `WxrParseException::fileNotFound`

## Definition of Done

- [ ] `WxrReader` parses all small/medium/edge fixtures in CI
- [ ] Memory bound verified: parses a 100MB synthetic WXR with peak memory < 50MB
- [ ] Recovery semantics: malformed records skip in default mode, throw in --strict
- [ ] Version rejection: pre-1.0 and post-1.2 throw cleanly
- [ ] All unit tests pass; conformance gate not yet applicable (no source plugins to test)
- [ ] `WxrParseException` listed on `public-surface-map.md` with stable code constants

## Risks

- **libxml namespace handling quirks.** PHP's libxml registers namespaces lazily; `XMLReader::expand()` results may need explicit `registerXPathNamespace()` for queries. Document workarounds inline if encountered.
- **Memory bloat under sustained pressure.** XMLReader IS streaming, but PHP's libxml has historical memory growth in long runs. T017 mitigates; verify with the medium fixture in WP09.
- **Plugin-namespace fixture coverage.** Only WooCommerce + Yoast covered in T020. Real-world variance is wider; revisit fixture list if WP09 surfaces failures.

## Reviewer guidance

Verify:
- No eager `file_get_contents()` or `simplexml_load_file()` in the parse loop
- libxml internal errors enabled BEFORE the parse loop, cleared per-record
- Generator yields (no array buffering) — `iterable<array>` return type honoured
- All exception factory methods return `self` (named-constructor pattern per CLAUDE.md)
- Test assertions check both record COUNT and field VALUES, not just structural shape

## Activity Log

- 2026-05-14T19:24:39Z – claude – shell_pid=123052 – Started implementation via action command
