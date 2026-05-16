# Contract: Sync-Store File Format

**Stability scope:** charter §5.5 (amended at mission close) — **the YAML format is on stable surface**
**FRs covered:** FR-009..FR-016, FR-011a (field-value mapping table)
**Owned by:** WP02

## Filename convention

`<entity_type>.<entity_id>.yml`

Both segments MUST match `^[a-z][a-z0-9_]*$`. Examples:

```
role.admin.yml
role.coordinator.yml
taxonomy_vocabulary.community_categories.yml
menu.main_navigation.yml
```

Files outside this convention are warn-skipped during `config:import` (not an error). They are surfaced in `config:status` output as "unrecognised sync entries" to alert the operator.

## File structure (canonical example)

```yaml
_meta:
  dependencies:
    - role.admin
    - taxonomy_vocabulary.community_categories
  entity_type: role
  langcode: en
  uuid: 0193abcd-7c4d-7000-8b6e-1a2b3c4d5e6f
description: Coordinators manage community calendars and welcome new members.
id: coordinator
label: Coordinator
permissions:
  - calendar.administer
  - membership.approve
  - membership.invite
weight: 10
```

### `_meta` block (REQUIRED, FIRST)

| Key | Type | Required | Notes |
|---|---|---|---|
| `entity_type` | string | YES | MUST match filename prefix; mismatch → `ConfigSerializationException` |
| `uuid` | string | YES | Stable across renames; generated deterministically for legacy entities |
| `dependencies` | list&lt;string&gt; | YES (may be `[]`) | Each entry is `<entity_type>.<entity_id>` — referenced config that must exist before this one |
| `langcode` | string | YES | Defaults to `en` for non-translatable config; populated from entity's `langcode()` |

The `_meta` block sorts alphabetically (`dependencies`, `entity_type`, `langcode`, `uuid` — alphabetical order). The block appears **first** in the file, before any field values. New `_meta` keys require charter §4 deprecation cycle.

### Field values (sorted alphabetically)

Every key after `_meta` is an entity field value. Keys sort alphabetically. The serializer reads the entity's `FieldDefinition`s to determine valid keys; fields NOT declared on the entity type MUST NOT appear (FR-012).

Values map per the type table below (FR-011a).

## Field-value mapping table

The mapping is **field-definition-driven**, not entity-class-driven. Whatever `FieldDefinition` type the field declares determines the YAML shape.

| `FieldDefinition` type | YAML representation | Example |
|---|---|---|
| `string` | scalar string | `label: Coordinator` |
| `int` | scalar int | `weight: 10` |
| `bool` | scalar bool | `enabled: true` |
| `datetime` | ISO 8601 string | `created_at: '2026-05-15T18:42:01+00:00'` |
| `json` | mapping or sequence (native YAML structure) | `settings: { theme: dark, retention_days: 30 }` |
| `text` | scalar string (block scalar when multi-line) | `description: \| \\n A paragraph\\n with multiple lines.` |
| `uuid` | scalar string | `target_uuid: 0193abcd-7c4d-...` |
| `entity_reference` | `<entity_type>.<entity_id>` string | `default_role: role.member` |
| `field_list` | sequence of scalars | `permissions: [calendar.administer, membership.approve]` |

Future field types extend this table by amendment (charter §4 deprecation cycle for backward-incompatible changes; additive new types ship freely).

## Serialization rules (pinned for deterministic diffs)

- Keys sort alphabetically within `_meta` and within each top-level field group.
- `_meta` block emits first.
- Block style for non-empty collections; flow style `[]` / `{}` for empty.
- Multi-line strings use YAML block scalars (`|` literal for newline preservation; `>` folded permitted where content tolerates fold).
- No tags, no anchors, no aliases. Round-trip safety; humans read these.
- UTF-8 throughout; no BOM.
- Trailing newline at end of file (POSIX text-file convention).

## Validation pipeline (FR-013, FR-037..FR-040)

When the deserializer reads a file:

1. **Filename ↔ entity_type check.** `<entity_type>` segment of filename MUST equal `_meta.entity_type`. Mismatch → `ConfigSerializationException`.
2. **Required-keys check.** `_meta.entity_type`, `_meta.uuid`, `_meta.langcode` MUST be present. `_meta.dependencies` defaults to `[]` if absent.
3. **Field-presence check.** Top-level keys (other than `_meta`) MUST appear in the entity type's `FieldDefinition` set. Stray keys → `ConfigSerializationException`.
4. **Type coercion via `FieldValueMapper`.** Each value coerces to the declared `FieldDefinition` type. Coercion failure → `ConfigSerializationException`.
5. **`FieldDefinition::validators()` pipeline** (run by `ConfigSyncValidator`, invoked from `config:validate` and from `config:import` pre-step). Field-level validators raise `ConfigImportFailedException` per FR-027.

Validation step 5 is the same pipeline ADR 013 ships for content entities. Reuse, not duplication.

## Rename handling

A sync file whose `_meta.uuid` matches an existing active-store entity but whose filename-derived `entity_id` differs is treated as a **rename** at import time:

- Same DB row (UUID stable).
- New `id` value on the entity.
- `config:diff` reports `STATUS_RENAMED` with `renamedFrom` populated.
- No delete + create — relationships and references survive.

This is the operative reason UUIDs are first-class in `_meta`: rename without UUID would require manual migration of every referring entity.

## What does NOT appear in sync files

- Per-environment values (API keys, mail-from addresses, environment URLs). These live in env vars read by `config/waaseyaa.php` (FR-061, R-05).
- Content entity data. CMI is config-only by definition; content promotion uses fixtures or seeds.
- Cache state, session state, queue state, any runtime artifact. The sync store is declarative deploy state.
- Computed / derived fields (entities can declare fields that exclude themselves from CMI via a forthcoming `#[NotPersisted]` or similar marker — TBD at WP02 if any current entity field needs the marker).

## Backwards compatibility commitments

Once landed, the YAML format is on stable surface (charter §5.5 amended). The following are breaking changes requiring charter §4 deprecation cycle:

- Renaming any `_meta` key.
- Changing the filename convention.
- Removing a row from the field-value type table.
- Changing scalar/sequence/mapping shape for an existing type (e.g. switching `entity_reference` from `<type>.<id>` string to a structured `{type, id}` map).

The following are additive (no deprecation needed):

- Adding new `FieldDefinition` types to the type table.
- Adding new optional `_meta` keys (defaulted to a sensible value at read time).
- Adding new emitter / parser options that produce the same canonical output for existing files.
