---
work_package_id: WP02
title: CLI regex validation + escaping audit
dependencies: []
requirement_refs:
- C-004
- FR-005
- FR-006
- FR-007
- FR-011
- NFR-002
planning_base_branch: main
merge_target_branch: main
branch_strategy: main → main (no worktree; lane allocated by finalize-tasks lanes.json)
subtasks:
- T003
- T004
- T005
- T006
- T007
history:
- date: '2026-05-20T23:57:09Z'
  author: tasks-materializer
  note: Initial WP file generated
authoritative_surface: packages/entity/src/
execution_mode: code_change
mission_slug: m006-translation-hardening-01KS3RY9
owned_files:
- packages/entity/src/LangcodeValidator.php
- packages/entity/tests/Unit/LangcodeValidatorTest.php
- packages/cli/src/Handler/AddTranslationsMigrationGenerator.php
- packages/cli/tests/Unit/AddTranslationsMigrationGeneratorTest.php
tags: []
---

# WP02 — CLI regex validation + escaping audit

**Mission**: m006-translation-hardening-01KS3RY9
**Closes**: #1446 (MEDIUM)
**Priority**: implement second (after WP03; no M-B dependency)

## Objective

Harden `AddTranslationsMigrationGenerator` against code injection via
operator-supplied langcode arguments. Deliver:

1. A canonical BCP-47-tolerant regex constant (`LangcodeValidator::BCP47_PATTERN`)
   in `packages/entity/src/` (L1 Core Data — the right home for entity-level language concepts).
2. Validation of `$defaultLangcode` and every entry in `$addTranslations` before
   any file is written.
3. An audit of the `$backend` interpolation site with defense-in-depth assertion.
4. Unit tests for both the validator class and the generator's new guard.

## Context

- **Generator file**: `packages/cli/src/Handler/AddTranslationsMigrationGenerator.php` (546 lines)
- **Injection sites identified in spec**:
  - Line 75: docblock — `{$defaultLangcode}` (unescaped)
  - Line 79: provenance comment — `{$defaultLangcode}` (unescaped)
  - Line 88: `private const BACKEND = '{$backend}'` (plan notes this is PHP-typed)
  - Line 191 (in `renderTwoAxisFromRevisionable`): `{$defaultLangcode}` in docblock/audit
- **`phpStringLiteral()` already exists** at line 506 and correctly escapes single-quoted string values.
- **`$defaultLc`** (line 62) is already passed through `phpStringLiteral()` for the constant body.
- **But the docblock / comment lines** (75, 79, 193, 358-368) interpolate the raw `$defaultLangcode` and `$backend` strings directly — the regex validation is the primary guard; escaped PHP constants in the class body are a secondary line.
- **Layer discipline**: `packages/cli` already depends on `waaseyaa/entity` (`cli/composer.json` line 70). Adding `use Waaseyaa\Entity\LangcodeValidator` requires no new dependency.

### Regex decision (from plan.md)

```
/^[a-zA-Z]{2,8}(-[a-zA-Z]{4})?(-[a-zA-Z]{2}|\d{3})?$/
```

Accepts: `en`, `en-US`, `zh-Hant`, `zh-Hant-TW`, `en-CA`, `fr-CA`, `mas`
Rejects: injection payloads (`;`, `'`, `/`), empty string, control characters,
leading/trailing whitespace, variant subtags (e.g. `en-US-x-twain`)

The constant is: `LangcodeValidator::BCP47_PATTERN` (a `public const string`).

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- Implement from the workspace `spec-kitty agent action implement WP02 --agent <name>` allocates.

## Implementation Command

```bash
spec-kitty agent action implement WP02 --agent claude:sonnet
```

---

## Subtask T003 — Create `LangcodeValidator`

**Purpose**: Provide a single canonical BCP-47-tolerant regex and a `validate()` method
so that any future code needing langcode validation imports the constant rather than
re-deriving the pattern (NFR-002).

**File**: `packages/entity/src/LangcodeValidator.php` *(new file)*

**Namespace**: `Waaseyaa\Entity`

**Steps**:

1. Create the file:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

/**
 * Validates langcode strings against a BCP-47-tolerant pattern.
 *
 * Accepts: language subtag (2-8 alpha), optionally followed by:
 *   - script subtag (4 alpha), e.g. `Hant` in `zh-Hant`
 *   - region subtag (2 alpha or 3 digits), e.g. `US` in `en-US`
 *
 * Examples of valid langcodes: en, en-US, zh-Hant, zh-Hant-TW, fr-CA, en-CA, mas
 * Out of scope (rejected): variant subtags, private-use extensions, grandfathered tags.
 *
 * @see https://www.rfc-editor.org/rfc/rfc5646 BCP 47
 */
