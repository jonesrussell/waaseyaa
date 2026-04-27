# ContentEntityBase Constructor Cleanup — Specification (STUB)

**Status**: Stub. Full spec via `/spec-kitty.specify` when ready to plan.
**Predecessor**: `attribute-first-entity-definition-01KQ6DXE` (and ideally classmap discovery merged too)

---

## Scope statement

After M1 makes attributes the only source of entity metadata, the `ContentEntityBase::__construct(array $values, string $entityTypeId, array $entityKeys, array $fieldDefinitions)` signature has three legacy parameters that no longer carry useful information. This mission tightens the constructor to the minimal modern shape.

## Target state

```php
abstract class ContentEntityBase extends EntityBase implements ContentEntityInterface, HydratableFromStorageInterface
{
    public function __construct(array $values = [])
    {
        $meta = EntityMetadataReader::forClass(static::class);
        if ($meta->typeId === null) {
            throw new EntityMetadataException(...);
        }
        parent::__construct($values, $meta->typeId, $meta->keys);
    }
}
```

## In-scope sketch

- Drop `$entityTypeId`, `$entityKeys`, `$fieldDefinitions` parameters from `ContentEntityBase::__construct()`.
- Drop the `protected array $fieldDefinitions` per-instance array (the "legacy path" the framework's own comment names).
- `getFieldDefinitions()` reads exclusively from the bundle/registry-aware path (`$fieldRegistry`).
- Each entity class can drop its boilerplate constructor override entirely (the override exists today only to populate the now-removed default parameters).

## Out-of-scope sketch

- Wider refactor of how field definitions reach storage drivers (storage continues to consume the same `array<string, FieldDefinitionInterface>` map).
- Hydration changes (`HydratableFromStorageInterface` contract unchanged).

## Open design questions

- Are there test fixtures or framework-internal helpers that still construct entities with explicit `$entityTypeId` strings? Audit needed.
- What happens to the `static::$casts` mechanism that runs through the constructor today?

## Predecessor dependencies

- M1 `attribute-first-entity-definition` must be merged.
- Strongly recommended: `attribute-entity-classmap-discovery` also merged, so the migration sweep is comprehensive.
