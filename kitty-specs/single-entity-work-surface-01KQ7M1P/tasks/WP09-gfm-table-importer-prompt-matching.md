---
work_package_id: WP09
title: 'F5b: GfmTableImporter + prompt matching + contract test'
dependencies:
- WP01
- WP02
- WP08
requirement_refs:
- FR-013
- FR-016
- FR-020
- NFR-005
- NFR-007
- NFR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T040
- T041
- T042
- T043
agent: "claude:sonnet-4-6:implementer:implementer"
shell_pid: "23804"
history:
- date: '2026-04-27'
  note: Generated from plan.md + research.md Q8 + contracts/ F5.
authoritative_surface: packages/structured-import/src/Gfm/
execution_mode: code_change
mission_id: 01KQ7M1PHWD8QAQPJC91RAVE0T
mission_slug: single-entity-work-surface-01KQ7M1P
owned_files:
- packages/structured-import/src/Gfm/GfmTableImporter.php
- packages/structured-import/src/Gfm/PromptNormalizer.php
- packages/structured-import/tests/Unit/Gfm/GfmTableImporterTest.php
- packages/structured-import/tests/Contract/StructuredImporterContractTest.php
tags: []
---

# WP09 — F5b: GfmTableImporter + prompt matching + contract test

## Objective

Implement the default `GfmTableImporter` that wires `GfmTableParser` (from WP08) + `PromptNormalizer` (new in this WP) + `FieldDefinitionRegistry` (enriched in WP01, populated in WP02) to produce `ImportResult` instances. Add the abstract `StructuredImporterContractTest` so future importer implementations have a single base to extend.

## Context (read first)

- **spec.md** FR-013, FR-020, C-012.
- **research.md** Q8 — prompt normalization algorithm.
- **data-model.md § 5/§ 6** — value objects.
- **contracts/README.md** F5 — acceptance criteria.
- **`packages/field/src/FieldDefinition.php`** post-WP01 — `getPromptAliases()` returns the declared aliases.
- **`packages/field/src/FieldDefinitionRegistry.php`** — `bundleFieldsFor(entityTypeId, bundle)` returns `array<string, FieldDefinitionInterface>`.

## Branch Strategy

- **Planning base**: `main` (after WP01 + WP02 + WP08 land)
- **Final merge target**: `main`
- Lane via `finalize-tasks`. Use `spec-kitty agent action implement WP09 --agent <name> --mission single-entity-work-surface-01KQ7M1P`.

## Subtasks

### T040 — `PromptNormalizer`

**File**: `packages/structured-import/src/Gfm/PromptNormalizer.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\StructuredImport\Gfm;

final class PromptNormalizer
{
    public function normalize(string $prompt): string
    {
        $lowered = mb_strtolower($prompt, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $lowered);
        return trim($collapsed ?? $lowered);
    }
}
```

**Notes**:
- `mb_strtolower` preserves diacritics (research.md Q8). Do **not** transliterate (`Café` stays `café`, never `cafe`).
- `preg_replace` returning null on error → fall back to the lowered string (extremely defensive; should not happen with a static pattern).
- Stateless and side-effect-free — safe to share as a singleton.
- This duplicates the inline normalization in WP02's `BundleTemplateCompiler`. The duplication is acceptable per DIR-003 ("no premature abstraction"); WP10 may consolidate if it surfaces as drift.

### T041 — `GfmTableImporter::import()`

**File**: `packages/structured-import/src/Gfm/GfmTableImporter.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\StructuredImport\Gfm;

use Waaseyaa\Field\FieldDefinitionRegistryInterface;
use Waaseyaa\StructuredImport\ImportResult;
use Waaseyaa\StructuredImport\StructuredImporterInterface;
use Waaseyaa\StructuredImport\UnmatchedRow;

final class GfmTableImporter implements StructuredImporterInterface
{
    public function __construct(
        private readonly FieldDefinitionRegistryInterface $registry,
        private readonly GfmTableParser $parser,
        private readonly PromptNormalizer $normalizer,
    ) {}

    public function import(string $payload, string $entityTypeId, ?string $bundle = null): ImportResult
    {
        $effectiveBundle = $bundle ?? $entityTypeId;
        $fields = $this->registry->bundleFieldsFor($entityTypeId, $effectiveBundle);

        // Build alias index: normalized-alias -> field-name.
        $aliasIndex = [];
        foreach ($fields as $name => $field) {
            // Field's own name is implicitly an alias.
            $aliasIndex[$this->normalizer->normalize($name)] = $name;
            foreach ($field->getPromptAliases() as $alias) {
                $aliasIndex[$this->normalizer->normalize($alias)] = $name;
            }
        }

        $parseResult = $this->parser->parse($payload);

        $matched = [];
        $unmatched = [];
        foreach ($parseResult->rows as $row) {
            $key = $this->normalizer->normalize($row['prompt']);
            if (isset($aliasIndex[$key])) {
                $fieldName = $aliasIndex[$key];
                $matched[$fieldName] = $row['value'];
            } else {
                $unmatched[] = new UnmatchedRow($row['prompt'], $row['value']);
            }
        }

        return new ImportResult(
            matched: $matched,
            unmatched: $unmatched,
            errors: $parseResult->errors,
        );
    }
}
```

**Behavioral details**:
- The field's own `name` is an implicit alias — if a field is named `title` and has no declared aliases, the prompt `Title` (case-folded to `title`) still matches.
- `bundle` defaults to `entityTypeId` when null (FR-013, spec assumption: "for entity types without bundles, treat the entity_type id as the implicit single bundle").
- If two aliases (across different fields in the same bundle) normalize to the same key, the alias index entry is overwritten — last-registered wins. WP02's `BundleTemplateCompiler` validates uniqueness at registration time and throws, so this collision should not be reachable in practice. Defensive behavior: do not throw at import time; the registration-time check is the source of truth.