final class LangcodeValidator
{
    /**
     * BCP-47-tolerant regex pattern for langcode validation.
     *
     * Covers language + optional script + optional region subtags.
     * Variant subtags and private-use extensions are out of scope for v1.
     */
    public const string BCP47_PATTERN = '/^[a-zA-Z]{2,8}(-[a-zA-Z]{4})?(-[a-zA-Z]{2}|\d{3})?$/';

    /**
     * Validate a langcode string.
     *
     * @throws \InvalidArgumentException When the langcode does not match BCP47_PATTERN.
     */
    public static function validate(string $langcode): void
    {
        if ($langcode === '' || !preg_match(self::BCP47_PATTERN, $langcode)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid langcode "%s". Expected a BCP-47 language subtag (e.g. "en", "en-US", "zh-Hant").',
                    $langcode,
                ),
            );
        }
    }
}
```

2. Confirm that `packages/entity/composer.json` has the `Waaseyaa\Entity\` autoload prefix
   pointing to `src/` — it should, since `TranslatableInterface` already lives there.

3. Run `bin/waaseyaa optimize:manifest` (or rely on the pre-push hook) to update the
   attribute-discovery manifest after adding the new class.

**Validation**:
- [ ] `composer dump-autoload` resolves `Waaseyaa\Entity\LangcodeValidator` without errors.
- [ ] `composer phpstan` passes on the new file (level 5).
- [ ] `composer cs-check` passes.

---

## Subtask T004 — Unit test `LangcodeValidatorTest`

**Purpose**: Verify the validator accepts valid BCP-47 langcodes and rejects invalid inputs
(injection payloads, empty string, control characters, whitespace).

**File**: `packages/entity/tests/Unit/LangcodeValidatorTest.php` *(new file)*

**Namespace**: `Waaseyaa\Entity\Tests\Unit`

**Steps**:

1. Create the test file with two data-provider–backed tests:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\LangcodeValidator;

#[CoversClass(LangcodeValidator::class)]
final class LangcodeValidatorTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function validLangcodes(): array
    {
        return [
            'simple language'        => ['en'],
            'language + region'      => ['en-US'],
            'language + region CA'   => ['fr-CA'],
            'language + script'      => ['zh-Hant'],
            'language + script + region' => ['zh-Hant-TW'],
            'three-letter language'  => ['mas'],
            'lowercase region'       => ['en-us'],  // regex is case-insensitive
        ];
    }

    /** @return array<string, array{string}> */
    public static function invalidLangcodes(): array
    {
        return [
            'empty string'              => [''],
            'injection semicolon'       => ["en-US'); DROP TABLE users;--"],
            'injection single quote'    => ["en'"],
            'injection newline'         => ["en\n"],
            'injection null byte'       => ["en\x00"],
            'leading whitespace'        => [' en'],
            'trailing whitespace'       => ['en '],
            'too short'                 => ['e'],
            'too long language subtag'  => ['toolonglang'],
            'digit language'            => ['12'],
            'variant subtag'            => ['en-US-x-twain'],
            'slash'                     => ['en/US'],
        ];
    }

    #[Test]
    #[DataProvider('validLangcodes')]
    public function validLangcodeDoesNotThrow(string $langcode): void
    {
        $this->expectNotToPerformAssertions();
        LangcodeValidator::validate($langcode);
    }

    #[Test]
    #[DataProvider('invalidLangcodes')]
    public function invalidLangcodeThrowsInvalidArgumentException(string $langcode): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LangcodeValidator::validate($langcode);
    }
}
```

2. Ensure `en-us` (lowercase region) is handled — if the regex uses `[a-zA-Z]` it accepts
   both cases. If you decide to normalise to uppercase, update the validator and document
   the normalisation behavior.

**Validation**:
- [ ] All `validLangcodes` cases pass without exception.
- [ ] All `invalidLangcodes` cases throw `\InvalidArgumentException`.
- [ ] `composer phpstan` passes on test file.

---

## Subtask T005 — Wire validation into `AddTranslationsMigrationGenerator`

**Purpose**: Call `LangcodeValidator::validate()` before any rendering logic runs so that
an injection payload in `--default-langcode` or `--add-translations` is rejected before
any file is written (FR-005, FR-006).

**File**: `packages/cli/src/Handler/AddTranslationsMigrationGenerator.php`

**Steps**:

1. Add `use Waaseyaa\Entity\LangcodeValidator;` to the import block at the top.

2. In `render()` (line 54), add at the very top of the method body (before line 60):
```php
LangcodeValidator::validate($defaultLangcode);
```

3. In `renderTwoAxisFromRevisionable()` (line 332), add the same call at the very top
   of the method body (before line 337):
```php
LangcodeValidator::validate($defaultLangcode);
```

