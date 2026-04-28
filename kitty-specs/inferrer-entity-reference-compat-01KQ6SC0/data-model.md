# Data Model — inferrer-entity-reference-compat

## Scope

This mission introduces **no new entities, attributes, or relationships**. It changes a metadata-resolver's compatibility check and, as a consequence, refines the PHP type declarations of two existing entity properties whose stored values are unchanged.

## Affected metadata pipeline

```
PHP property declaration (?int / ?string)
  → ReflectionProperty
    → FieldTypeInferrer::infer()
      → FieldTypeInferrer::isCompatible(inferred='integer'|'string', explicit='entity_reference')
        → returns true (NEW)            ← previously: returns false → throws EntityMetadataException
      → {type: 'entity_reference', required: …, settings: [...target_entity_type_id...]}
    → FieldDefinition
  → EntityTypeBuilder reads target_entity_type_id → builds reference field
```

## Impacted entities (storage shape unchanged)

| Entity | Property | Before | After | Storage column shape |
|---|---|---|---|---|
| `node` | `uid` | untyped + `@var int|null` | `?int` | unchanged (integer FK to user.uid) |
| `taxonomy_term` | `parent_id` | untyped + `@var int|null` | `?int` | unchanged (integer FK to taxonomy_term.tid) |

No migration is required; column names and types are unchanged.

## New surface

None. `FieldTypeInferrer` gains one private constant and one branch in `isCompatible()`. `compatibilityGroups()` public seam is unchanged.
