---
work_package_id: WP03
title: 'Source plugin: WordPressUserSource'
dependencies:
- WP02
requirement_refs:
- FR-006
- FR-010
- FR-011
- FR-012
- FR-037
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
subtasks:
- T022
- T023
- T024
- T025
- T026
- T027
history: []
authoritative_surface: src/Source/WordPressUserSource.php
execution_mode: code_change
owned_files:
- src/Source/WordPressUserSource.php
- tests/Unit/Source/WordPressUserSourceTest.php
- tests/Conformance/UserSourceConformanceTest.php
tags: []
agent: "claude"
shell_pid: "137727"
---

# WP03 — Source plugin: `WordPressUserSource`

## Objective

Ship the first WordPress source plugin: yields one `SourceRecord` per WP user. Conforms to M-002's `SourcePluginInterface` and passes `SourceConformanceTestCase`.

## Context

- Mission: M-005, see [`spec.md`](../spec.md) §3.2 (FR-006, FR-010..FR-012).
- Data model: [`data-model.md`](../data-model.md) §1.1 — User record shape.
- Substrate contracts: M-002 `SourcePluginInterface`, `SourceRecord`, `SourceId`, `SourceConformanceTestCase`.
- This WP is parallelizable with WP04, WP05, WP06, WP07 after WP02 lands.

## Implementation command

```
spec-kitty agent action implement WP03 --agent sonnet
```

## Subtask guidance

### T022 — Implement `WordPressUserSource`

```php
<?php
declare(strict_types=1);
namespace Waaseyaa\Migrate\Source\WordPress\Source;

use Waaseyaa\Migrate\Source\WordPress\Wxr\WxrReader;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\SourceId;

final class WordPressUserSource implements SourcePluginInterface
{
    public function __construct(private readonly WxrReader $reader) {}

    /** @return iterable<SourceRecord> */
    public function records(): iterable
    {
        foreach ($this->reader->records() as $record) {
            if ($record['type'] !== 'user') continue;
            yield new SourceRecord(
                sourceId: $this->sourceIdFor($record['data']),
                fields: $this->extractFields($record['data']),
            );
        }
    }

    public function sourceIdFor(array $rawData): SourceId
    {
        return SourceId::fromCanonical(['type' => 'wp_user', 'id' => $rawData['id']]);
    }

    public function supportsQuery(): bool { return false; }

    private function extractFields(array $rawData): array { /* per data-model §1.1 */ }
}
```

### T023 — Record shape per data-model.md §1.1

Required fields:
- `id` (int) ← `<wp:author_id>`
- `login` (string) ← `<wp:author_login>`
- `email` (string) ← `<wp:author_email>` — empty allowed for legacy exports
- `display_name` (string) ← `<wp:author_display_name>`, fallback to login
- `first_name` (?string) ← `<wp:author_first_name>`, optional
- `last_name` (?string) ← `<wp:author_last_name>`, optional
- `registered` (?string ISO 8601) ← `<wp:author_registered_date>`, only WXR 1.2+
- `role` (string) ← `<wp:author_role>`
- `_extra` (array) ← unmapped namespaced attributes

### T024 — `sourceIdFor()` deterministic hash

Per M-002 FR-027 + data-model §2: `SourceId::fromCanonical(['type' => 'wp_user', 'id' => $wp_id])` produces sha256 of the canonical JSON. The `'wp_user'` type prefix prevents collisions across entity types.

### T025 — `supportsQuery(): false`

WXR is not queryable; consumers iterate. Returning false signals the substrate to use the iteration path, not the query path.

### T026 — Conformance test

```php
final class UserSourceConformanceTest extends SourceConformanceTestCase
{
    protected function makeSource(): SourcePluginInterface
    {
        return new WordPressUserSource(new WxrReader(__DIR__ . '/../../testing/Fixtures/small-site.xml'));
    }
}
```

`SourceConformanceTestCase` is the M-002 contract test that exercises every required `SourcePluginInterface` invariant. Uses the small-site fixture from WP02.

### T027 — Unit tests

- 2 users in small-site fixture → 2 SourceRecords yielded
- Fields populated correctly per data-model.md §1.1
- Empty `email` doesn't crash
- Missing `first_name`/`last_name` yields `null`
- Unknown namespace attributes preserved in `_extra`
- `sourceIdFor()` is deterministic across calls + collision-free vs `WordPressPostSource`'s sourceIdFor when both have id=1

## Definition of Done

- [ ] `WordPressUserSource` implements `SourcePluginInterface` cleanly
- [ ] Conformance test passes
- [ ] Unit tests cover record extraction + edge cases
- [ ] `WordPressUserSource` listed on `public-surface-map.md` (flip `present: true`)
- [ ] No imports from WP04..WP07 (independent)

## Reviewer guidance

- No eager record buffering — `records()` yields, doesn't return an array
- `sourceIdFor()` accepts the SAME shape it expects to find in record data
- The conformance test extends M-002's base class without modification

## Activity Log

- 2026-05-14T20:55:33Z – claude – shell_pid=137727 – Started implementation via action command
- 2026-05-14T21:00:32Z – claude – shell_pid=137727 – Out-of-tree WP: deliverables at standalone-repo commit f1878f7. Files: src/Source/WordPressUserSource.php (impl), tests/Unit/Source/WordPressUserSourceTest.php (10 Pest unit tests), tests/Conformance/UserSourceConformanceTest.php (extends M-002 SourceConformanceTestCase, all 8 C1-C8 gates green). composer.json also registers Waaseyaa\Migration\Testing\ under autoload-dev so substrate's conformance base is reachable. vendor/bin/pest: 31 passed (5085 assertions). phpstan --level=5 on src+tests: clean.