4. In `generate()` (line 288), add validation of `$defaultLangcode` **before** the
   `isTranslatable()` guard (or immediately after — before any render call):
```php
LangcodeValidator::validate($defaultLangcode);
```

5. The `$addTranslations` parameter currently does not appear in the public API of
   `AddTranslationsMigrationGenerator` — it is a CLI argument processed by `MakeMigrationHandler`.
   Check `packages/cli/src/Handler/MakeMigrationHandler.php` to locate where `--add-translations`
   is split into individual langcodes. If that split happens before calling the generator,
   add the per-entry validation loop there. If the generator's `generate()` method will
   receive the list in a future PR, document this gap in a `// TODO(M-C WP02):` comment
   and validate in `generate()` if it already receives an array.

6. The `$backend` value in the heredoc at line 88 (`private const BACKEND = '{$backend}';`)
   is **not** passed through `phpStringLiteral()` — handle this in T006.

**Important**: The docblock and comment interpolation sites (lines 75, 79, 193, 358–368)
use raw `$defaultLangcode` — these land inside `/** ... */` PHP comments inside the
generated migration source, not inside executable string literals. After regex validation
the value is safe for comment interpolation. No `phpStringLiteral()` wrapping is needed
for comment-only sites, but you should confirm this by looking at the actual generated
output — the comment must not be injectable as PHP code.

**Validation**:
- [ ] `./vendor/bin/phpunit packages/cli/tests/` passes after the edits.
- [ ] `composer phpstan` clean on the modified generator file.

---

## Subtask T006 — Audit `$backend` interpolation site

**Purpose**: Close FR-007's defense-in-depth requirement for the `$backend` variable,
which is interpolated as a bare string at line 88: `private const BACKEND = '{$backend}';`.

**File**: `packages/cli/src/Handler/AddTranslationsMigrationGenerator.php`

**Steps**:

1. Locate line 88 (in `render()`):
   ```php
   private const BACKEND = '{$backend}';
   ```
   And the corresponding line in `renderTwoAxisFromRevisionable()` if it also uses `$backend`.

2. The `$backend` parameter has PHP type `'sql-blob'|'sql-column'` (union string literal type).
   PHP's type system **statically narrows** this — only those two string values are valid
   at the call site. However, FR-007 requires defense in depth.

3. Add a runtime assertion **before the heredoc** in `render()`:
   ```php
   \assert(
       in_array($backend, ['sql-blob', 'sql-column'], true),
       "backend must be 'sql-blob' or 'sql-column', got: {$backend}",
   );
   ```

4. Add a PHPDoc comment above the assert explaining the defense-in-depth rationale:
   ```php
   // Defense in depth per FR-007: the PHP type system narrows $backend to
   // 'sql-blob'|'sql-column', making injection practically impossible. The
   // assert documents this intent and will fire in development/test environments
   // if the type contract is ever widened.
   ```

5. If `renderTwoAxisFromRevisionable()` also interpolates `$backend` as a bare string in
   a heredoc, apply the same assert there.

6. Optionally, wrap `$backend` in `phpStringLiteral()` at the interpolation site to fully
   align it with all other operator-provided values:
   ```php
   $backendLiteral = $this->phpStringLiteral($backend);
   // then use {$backendLiteral} in the heredoc
   ```
   This is the cleanest option; the assert can remain as documentation.

**Validation**:
- [ ] The modified file passes `composer phpstan` (assert does not introduce a new issue).
- [ ] `./vendor/bin/phpunit packages/cli/tests/` passes.

---

## Subtask T007 — Unit test `AddTranslationsMigrationGeneratorTest`

**Purpose**: Assert that the generator rejects injection payloads before writing any file,
and that it accepts valid BCP-47 langcodes (FR-011).

**File**: `packages/cli/tests/Unit/AddTranslationsMigrationGeneratorTest.php` *(new file or add to existing)*

**Namespace**: `Waaseyaa\CLI\Tests\Unit`

**Steps**:

1. If a test file already exists for the generator, add new test methods to it.
   If not, create it following the same namespace pattern as other CLI unit tests.

2. Create a minimal `EntityTypeInterface` stub (or use an existing fixture if available
   in `packages/cli/tests/`). The stub needs:
   - `id(): string` → returns `'article'`
   - `isTranslatable(): bool` → returns `false` (so `generate()` proceeds to render)
   - `isRevisionable(): bool` → returns `false` (so the simple path is taken in `generate()`)

3. Add test `renderRejectsInjectionPayloadInDefaultLangcode`:
```php
#[Test]
public function renderRejectsInjectionPayloadInDefaultLangcode(): void
{
    $generator = new AddTranslationsMigrationGenerator();
    $entityType = $this->makeEntityTypeStub('article');

    $this->expectException(\InvalidArgumentException::class);
    $generator->render($entityType, "en-US'); DROP TABLE users;--", 'sql-blob', []);
}
```

