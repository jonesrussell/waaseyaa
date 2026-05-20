# Research: M-006 Translation Hardening

**Date**: 2026-05-20 | **Mission**: `m006-translation-hardening-01KS3RY9`

## Decision: BCP-47 Regex Constant Location

**Decision**: `packages/entity/src/LangcodeValidator.php` (`Waaseyaa\Entity` namespace, L1 Core Data)

**Rationale**:
- `TranslatableInterface`, `TranslatableEntityTrait`, `TranslationEvent`, `EntityTranslationException` all live in `packages/entity/src/`. Langcodes are entity-level concepts.
- `packages/i18n/src/` is L0 Foundation; it owns UI-string translation (`Translator`, `Language`, `LanguageManager`). `Language::$id` is a free-form string — the i18n package has no regex validation today and langcodes are not its semantic focus.
- `packages/cli/composer.json` already lists `"waaseyaa/entity": "^0.1.0-alpha.186"` (line 70). Adding `use Waaseyaa\Entity\LangcodeValidator` requires zero new dependency.
- Placing the validator in entity keeps the constraint co-located with the interface it protects.

**Alternatives considered**:
- `packages/i18n/` — rejected: Foundation layer, wrong semantic home, would mix UI translation with entity langcode constraints.
- `packages/foundation/src/` — rejected: Foundation must not import entity concepts; a generic string-validator utility would be too detached from its consumer.
- `packages/cli/src/` — rejected: would make the canonical pattern only available to CLI consumers; entity layer is the natural authority.
- Inline regex in the generator — rejected: violates NFR-002 (single canonical string, importable constant).

## Discovery: CLI Interpolation Audit

**Examined**: `packages/cli/src/Handler/AddTranslationsMigrationGenerator.php` (read in full)

**Findings**:
- `phpStringLiteral()` is correctly called on `$defaultLc` (line 62), `$table` (line 60), `$translationsTable` (line 61), and all items via `$cols`/`$parts` (array literal helper).
- **Line 88**: `private const BACKEND = '{$backend}'` — bare PHP heredoc interpolation without `phpStringLiteral()`. The PHP type signature constrains `$backend` to `'sql-blob'|'sql-column'` (union type on `render()` and `renderTwoAxisFromRevisionable()`), making it practically safe. However, FR-007 "defense in depth" requires documenting this and optionally adding a runtime assertion.
- **Lines 75-79, 193**: docblock comments interpolate raw `$defaultLangcode` and `$entityType->id()`. Comments are not executable PHP; injection here is cosmetic, not a code-injection risk. No action required beyond noting it.
- `$indexName` (line 343 in `renderTwoAxisFromRevisionable`): `private const INDEX_NAME = '{$indexName}'` — same pattern as `$backend`. Value is derived as `$entityType->id() . '_tx_rev_lookup'` where `$entityType->id()` is already escaped via `phpStringLiteral()` for other uses. However, `$indexName` itself is NOT passed through `phpStringLiteral()` before interpolation. This is an additional site WP02 must audit.

**Action for WP02**: Add regex pre-validation for `$defaultLangcode` and each `$addTranslations` entry. Document the `$backend` type constraint. Add `phpStringLiteral()` around `$indexName` in `renderTwoAxisFromRevisionable()`.

## Discovery: TranslationController Current State

**Examined**: `packages/api/src/Controller/TranslationController.php` (read in full)

Constructor takes only `EntityTypeManagerInterface` + `ResourceSerializer` — no `EntityAccessHandler`. All 5 methods (`index`, `show`, `store`, `update`, `destroy`) perform no access check. The `_account` request attribute is not read anywhere. Confirmed: this is the exact gap described in #1445.

`loadTranslatableEntity()` is a private helper — the access check should be inserted in each public method, not in `loadTranslatableEntity()`, because the account context comes from the request parameter which the individual methods receive.

