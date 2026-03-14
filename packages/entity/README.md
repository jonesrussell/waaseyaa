# waaseyaa/entity

**Layer 1 — Core Data**

Entity type system for Waaseyaa applications.

Defines `EntityInterface`, `EntityBase`, `EntityType` (with id, label, class, keys, and field definitions), and `EntityTypeManager`. Entity subclasses accept `(array $values)` and hardcode their `entityTypeId` and `entityKeys`. Use `$entity->enforceIsNew()` before saving pre-keyed entities to force `INSERT` over `UPDATE`.

Key classes: `EntityInterface`, `EntityBase`, `EntityType`, `EntityTypeManager`, `EntityTypeManagerInterface`.