4. Add test `generateRejectsInjectionPayloadInDefaultLangcode`:
```php
#[Test]
public function generateRejectsInjectionPayloadInDefaultLangcode(): void
{
    $generator = new AddTranslationsMigrationGenerator();
    $entityType = $this->makeEntityTypeStub('article');

    $this->expectException(\InvalidArgumentException::class);
    // No file should be written — InvalidArgumentException must be thrown before render.
    $generator->generate($entityType, "en\x00null-byte", 'sql-blob', []);
}
```

5. Add test `renderAcceptsValidBcp47Langcodes` with data provider:
```php
/** @return array<string, array{string}> */
public static function validLangcodes(): array
{
    return [
        'simple'           => ['en'],
        'language+region'  => ['en-US'],
        'script subtag'    => ['zh-Hant'],
        'script+region'    => ['zh-Hant-TW'],
        'fr-CA'            => ['fr-CA'],
    ];
}

#[Test]
#[DataProvider('validLangcodes')]
public function renderAcceptsValidBcp47Langcodes(string $langcode): void
{
    $generator = new AddTranslationsMigrationGenerator();
    $entityType = $this->makeEntityTypeStub('article');

    // Should not throw — just assert the returned string is non-empty PHP.
    $result = $generator->render($entityType, $langcode, 'sql-blob', []);
    $this->assertStringContainsString('<?php', $result);
    $this->assertStringContainsString($langcode, $result);
}
```

6. Add a helper `makeEntityTypeStub(string $id): EntityTypeInterface` at the bottom
   of the test class using an anonymous class:

```php
private function makeEntityTypeStub(string $id): EntityTypeInterface
{
    return new class($id) implements EntityTypeInterface {
        public function __construct(private string $entityId) {}
        public function id(): string { return $this->entityId; }
        public function isTranslatable(): bool { return false; }
        public function isRevisionable(): bool { return false; }
        // Add other EntityTypeInterface methods as no-op stubs as needed.
        // Run phpstan to identify which methods are required.
    };
}
```

**Note on `EntityTypeInterface` stubs**: The anonymous class must implement all methods
of the interface. Run `composer phpstan` after writing the stub — it will list missing
methods. Add them as no-ops returning sensible defaults.

**Validation**:
- [ ] Injection payload tests throw `\InvalidArgumentException`.
- [ ] Valid langcode tests pass without exception.
- [ ] `composer phpstan` clean on the test file.
- [ ] `./vendor/bin/phpunit packages/cli/tests/Unit/AddTranslationsMigrationGeneratorTest.php` passes.

---

## Definition of Done

- [ ] `packages/entity/src/LangcodeValidator.php` created with `BCP47_PATTERN` const and `validate()` method.
- [ ] `LangcodeValidatorTest` passes all acceptance and rejection cases.
- [ ] `AddTranslationsMigrationGenerator::render()` and `renderTwoAxisFromRevisionable()` call `LangcodeValidator::validate($defaultLangcode)` before any rendering.
- [ ] `generate()` also validates `$defaultLangcode` before delegating.
- [ ] `$backend` interpolation site has an assertion + comment documenting the defense-in-depth rationale.
- [ ] `AddTranslationsMigrationGeneratorTest` covers injection rejection + acceptance.
- [ ] `composer verify` sub-checks pass: `cs-check`, `phpstan`, `phpunit` on affected packages.
- [ ] Commit message includes `Closes #1446` or `Refs #1446`.

## Risks

| Risk | Mitigation |
|------|------------|
| `MakeMigrationHandler.php` owns `$addTranslations` splitting and may need patching too | Check the handler before finishing T005; add validation there if it processes the array before passing to generator |
| `renderTwoAxisFromRevisionable()` has its own `$defaultLangcode` interpolation sites | T005 explicitly covers this path — do not assume `render()` validation is sufficient |
| Anonymous class stub for `EntityTypeInterface` missing methods | Run `composer phpstan` after writing stubs; fix missing no-ops iteratively |
| Regex rejects valid but unusual BCP-47 langcodes (e.g. `sgn-BE-FR`) | Document the out-of-scope boundary in the constant PHPDoc; do not over-extend the regex in this WP |

## Reviewer Guidance

- Confirm `LangcodeValidator::validate()` is called in **all three** methods: `render()`, `renderTwoAxisFromRevisionable()`, `generate()`.
- Confirm the `$backend` assertion fires before the heredoc (check method order in the file diff).
- Confirm no partial file output is possible if validation throws (the throw must happen before any `file_put_contents` or equivalent — the generator only renders a string, so there is no file write in this class; that happens in the calling handler).
- Confirm `LangcodeValidatorTest` covers `zh-Hant` (script subtag with 4 letters) explicitly.