Correction from spec analysis: `index` and `show` do NOT take a `Request` parameter in the current signature — they take `string $entityTypeId, int|string $id`. The access check injection will require adding `Request $request` (or equivalent) as a parameter, OR threading the account from a middleware-set request context. CLAUDE.md confirms: `$request->attributes->get('_account')`. The implementer must decide the cleanest approach — likely adding `?AccountInterface $account = null` as a parameter with the controller's DI container resolving it, or accepting a `ServerRequestInterface` in each method. This is a WP01 implementation detail.

## Discovery: Integration Test Phase

**Examined**: `tests/Integration/` directory listing.

Existing phases: Phase3 through Phase29, plus named directories (AdminSurface, DBAL, EntityStorage, etc.). The highest numbered phase is Phase29. The translation substrate from M-006 likely landed in Phase27 or Phase28 (based on the M-006 timeline). Translation access control integration test → `tests/Integration/Phase29/TranslationAccessControlTest.php`.

If Phase29 already has unrelated tests, the new test still goes there (PHPUnit testsuite `Integration` picks up all files under `tests/Integration/`). Alternatively, if Phase29 is M-006 translations, the test may co-locate there. WP01 implementer should check `ls tests/Integration/Phase29/` and add the new test file alongside existing ones.

## Discovery: M-B Cross-Mission Interaction

**Examined**: `kitty-specs/access-fail-closed-completeness-01KS3RJT/spec.md`

M-B WP02 replaces the `AccessPolicyRegistry`'s constructor heuristic with a container-resolved typed resolver protocol. `EntityAccessHandler::check()` calls `AccessPolicyRegistry::findForEntity()` (or equivalent). The current registry silently skips policies with injected dependencies — after M-B WP02 merges, this bug is fixed and boot fails closed on unresolvable policies.

**Impact on M-C WP01**: If M-C WP01 (TranslationController access gate) is merged before M-B WP02, the new `EntityAccessHandler` injection may operate against the pre-fix registry. In a codebase with no policies that have injected dependencies, this is benign. However, the framework's fail-closed guarantee only holds after M-B WP02. The principled sequencing is: M-B WP02 merges first, then M-C WP01 merges.

**C-005 implication**: `composer verify` will pass on both missions independently. The ordering constraint is semantic, not CI-enforced.

## Discovery: TranslatableInterface Contract Gap

**Examined**: `packages/entity/src/TranslatableInterface.php` (read in full)

Confirmed: `fieldLangcode(string $fieldName): ?string` is NOT declared on the interface (98 lines, 8 methods). The method exists only on `TranslatableEntityTrait` (line 264 per spec). WP03 is a single-line edit plus a contract test.

Trait users are unaffected — `TranslatableEntityTrait::fieldLangcode()` already satisfies the new declaration. Direct implementors (none exist in the framework today) get compile-time enforcement.

## BCP-47 Regex Analysis

**Target pattern** (language + optional script + optional region; variants/private-use out of scope):
```
/^[a-zA-Z]{2,8}(-[a-zA-Z]{4})?(-[a-zA-Z]{2}|\d{3})?$/
```

| Input | Expected | Notes |
|-------|----------|-------|
| `en` | ACCEPT | 2-char language |
| `en-US` | ACCEPT | language + 2-char region |
| `zh-Hant` | ACCEPT | language + 4-char script |
| `zh-Hant-TW` | ACCEPT | language + script + region |
| `fr-CA` | ACCEPT | language + region |
| `en-CA;DROP TABLE users;` | REJECT | injection payload |
| `` (empty) | REJECT | empty string |
| `\x00en` | REJECT | control characters |
| ` en` | REJECT | leading whitespace |
| `en ` | REJECT | trailing whitespace |
| `x-private` | REJECT (v1) | private-use subtag — out of scope |
| `en-Latn-US-valencia` | REJECT (v1) | variant subtag — out of scope |

Constant name: `LangcodeValidator::BCP47_PATTERN`
Error message template: `"Invalid langcode '{$value}': must match BCP-47 pattern (language[−script][−region]). Got: {$value}"`
