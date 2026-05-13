---
work_package_id: WP03
title: Essential process plugins
dependencies:
- WP01
requirement_refs:
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T014
- T015
- T016
- T017
- T018
- T019
- T020
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/src/Plugin/Process/
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/src/Plugin/Process/PassThroughProcessor.php
- packages/migration/src/Plugin/Process/HtmlSanitizeProcessor.php
- packages/migration/src/Plugin/Process/LookupProcessor.php
- packages/migration/src/Plugin/Process/ConcatProcessor.php
- packages/migration/src/Plugin/Process/TypeCoerceProcessor.php
- packages/migration/src/Plugin/Process/DefaultValueProcessor.php
- packages/migration/src/Exception/ProcessException.php
- packages/migration/tests/Unit/Plugin/Process/**
priority: p1
tags:
- stable-surface
- layer-3
- process-plugins
---

# WP03 — Essential process plugins

## Objective

Ship the six framework-reserved process plugins so non-trivial migrations are expressible out of the box: `PassThroughProcessor`, `HtmlSanitizeProcessor`, `LookupProcessor`, `ConcatProcessor`, `TypeCoerceProcessor`, `DefaultValueProcessor`. Each has a reserved id and stable status. The bundle is the minimum set sufficient for the WP11 end-to-end CSV→entity validation and for the WordPress reader (post-mission).

This WP can run in parallel with WP02 and WP04 — all three depend only on WP01.

## Dependencies

- Internal: WP01 (plugin interfaces, `ProcessContext`, `PluginRegistry`, `ReservedPluginIds`).
- External: None. May optionally depend on `ezyang/htmlpurifier` if already in the project's vendor tree; otherwise falls back to a DOMDocument-based allowlist (see T015).
- Charter anchors: §5.8 (proposed) — process plugin classes.

## Scope (in / out)

**In scope**
- Six `final readonly class` process-plugin implementations under `packages/migration/src/Plugin/Process/` (FR-010 chaining is delivered by the runner; this WP delivers the plugin set).
- `ProcessException` typed exception covering per-record process-stage failures (FR-045).
- Per-plugin unit tests covering the contracts and edge cases listed below.

**Out of scope**
- Chain composition logic (lives in `MigrationRunner::runProcessChain()` — WP06).
- Source-specific plugins (`WordPressShortcodeStrip` etc.) — separate mission.
- Plugin-collision detection (already in WP01's `PluginRegistry`).

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Per-lane worktree. Run `spec-kitty agent action implement WP03 --agent opus`.

## Implementation guidance

### Subtask T014 — `PassThroughProcessor`

**Purpose**: The trivial processor. Reads a named source field and returns it untouched. Underpins the string-shorthand syntax in the manifest (`'title' => 'post_title'` resolves to `PassThrough('post_title')`).

**FRs covered**: FR-010 (set member), §5.4 reserved namespace.

**Files**:
- `packages/migration/src/Plugin/Process/PassThroughProcessor.php` (new, ~50 lines).
- `packages/migration/tests/Unit/Plugin/Process/PassThroughProcessorTest.php` (new).

**Steps**:
1. `final readonly class PassThroughProcessor implements ProcessPluginInterface` (`@api`).
2. Constructor: `__construct(public string $sourceField)`. Validate non-empty.
3. `id(): string` → `ReservedPluginIds::PASS_THROUGH` (`'pass_through'`).
4. `stability(): string` → `'stable'`.
5. `transform(mixed $value, ProcessContext $context): mixed` returns `$context->sourceRecord->field($this->sourceField, null)`. The `$value` argument is ignored — `PassThrough` is always the head of a chain.

**Validation**:
- [ ] Reading an existing source field returns the value.
- [ ] Reading a missing source field returns null (not raises).
- [ ] `id()` returns the reserved constant.

**Edge cases**:
- An empty `$sourceField` constructor argument raises `\InvalidArgumentException` at construction time.

### Subtask T015 — `HtmlSanitizeProcessor`

**Purpose**: Sanitize HTML strings using a tag/attribute allowlist. Needed for any rich-text body migrated from WordPress / Drupal source data.

**FRs covered**: FR-010 (set member), §5.4 reserved namespace.

**Files**:
- `packages/migration/src/Plugin/Process/HtmlSanitizeProcessor.php` (new, ~180 lines).
- `packages/migration/tests/Unit/Plugin/Process/HtmlSanitizeProcessorTest.php` (new, ~120 lines).

**Steps**:
1. `final readonly class HtmlSanitizeProcessor implements ProcessPluginInterface` (`@api`).
2. Constructor:
   ```php
   public function __construct(
       public string $sourceField,
       /** @var list<string> */
       public array $allowedTags = self::DEFAULT_ALLOWED_TAGS,
       /** @var array<string, list<string>> tag => list of allowed attributes */
       public array $allowedAttributes = self::DEFAULT_ALLOWED_ATTRIBUTES,
   ) {}
   ```
3. `DEFAULT_ALLOWED_TAGS` constant: `['p', 'a', 'br', 'em', 'strong', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre', 'img']`. `DEFAULT_ALLOWED_ATTRIBUTES`: `['a' => ['href', 'title'], 'img' => ['src', 'alt', 'title']]`.
4. `transform()` implementation:
   - If `ezyang/htmlpurifier` is in vendor (`class_exists('HTMLPurifier')`), use it with a HTMLPurifier_Config built from the allowlists. Cache the purifier in a static property within the method scope using `static $purifier; if ($purifier === null) { ... }`.
   - Otherwise, fall back to a DOMDocument-based implementation: parse with `LIBXML_NOERROR | LIBXML_NOWARNING`, walk the tree, strip tags not in the allowlist (replace with text content), strip attributes not in the allowlist for the tag. Re-serialize.
   - For both paths, pass-through null and pass-through empty strings unchanged.
5. `id()` → `ReservedPluginIds::HTML_SANITIZE` (`'html_sanitize'`).
6. `stability()` → `'stable'`.

**Validation**:
- [ ] Allowed tags survive; disallowed tags are stripped (text content preserved).
- [ ] Allowed attributes survive; disallowed attributes are stripped.
- [ ] `<script>alert('xss')</script>` → empty or text-only output (no script tag).
- [ ] Malformed input does not raise; returns best-effort sanitized output.
- [ ] Null input returns null.
- [ ] Test runs against both HTMLPurifier-available and HTMLPurifier-absent code paths (mock `class_exists` via a wrapper service or run two test classes — preferred: factor the strategy selection into a protected method that can be overridden in a test subclass).

**Edge cases**:
- Self-closing tags (`<br/>`) must serialize identically.
- HTML entities (`&amp;`, `&lt;`) must round-trip without double-encoding.

### Subtask T016 — `LookupProcessor`

**Purpose**: Resolve a source field through the migration id-map. Used to map cross-migration references (e.g. a post's author resolves to a previously-imported user's destination uuid).

**FRs covered**: FR-010 (set member), §5.4.

**Files**:
- `packages/migration/src/Plugin/Process/LookupProcessor.php` (new, ~110 lines).
- `packages/migration/tests/Unit/Plugin/Process/LookupProcessorTest.php` (new).

**Steps**:
1. `final readonly class LookupProcessor implements ProcessPluginInterface` (`@api`).
2. Constructor:
   ```php
   public function __construct(
       public string $sourceField,
       public string $migration,                           // target migration id
       public ?string $sourceType = null,                  // optional override; defaults to sourceField name
       public bool $allowMissing = false,                  // when true, missing lookups return null; otherwise raise
   ) {}
   ```
3. `transform()` reads the source value via `$context->sourceRecord->field($this->sourceField)`, constructs a `SourceId` (`new SourceId($this->sourceType ?? $this->sourceField, ['id' => $value])`), then calls `$context->lookup($this->migration, $sourceId)` (the lookup closure injected by the runner).
4. If the lookup returns null and `$allowMissing` is false, raise `ProcessException` with code `'LOOKUP_MISS'` and a message naming the source field + target migration + lookup key. If `$allowMissing` is true, return null.
5. If the lookup returns a `WriteResult`, return `$writeResult->destinationUuid`.
6. `id()` → `'lookup'`. `stability()` → `'stable'`.

**Validation**:
- [ ] Hit returns the destination uuid.
- [ ] Miss + `allowMissing=false` raises `ProcessException` with code `'LOOKUP_MISS'`.
- [ ] Miss + `allowMissing=true` returns null.
- [ ] The closure is invoked with the correct `migrationId` + `SourceId`.

**Edge cases**:
- A null source value must short-circuit to null (no lookup attempted; logged at debug).

### Subtask T017 — `ConcatProcessor`

**Purpose**: Concatenate multiple source fields and/or literal strings. Used for composite slugs, full-name derivations, etc.

**FRs covered**: FR-010 (set member), §5.4.

**Files**:
- `packages/migration/src/Plugin/Process/ConcatProcessor.php` (new, ~80 lines).
- `packages/migration/tests/Unit/Plugin/Process/ConcatProcessorTest.php` (new).

**Steps**:
1. `final readonly class ConcatProcessor implements ProcessPluginInterface` (`@api`).
2. Constructor: `__construct(public array $parts, public string $separator = '')`. Each part is either:
   - A string starting with `@` → interpreted as a source field reference (e.g. `'@post_slug'`).
   - Any other string → literal.
3. `transform()` walks `$parts`, resolves each, casts to string (null → `''`), joins by `$separator`.
4. `id()` → `'concat'`. `stability()` → `'stable'`.

**Validation**:
- [ ] Mixed literal + `@field` parts concatenate correctly.
- [ ] Null source fields produce empty string in the result, not the literal text `"null"`.
- [ ] Empty `$parts` returns empty string.
- [ ] `$separator` is honoured.

**Edge cases**:
- A `@field` reference to a non-existent source field is treated as null (empty string in result). Logged at debug.

### Subtask T018 — `TypeCoerceProcessor`

**Purpose**: Cast a value to a target PHP scalar type. Needed because source data is often string-typed even when the destination field is an int / bool / float.

**FRs covered**: FR-010 (set member), §5.4.

**Files**:
- `packages/migration/src/Plugin/Process/TypeCoerceProcessor.php` (new, ~110 lines).
- `packages/migration/tests/Unit/Plugin/Process/TypeCoerceProcessorTest.php` (new).

**Steps**:
1. `final readonly class TypeCoerceProcessor implements ProcessPluginInterface` (`@api`).
2. Constructor: `__construct(public string $targetType)`. Allowed values: `'string'`, `'int'`, `'float'`, `'bool'`, `'array'`. Anything else → `\InvalidArgumentException`.
3. `transform(mixed $value, ProcessContext $context): mixed`:
   - null passes through.
   - `'string'` → `(string) $value`.
   - `'int'` → `filter_var($value, FILTER_VALIDATE_INT)`; on false raise `ProcessException` with code `'TYPE_COERCE_FAIL'`.
   - `'float'` → `filter_var($value, FILTER_VALIDATE_FLOAT)`; same failure handling.
   - `'bool'` → `filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)`; null result raises `ProcessException`.
   - `'array'` → if already array, pass through; if scalar, wrap in single-element array.
4. `id()` → `'type_coerce'`. `stability()` → `'stable'`.

**Validation**:
- [ ] Round-trip cases for all five types.
- [ ] Invalid coercions raise `ProcessException` with the offending value in the message.
- [ ] null passes through unchanged.

**Edge cases**:
- The string `'false'` must coerce to bool `false` (not bool `true`). Confirmed by `FILTER_VALIDATE_BOOLEAN` semantics.
- The string `'on'` and `'off'` also map to bool — desirable for HTML form sources.

### Subtask T019 — `DefaultValueProcessor`

**Purpose**: Provide a fallback for null / empty source values. Cheapest of the six plugins; usually the last link in a chain.

**FRs covered**: FR-010 (set member), §5.4.

**Files**:
- `packages/migration/src/Plugin/Process/DefaultValueProcessor.php` (new, ~50 lines).
- `packages/migration/tests/Unit/Plugin/Process/DefaultValueProcessorTest.php` (new).

**Steps**:
1. `final readonly class DefaultValueProcessor implements ProcessPluginInterface` (`@api`).
2. Constructor: `__construct(public mixed $default, public bool $treatEmptyStringAsNull = true)`.
3. `transform(mixed $value, ProcessContext $context): mixed`:
   - null → `$default`.
   - `''` and `$treatEmptyStringAsNull === true` → `$default`.
   - Otherwise return `$value` unchanged.
4. `id()` → `'default_value'`. `stability()` → `'stable'`.

**Validation**:
- [ ] Null replaced by default.
- [ ] Empty string replaced when flag is on; preserved when flag is off.
- [ ] Non-null non-empty value passes through.

### Subtask T020 — `ProcessException` + parity-of-reserved-ids test

**Purpose**: Ship the typed exception for per-record process failures, plus assert the reserved-id list matches the shipped plugins.

**FRs covered**: FR-045 (continued — process exception type), §5.4 (consistency).

**Files**:
- `packages/migration/src/Exception/ProcessException.php` (new, ~70 lines).
- `packages/migration/tests/Unit/Plugin/Process/ReservedPluginIdsParityTest.php` (new).

**Steps**:
1. `ProcessException` extends `\RuntimeException`. `@api`. Public readonly `string $code`, `string $sourceField`, `string $migrationId`. Stable `const CODES` listing each shipped error code (`'LOOKUP_MISS'`, `'TYPE_COERCE_FAIL'`). Add new codes here as new processors land.
2. `ReservedPluginIdsParityTest`: a single test asserting `ReservedPluginIds::ALL` equals the set `[(new PassThroughProcessor('x'))->id(), (new HtmlSanitizeProcessor('x'))->id(), (new LookupProcessor('x', 'm'))->id(), (new ConcatProcessor([]))->id(), (new TypeCoerceProcessor('string'))->id(), (new DefaultValueProcessor(null))->id()]` (after sorting). Catches drift between the constant list and the shipped concrete set.

**Validation**:
- [ ] `ReservedPluginIdsParityTest` green.
- [ ] `ProcessException` round-trip test asserts all three readonly properties.

## Tests

- **Unit**: one test class per processor under `packages/migration/tests/Unit/Plugin/Process/`. Plus the parity test.
- **Integration**: none in this WP. Chain composition is exercised by WP06's integration tests; end-to-end by WP11.
- **Conformance**: WP10 — process-plugin conformance is light because each plugin is small; the conformance suite focuses on Source/Destination.

## Definition of Done

- [ ] All seven subtasks complete.
- [ ] FR-010 cited in code comments on the six processors as `@spec FR-010`.
- [ ] `composer phpstan` clean for `packages/migration/`.
- [ ] `composer cs-check` clean (run twice).
- [ ] `bin/check-package-layers` clean.
- [ ] `bin/audit-dead-code` clean.
- [ ] `./vendor/bin/phpunit` full-suite green.
- [ ] All six processors carry `@api` PHPDoc.
- [ ] `id()` of each processor returns the corresponding `ReservedPluginIds` constant — verified by the parity test.
- [ ] No `psr/log` imports.
- [ ] `HtmlSanitizeProcessor` does not strip safe URLs (`<a href="https://example.com">x</a>` round-trips with href intact).

## Risks

- **R1 — `HtmlSanitize` falls behind real-world WordPress HTML**: WordPress content includes shortcodes, Gutenberg blocks, oEmbed wrappers. This WP ships only generic HTML sanitization; WordPress-specific plugins land in the sibling mission. Document the gap in the processor's PHPDoc.
- **R2 — `TypeCoerce` ambiguity on numeric strings**: `"01"` parses as int 1, dropping the leading zero. This is expected; document in PHPDoc that `TypeCoerce('string')` is the right choice when leading zeros matter.
- **R3 — `Lookup` cycle**: a `LookupProcessor` against a migration that has not yet imported the referenced record will miss. The dependency-graph (WP02) prevents this at the migration level, but bad data is still possible. `allowMissing` is the escape hatch.
- **R4 — `HTMLPurifier` static cache leaks across PHPUnit isolated tests**: static-property caching inside a class instance is fine; static *inside a method* must be guarded with the test-runner's restart. Document and accept.

## Reviewer guidance

- Check: every processor is `final readonly class`.
- Check: `id()` is a single literal constant lookup, never a string literal repeated in source.
- Check: `HtmlSanitize` fallback (DOMDocument path) handles malformed input without raising.
- Check: `Lookup` calls the closure injected on `ProcessContext`, not a class-string registry.
- Check: `ReservedPluginIdsParityTest` is in the unit suite.
- Verify: the six processors collectively cover the manifest examples in spec §6.2 ("title", "body", "author_id", "slug" chain).
- Confirm: no upward layer imports — Layer 3 → Layer 0/1 only.
