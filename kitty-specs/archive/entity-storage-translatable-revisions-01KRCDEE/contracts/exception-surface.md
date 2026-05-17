# Contract — Exception surface

**Status:** Normative for WP04.
**Refs:** FR-040, FR-041, FR-042; spec §3.9; revalidation §12.1.

---

## 1. Scope

Defines the consolidated exception surface for M-004. Follows M-006's unified-domain-exception pattern strictly. The original spec's "five exception classes" plan is dropped.

## 2. Two classes total

| Class | Layer | Status | New factories in M-004 |
|---|---|---|---|
| `Waaseyaa\Entity\Exception\EntityTranslationException` | L1 (entity) | EXTEND (existing from M-006) | `historicalRevisionWrite($vid, $langcode)` |
| `Waaseyaa\EntityStorage\Exception\StorageMigrationException` | L1 (entity-storage) | **NEW** | `noOpPromotion($entityType)`, `unsupportedTwoAxisField($fieldName, $backend)` |

## 3. `EntityTranslationException` (extension)

### 3.1 Existing factories (M-006, unchanged)

| Factory | Code string | Reused for |
|---|---|---|
| `translationNotFound(string $langcode): self` | `translation_not_found` | FR-016, multi-language save with unknown langcode |
| `cannotRemoveDefault(string $langcode): self` | `cannot_remove_default` | FR-035 |
| `langcodeRequired(): self` | `langcode_required` | M-006 internal; reused |
| `notTranslatable(string $entityTypeId): self` | `not_translatable` | Non-translatable entity type + withTranslations save attempt |
| `translationAlreadyExists(string $langcode): self` | `translation_already_exists` | M-006 internal; reused |

### 3.2 New factory (FR-040)

```php
public static function historicalRevisionWrite(int $vid, string $langcode): self
{
    return new self(
        message: sprintf(
            'Cannot save a historical revision (vid=%d, langcode=%s); load the current revision and save that.',
            $vid,
            $langcode,
        ),
        code: 'historical_revision_write',
    );
}
```

**Trigger sites:**
- `EntityRepository::save($entity)` (or storage coordinator equivalent) inspects the entity's revision state; if `isCurrentRevision()` returns false for the active langcode, raise this exception.
- Spec FR-017 mandates this is the only allowed exception for the case.

## 4. `StorageMigrationException` (NEW)

### 4.1 Class definition

```php
namespace Waaseyaa\EntityStorage\Exception;

final class StorageMigrationException extends \RuntimeException
{
    public readonly string $errorCode;

    private function __construct(string $message, string $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
    }

    public static function noOpPromotion(string $entityType): self
    {
        return new self(
            message: sprintf(
                'Entity type "%s" is already two-axis (revisionable + translatable); no migration needed.',
                $entityType,
            ),
            errorCode: 'no_op_promotion',
        );
    }

    public static function unsupportedTwoAxisField(string $fieldName, string $backend): self
    {
        return new self(
            message: sprintf(
                'Field "%s" uses backend "%s" which does not support translation × revision composition; allowed backends are sql-column and sql-blob.',
                $fieldName,
                $backend,
            ),
            errorCode: 'unsupported_two_axis_field',
        );
    }
}
```

### 4.2 Factories

| Factory | Code string | Trigger |
|---|---|---|
| `noOpPromotion(string $entityType): self` | `no_op_promotion` | FR-029: migration generator called with `--add-translations` or `--add-revisions` against a type that is already two-axis. |
| `unsupportedTwoAxisField(string $fieldName, string $backend): self` | `unsupported_two_axis_field` | FR-006: schema sync / kernel boot detects a field declared `translatable()` on `vector` or `remote` backend. |

## 5. Stability semantics (FR-041, FR-042)

- Class names (`EntityTranslationException`, `StorageMigrationException`) are stable surface.
- Each factory's `errorCode` string is stable surface (used by application-level error handling, log aggregation, error reporting).
- Renames or removals follow the deprecation cycle in charter §4.
- New factories MAY be added to either class without a deprecation cycle (additive).
- Constructor parameters MAY be reordered or renamed (constructor is private — factories are the public surface).

## 6. Classes explicitly NOT created

The original spec called for five separate classes. Per the M-006 reconciliation (§12.1), these are NOT introduced:

- ❌ `HistoricalRevisionWriteException` — use `EntityTranslationException::historicalRevisionWrite()` factory instead.
- ❌ `TranslationNotFoundException` — use existing `EntityTranslationException::translationNotFound()` factory.
- ❌ `DefaultLangcodeRemovalException` — use existing `EntityTranslationException::cannotRemoveDefault()` factory.
- ❌ `UnsupportedTwoAxisFieldException` — use `StorageMigrationException::unsupportedTwoAxisField()` factory.
- ❌ `NoOpMigrationException` — use `StorageMigrationException::noOpPromotion()` factory.

## 7. Test contract (WP04)

`HistoricalRevisionWriteTest` (unit):

1. `EntityTranslationException::historicalRevisionWrite(7, 'oj')` returns instance.
2. `$ex->getCode()` returns `'historical_revision_write'`.
3. `$ex->getMessage()` contains both `'7'` and `'oj'`.

`StorageMigrationExceptionTest` (unit):

1. `StorageMigrationException::noOpPromotion('teaching')` returns instance with `errorCode === 'no_op_promotion'`.
2. `StorageMigrationException::unsupportedTwoAxisField('embedding', 'vector')` returns instance with `errorCode === 'unsupported_two_axis_field'`.
3. Class is `final`.
4. Constructor is private; factories are the only construction path.

Integration tests for trigger sites live in their respective WPs (WP04 historical-write trigger; WP06 noOpPromotion; WP01/WP02 unsupportedTwoAxisField).
