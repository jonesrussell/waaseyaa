# Data Model — Attribute-First Entity Definition

This mission's "data model" is the attribute API and the entity-metadata structures derived from it. There are no persisted entities introduced.

---

## Attributes (PHP class-level / property-level)

### `Waaseyaa\Entity\Attribute\Field` (NEW)

```php
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    public function __construct(
        public ?string $type = null,         // when null → inferred from PHP property type
        public ?bool $required = null,       // when null → derived from nullability + presence of $default
        public mixed $default = null,        // null = "no explicit default" sentinel; respect $required for true semantics
        public string $label = '',
        public string $description = '',
        public array $settings = [],         // arbitrary settings; merged with type-inferred settings (e.g. enum_class)
        public bool $readOnly = false,
        public bool $translatable = false,
        public bool $revisionable = false,
    ) {}
}
```

**Targets**: `TARGET_PROPERTY`. Not repeatable.

**Allowed on**: `public` typed properties of a class extending `ContentEntityBase`.

**Throws** (at `EntityType::fromClass()` time):
- Property is not `public` → `EntityMetadataException`.
- Property has no declared type AND `type:` is null → `EntityMetadataException`.
- Property's PHP type is unsupported AND `type:` is null → `EntityMetadataException`.
- `type:` is set AND conflicts with the inferred PHP type → `EntityMetadataException`.

### `Waaseyaa\Entity\Attribute\ContentEntityType` (EXTENDED)

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ContentEntityType
{
    public function __construct(
        public string $id,                    // required, unchanged
        public string $label = '',            // NEW: human label; defaults to $id with first letter uppercased if empty
        public string $description = '',      // NEW: optional description
    ) {}
}
```

**Backwards compat**: `id`-only call sites (`#[ContentEntityType(id: 'foo')]`) continue to work; new params have defaults. Greenfield context means we can also encourage updating call sites, but it isn't enforced.

### `Waaseyaa\Entity\Attribute\ContentEntityKeys` (UNCHANGED)

No change. Keys map continues to use the existing parameters.

---

## Type Inference (PHP property type → field shape)

The mapping table from `plan.md` AD-4 plus a concrete error-mode list.

| PHP type expression | Field type id | `required` (when not overridden) | Settings injected | Errors |
|---|---|---|---|---|
| `string` | `string` | true | — | — |
| `?string` | `string` | false | — | — |
| `int` | `integer` | true | — | — |
| `?int` | `integer` | false | — | — |
| `bool` | `boolean` | true | — | — |
| `?bool` | `boolean` | false | — | — |
| `float` | `float` | true | — | — |
| `?float` | `float` | false | — | — |
| `array` | `json` | true | — | — |
| `?array` | `json` | false | — | — |
| `\DateTimeImmutable` | `datetime` | true | — | — |
| `?\DateTimeImmutable` | `datetime` | false | — | — |
| `class extends BackedEnum` | `string` | true (false if nullable) | `['enum_class' => Foo::class]` | — |
| Union types | (not supported) | — | — | Throws unless `type:` explicit |
| Intersection types | (not supported) | — | — | Throws unless `type:` explicit |
| Untyped property | (not inferable) | — | — | Throws unless `type:` explicit |
| `iterable` | (not supported) | — | — | Throws — declare `array` for `'json'`, or use explicit `type:` |
| Reference to user class (non-enum) | (not supported) | — | — | Throws unless `type:` explicit (e.g., for `'entity_reference'`) |

When `type:` is set explicitly:
- If the PHP type is compatible with the override (e.g., `public ?int $x` with `#[Field(type: 'integer')]`), accept.
- If the PHP type is incompatible (`public string $x` with `#[Field(type: 'integer')]`), throw.
- If the PHP type is unsupported but `type:` is given, accept and bypass inference.

---

## Resolved metadata structure

### `Waaseyaa\Entity\Attribute\EntityClassMetadata` (EXTENDED)

```php
final readonly class EntityClassMetadata
{
    public function __construct(
        public ?string $typeId,
        /** @var array<string, string> logical-key → storage-name */
        public array $keys,
        public string $label = '',                          // NEW
        public string $description = '',                    // NEW
        /** @var array<string, FieldDefinition> field-name → definition */
        public array $fields = [],                          // NEW
    ) {}
}
```

`EntityMetadataReader::forClass()` returns this populated record. `resolveFields()` is a new method that walks the class hierarchy and assembles the field map.

---

## `EntityType::fromClass()` output shape

```php
public static function fromClass(string $class): EntityType
{
    $meta = EntityMetadataReader::forClass($class);
    if ($meta->typeId === null) {
        throw new EntityMetadataException(...);
    }
    return new EntityType(
        id: $meta->typeId,
        label: $meta->label !== '' ? $meta->label : ucfirst($meta->typeId),
        class: $class,
        keys: $meta->keys,
        // ... other EntityType params from sensible defaults or future attribute extensions
        // Note: NO fieldDefinitions parameter — passed via internal mechanism
    );
}
```

The internal mechanism for passing `$meta->fields` into `EntityType` is an implementation detail. Two viable approaches captured for plan-time decision:
- **Approach 1**: keep `fieldDefinitions:` as a private/internal constructor parameter (e.g., last-named param, marked `@internal`); `fromClass()` and `TestEntityType::stub()` are the only callers in the framework.
- **Approach 2**: `EntityType` exposes a `withFieldDefinitions(array $defs): self` method (since it's `readonly`, this returns a new instance); `fromClass()` constructs the base instance and immediately calls this.

Plan picks Approach 1 for simplicity; Approach 2 stays in research notes for revisit if friction surfaces during implementation.

---

## State transitions

None — attributes are static class-level metadata; resolution is idempotent and read-only at runtime (modulo the per-class cache invalidation in tests).

---

## Validation rules

Enforced inside `EntityMetadataReader::resolveFields()`:

1. Each `#[Field]`-decorated property must be `public`. Else throw.
2. Each `#[Field]`-decorated property must have a typed declaration (or override via `type:` parameter). Else throw.
3. The inferred or explicit field type id must be one of the registered field-type IDs (`boolean`, `string`, `integer`, etc.). Else throw — we won't silently allow unknown type IDs.
4. If `type:` parameter conflicts with the inferred PHP type → throw.
5. The class must extend `ContentEntityBase`. Else throw.
6. The class must declare `#[ContentEntityType]`. Else throw (covered by existing `EntityMetadataReader::resolveTypeId()` returning null + `fromClass()` checking).
