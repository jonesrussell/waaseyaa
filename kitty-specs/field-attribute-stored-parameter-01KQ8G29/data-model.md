# Data Model — `#[Field(stored:)]`

This mission does not introduce a new domain entity. It extends an existing PHP attribute and re-routes the `Group` entity registration through the canonical attribute-first path. The "data model" here describes the metadata flow, not persisted records.

## Existing types involved

### `Waaseyaa\Entity\Attribute\Field` (modified)
PHP attribute, target `TARGET_PROPERTY`, marker for entity field declarations.
**New property:**
- `public FieldStorage $stored` — default `FieldStorage::Column`. Forwarded verbatim to `FieldDefinition`.

### `Waaseyaa\Field\FieldStorage` (unchanged)
Enum with two cases:
- `Column` — schema-handler materializes a dedicated SQL column; query routing reads it as a column.
- `Data` — no dedicated column; values live in the `_data` JSON blob; queries resolve via `json_extract`.

### `Waaseyaa\Field\FieldDefinition` (unchanged)
Already accepts `stored: FieldStorage = FieldStorage::Column` at constructor position 15.

### `Waaseyaa\Entity\Attribute\EntityMetadataReader` (modified)
Static helper. `resolveFields()` walks `#[Field]`-decorated properties on a class and emits a `FieldDefinition` map. Adds one new line to forward `$field->stored`.

### `Waaseyaa\Entity\EntityType::fromClass()` (unchanged)
Consumes `EntityMetadataReader::forClass()` to materialize an `EntityType` purely from class metadata. After this mission, `Group` registration runs through this path.

## Flow

```
Entity class (with #[Field(stored: FieldStorage::Data)] property)
  → EntityMetadataReader::resolveFields()
      → FieldTypeInferrer::infer()           [unchanged; returns {type, required, settings}]
      → new FieldDefinition(..., stored: $field->stored)
  → EntityType::fromClass(...) consumes the field map
  → ServiceProvider registers the EntityType
```

## Affected entity in this mission

### `Waaseyaa\Groups\Group`
Adds three public typed properties:
- `public int $status` — `#[Field(type: 'integer', default: 1, label: 'Status', description: 'Whether the group is published.', stored: FieldStorage::Data)]`
- `public int $created_at` — `#[Field(type: 'integer', label: 'Created at', stored: FieldStorage::Data)]`
- `public int $updated_at` — `#[Field(type: 'integer', label: 'Updated at', stored: FieldStorage::Data)]`

These match — bit-for-bit — the `FieldDefinition` triplet currently constructed manually inside `GroupsServiceProvider` (`packages/groups/src/GroupsServiceProvider.php:43-69`).

## Storage implications

No on-disk storage changes. The bundle-partitioned `group__{bundle}` data tables continue to carry `_data` JSON blobs; the three migrated fields continue to be read via `json_extract` because `stored` resolves to `FieldStorage::Data` in both the old and new code paths. The `groups/tests/` suite (13/13) is the binary regression gate.
