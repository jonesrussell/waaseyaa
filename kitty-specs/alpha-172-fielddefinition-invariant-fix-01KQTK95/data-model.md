# Data Model: Alpha.172 FieldDefinition Invariant Fix

**Mission**: `01KQTK95TKYCKCFA1C07XE0CFM`
**Date**: 2026-05-04

---

## No schema or storage changes

This mission does not introduce, remove, rename, or restructure any persistent data. No migrations are needed. No table is touched. No serialization format changes.

## Entities of interest (read-only context)

For test design purposes, the following framework value objects are touched:

### `Waaseyaa\Field\FieldDefinition`

A descriptor for a single field on an entity type. Constructed via named parameters; the relevant property for this mission is:

- `targetEntityTypeId: string` — the id of the entity type this field belongs to. Defaults to `''` (empty) at construction. Bind-time invariant requires this to match the registering `EntityType::id`.

Accessor: `getTargetEntityTypeId(): string`.

### `Waaseyaa\Entity\EntityType`

The descriptor for an entity type. The mission registers the existing entity types `group_type` (in `waaseyaa/groups`) and `taxonomy_vocabulary` (in `waaseyaa/taxonomy`); neither is changed structurally.

### `Waaseyaa\Field\FieldDefinitionRegistry`

The registry that walks `EntityType::_fieldDefinitions` and binds each `FieldDefinition` to the entity type. Throws `\InvalidArgumentException` when the bound field's `targetEntityTypeId` does not equal the owning entity type's id. The registry's contract is not changed by this mission; only the inputs (provider construction sites) are corrected.

## State transitions

None — the mission is a construction-site correction with no runtime state machine changes.

## Validation rules (recap from spec)

- Every bound `FieldDefinition` MUST have `targetEntityTypeId !== ''`.
- Every bound `FieldDefinition` MUST have `targetEntityTypeId === <EntityType::id>` of its owning bundle.

These rules are pre-existing (alpha.171); this mission corrects the call sites that violate them.
