# Contract: Waaseyaa\Entity\TranslatableInterface

**Mission:** M-006 · **Status:** stable surface on merge per stability-charter §5.3
**File:** `packages/entity/src/TranslatableInterface.php`
**Normative spec:** [`spec.md` §3.2](../spec.md#32-translatableentityinterface-surface)
**Implemented by:** `Waaseyaa\Entity\ContentEntityBase` (WP01)

---

## Signature

```php
namespace Waaseyaa\Entity;

interface TranslatableInterface
{
    public function defaultLangcode(): string;
    public function activeLangcode(): string;
    public function language(): string;                    // deprecated alias for activeLangcode()

    public function hasTranslation(string $langcode): bool;
    public function getTranslation(string $langcode): static;
    public function addTranslation(string $langcode): static;
    public function removeTranslation(string $langcode): void;

    /** @return iterable<string> */
    public function translations(): iterable;
    /** @return array<int,string> */
    public function getTranslationLanguages(): array;
}
```

---

## Invariants

1. `activeLangcode()` of an entity loaded via `EntityRepository::find($id)` (without chained `getTranslation()`) MUST equal `defaultLangcode()`.
2. `defaultLangcode()` MUST throw `EntityTranslationException::langcodeRequired()` when the underlying entity has no `default_langcode` value set.
3. `getTranslation($lc)` where `hasTranslation($lc) === false` MUST throw `EntityTranslationException::translationNotFound($lc)`.
4. `addTranslation($lc)` where `hasTranslation($lc) === true` MUST throw `EntityTranslationException::translationAlreadyExists($lc)`.
5. `removeTranslation($lc)` where `$lc === defaultLangcode()` MUST throw `EntityTranslationException::cannotRemoveDefault($lc)`. Removing a non-default translation marks the row for deletion; the actual delete happens on the next `EntityRepository::save()`.
6. `translations()` MUST yield default-langcode first, then ascending lex.
7. `getTranslationLanguages()` MUST return the same set as `translations()` materialized to `array`.
8. Methods called on an entity whose type has `translatable: false` MUST throw `EntityTranslationException::notTranslatable($entityTypeId)`.
9. `language()` is a deprecated alias for `activeLangcode()`. Implementations MUST return the same value from both methods.

---

## Backwards compatibility

- Pre-M-006 `TranslatableInterface` exposed `language()`, `hasTranslation()`, `getTranslation()`, `getTranslationLanguages()`. All four are preserved as the canonical names except `language()` which becomes a deprecation alias.
- No implementor existed pre-M-006; the only callers were docblock references. Net-new in practice.

---

## Test coverage (T01..T08, T10..T12 in spec §9.1)

Verified via `Waaseyaa\Entity\Testing\TranslatableEntityContractTest`. Backend subclasses run against `sql-blob` and `sql-column` configurations.

---

## Discoverability

The interface MUST be declared in `EntityType` for any type setting `translatable: true`. `EntityType::__construct` boot validation throws `InvalidEntityTypeException::translatableEntityClassNotImplementingInterface()` if the entity class does not implement.
