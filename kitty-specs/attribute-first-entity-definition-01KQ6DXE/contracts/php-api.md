# Contracts — Public PHP API Surface

This mission ships public PHP API for downstream apps and packages to consume. This file is the contract.

---

## 1. `Waaseyaa\Entity\Attribute\Field` (NEW)

```php
namespace Waaseyaa\Entity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    public function __construct(
        public ?string $type = null,
        public ?bool $required = null,
        public mixed $default = null,
        public string $label = '',
        public string $description = '',
        public array $settings = [],
        public bool $readOnly = false,
        public bool $translatable = false,
        public bool $revisionable = false,
    ) {}
}
```

**Stability**: stable from M1.

---

## 2. `Waaseyaa\Entity\Attribute\ContentEntityType` (EXTENDED)

```php
namespace Waaseyaa\Entity\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ContentEntityType
{
    public function __construct(
        public string $id,
        public string $label = '',           // NEW in M1
        public string $description = '',     // NEW in M1
    ) {}
}
```

**Stability**: stable. New parameters are additive; existing call sites continue to work.

---

## 3. `Waaseyaa\Entity\EntityType::fromClass()` (NEW)

```php
namespace Waaseyaa\Entity;

final readonly class EntityType implements EntityTypeInterface
{
    /**
     * Build an EntityType from a content entity class's attributes.
     *
     * Reads:
     *   - #[ContentEntityType(id, label, description)]
     *   - #[ContentEntityKeys(...)]
     *   - #[Field(...)] on each public typed property
     *
     * @param class-string<ContentEntityBase> $class
     * @throws EntityMetadataException When attributes are missing or invalid.
     */
    public static function fromClass(string $class): self;
}
```

**Cached**: yes, by class name (via `EntityMetadataReader::$cache`). Subsequent calls return the same instance.

**Stability**: stable.

---

## 4. `Waaseyaa\Entity\EntityType::__construct()` (CHANGED)

**Before M1**:
```php
public function __construct(
    string $id,
    string $label,
    string $class,
    string $storageClass = SqlEntityStorage::class,
    array $keys = [],
    bool $revisionable = false,
    bool $revisionDefault = false,
    bool $translatable = false,
    ?string $bundleEntityType = null,
    array $constraints = [],
    array $fieldDefinitions = [],   // ← REMOVED
    ?string $group = null,
    ?string $description = null,
);
```

**After M1**:
```php
public function __construct(
    string $id,
    string $label,
    string $class,
    string $storageClass = SqlEntityStorage::class,
    array $keys = [],
    bool $revisionable = false,
    bool $revisionDefault = false,
    bool $translatable = false,
    ?string $bundleEntityType = null,
    array $constraints = [],
    ?string $group = null,
    ?string $description = null,
    /** @internal — set only by fromClass() and test helpers */
    array $_fieldDefinitions = [],
);
```

**Stability**: API break — `fieldDefinitions:` is gone. The trailing `_fieldDefinitions` is `@internal` and prefixed with underscore to discourage app use; standard apps go through `fromClass()`.

**Migration recipe** (for the 45 affected sites):
- If you have a real entity class: replace `new EntityType(id: …, fieldDefinitions: [...])` with `EntityType::fromClass(MyEntity::class)`.
- If you don't have a real entity class (raw schema test): replace with `TestEntityType::stub('id', [...])`.

---

## 5. `Waaseyaa\Entity\EntityTypeManager::registerEntityType()` (UNCHANGED externally)

Internally drops the call to `assertClassMetadataMatchesEntityType()` and deletes that private method. External signature unchanged.

---

## 6. `Waaseyaa\Entity\Attribute\EntityMetadataReader::resolveFields()` (NEW)

```php
namespace Waaseyaa\Entity\Attribute;

use Waaseyaa\Field\FieldDefinition;

final class EntityMetadataReader
{
    /**
     * Resolve the field definition map for a content entity class.
     * Walks the class hierarchy from the first concrete subclass of
     * ContentEntityBase down to $class; later (deeper) declarations override.
     *
     * @param class-string $class
     * @return array<string, FieldDefinition>
     * @throws EntityMetadataException When attributes are invalid.
     */
    public static function resolveFields(string $class): array;

    // Existing: forClass(), resolveTypeId(), resolveKeys(), clearCache(), clearCacheForClass()
}
```

**Stability**: stable.

---

## 7. Errors / Exceptions

All exceptions raised by attribute reading and `EntityType::fromClass()` extend or are instances of `Waaseyaa\Entity\Exception\EntityMetadataException` (existing class). Each error carries the class FQN, property name (where applicable), and a one-line remediation hint per NFR-004.

### Error classes & messages (representative — final wording at implementation time)

| Trigger | Message template |
|---|---|
| Class missing `#[ContentEntityType]` | `Concrete content entity %s must declare #[ContentEntityType(id: "…")]. Add the attribute on the class declaration.` |
| `#[Field]` on non-public property | `#[Field] on %s::$%s requires the property to be public; got %s. Either make it public or remove the attribute.` |
| `#[Field]` on untyped property without explicit `type:` | `#[Field] on %s::$%s cannot infer field type — declare a property type or pass type: explicitly.` |
| `#[Field]` on unsupported type without explicit `type:` | `#[Field] on %s::$%s cannot infer a field type for PHP type %s; supported PHP types: string, int, bool, float, array, \DateTimeImmutable, BackedEnum, or pass type: explicitly.` |
| `#[Field(type: '…')]` conflicts with inferred type | `#[Field(type: %s)] on %s::$%s conflicts with property's PHP type %s (would infer %s). Pick one or remove type: parameter.` |
| Unknown explicit `type:` value | `#[Field(type: %s)] on %s::$%s — unknown field type id; valid ids are: %s.` |

---

## 8. Test helper API

### `Waaseyaa\Entity\Tests\Helper\TestEntityType` (NEW, test-only)

```php
namespace Waaseyaa\Entity\Tests\Helper;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Field\FieldDefinition;

final class TestEntityType
{
    /**
     * Build a stub EntityType for tests that need shape independent of any class.
     *
     * @param array<string, FieldDefinition> $fieldDefinitions
     */
    public static function stub(
        string $id,
        array $fieldDefinitions = [],
        array $keys = ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
        ?string $class = null,   // synthetic class name when no real class exists
    ): EntityType;
}
```

**Stability**: test-only; not part of the public app-facing API. Lives under `tests/Helper/` and is not in the production autoloader.