### T042 — `GfmTableImporter` unit tests

**File**: `packages/structured-import/tests/Unit/Gfm/GfmTableImporterTest.php`

**Cases**:

1. **Happy path with declared aliases**: `(node, profile)` bundle has `name` (aliases `['name', 'display name']`) and `bio` (aliases `['bio', 'biography']`). Input `| Display Name | Aanikoobijigan |\n| --- | --- |\n| Biography | Storyteller. |` → `matched = ['name' => 'Aanikoobijigan', 'bio' => 'Storyteller.']`, `unmatched = []`, `errors = []`.

2. **Implicit alias from field name**: field `title` with no declared aliases. Input row prompt `Title` → matched as `title`.

3. **Unknown prompt**: row with prompt not matching any alias → lands in `unmatched` (preserving original prompt and value, not the normalized form).

4. **Mixed matched + unmatched**: 5-row table with 4 matching, 1 unknown → matched has 4 entries, unmatched has 1.

5. **Empty document**: `""` → matched empty, unmatched empty, errors `["No table found"]`.

6. **Bundle defaults to entity_type when null**: `import($payload, 'config_entity', null)` looks up bundle `config_entity`.

7. **Diacritic preservation**: alias `'café'` matches prompt `Café` (case-folded, diacritic preserved). Alias `'cafe'` does **not** match `Café` (no transliteration — C-012).

8. **Whitespace tolerance**: alias `'display name'` matches prompts `'Display Name'`, `'  Display Name  '`, `'Display\tName'`, `'Display Name'` (Unicode whitespace).

9. **Errors propagate from parser**: malformed table input → `ImportResult.errors` includes the parser's errors.

Use stubs for `FieldDefinitionRegistryInterface` (anonymous class returning configured fields). Use a real `GfmTableParser` and `PromptNormalizer`.

### T043 — Contract test for `StructuredImporterInterface`

**File**: `packages/structured-import/tests/Contract/StructuredImporterContractTest.php`

**Pattern**: abstract base class with `#[CoversNothing]`. Subclass instantiates the importer-under-test and runs the contract. Reference: existing contract tests in `packages/*/tests/Contract/`.

**Cases (must pass for any implementation)**:
- `import('', 'unknown_type', null)` returns an `ImportResult` (does not throw).
- `import` with a parseable payload but no matchable prompts returns `matched=[]` and non-empty `unmatched`.
- `import` is stateless: two calls in succession with different payloads do not share state.
- `import` returns `ImportResult` (type assertion).

Concrete subclass `GfmTableImporterContractTest` extends the abstract base and provides a `GfmTableImporter` instance with a stub registry.

## Definition of Done

- [ ] `PromptNormalizer::normalize()` implemented per research.md Q8 (mb_strtolower + Unicode-whitespace collapse + trim, no transliteration).
- [ ] `GfmTableImporter::import()` produces correct `matched` / `unmatched` / `errors` decompositions.
- [ ] Field name acts as implicit alias.
- [ ] Bundle defaults to entity_type when null.
- [ ] Diacritics preserved (alias `'café'` matches `'Café'`, not `'Cafe'`).
- [ ] Errors from `GfmTableParser` propagate to `ImportResult.errors`.
- [ ] Unit test covers ≥ 9 cases above.
- [ ] Contract test base class with `#[CoversNothing]`; concrete subclass for `GfmTableImporter` extends it and passes.
- [ ] `composer phpstan`, `composer cs-check`, PHPUnit pass.
- [ ] No code changes outside `owned_files`.

## Risks

| Risk | Mitigation |
|---|---|
| Performance of `bundleFieldsFor` lookup on every import | Acceptable — registry returns from in-memory cache. Importer doesn't pre-cache the alias index across calls because `bundle` and `entityTypeId` vary per call. If profiling shows hot path, add per-call cache; do not pre-optimize. |
| Alias-index collision when two fields share a normalized alias | WP02's compiler should have thrown at boot. Importer's behavior under collision is documented as "last-registered wins"; defensive only. |
| `GfmTableParser` returns rows with multibyte characters that confuse `mb_strtolower` if locale isn't set | The parser passes raw UTF-8; `mb_strtolower($s, 'UTF-8')` is locale-independent. No `setlocale` calls needed. |
| Contract test base too strict for hypothetical future importers (e.g., a CSV importer) | Keep contract assertions to the spec's universal guarantees: `import` returns `ImportResult`, doesn't throw on empty input, is stateless. Don't add format-specific assertions to the base. |

## Reviewer guidance

- Verify the alias index includes the field name as an implicit alias.
- Verify diacritic preservation: write an explicit test asserting `'Café'` matches alias `'café'` and does **not** match alias `'cafe'`.
- Verify normalization is symmetric — same algorithm on both declared aliases (cached at index construction) and inbound prompts.
- Verify the contract test is `#[CoversNothing]` (it tests the contract, not a specific class).
- Verify no transliteration library or `iconv` calls.
- No CHANGELOG edit (WP10).

## Implementation command

```bash
spec-kitty agent action implement WP09 --agent <agent-name> --mission single-entity-work-surface-01KQ7M1P
```

Depends on WP01 + WP02 + WP08.

## Activity Log

- 2026-04-27T17:46:25Z – claude:sonnet-4-6:implementer:implementer – shell_pid=23804 – Started implementation via action command
