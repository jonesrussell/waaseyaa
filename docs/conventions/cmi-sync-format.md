# Convention: CMI Sync-Store File Format

**Scope:** every file under `config.sync_path` (default `storage/config-sync/`).
**Owner:** M-003 (`config-management-v1-01KRCDEC`) — landed 2026-05-16.
**Charter:** [`stability-charter.md`](../specs/stability-charter.md) §5.5 — the file format itself is stable surface and follows the §4 deprecation cycle for any changes.
**Spec:** [`docs/specs/config-management.md`](../specs/config-management.md) §3.
**Cookbook:** [`docs/cookbook/config-sync.md`](../cookbook/config-sync.md).

These invariants are load-bearing. Every consumer's git history depends on
them being stable, every CI gate depends on them being deterministic, and
every operator's muscle memory depends on them being predictable.

---

## Invariant 1 — Filename convention

```
<entity_type>.<entity_id>.yml
```

- Lowercase ASCII letters, digits, and `_` only.
- Entity-type and entity-id are separated by a single `.` (period).
- File extension is `.yml`, not `.yaml`.
- Files outside this convention are **ignored** by `config:import` (warn and
  skip; not error). This lets operators stash README files, fixtures, or
  experimental snapshots in the same directory without breaking the pipeline.

Examples:

| Allowed | Reason |
|---|---|
| `role.admin.yml` | Canonical. |
| `taxonomy_vocabulary.community_categories.yml` | Underscores in either component are fine. |
| `node_type.event_post.yml` | Both components may contain `_`. |
| `README.md` | Ignored — not `.yml`. |

| Rejected (warn-and-skip) | Reason |
|---|---|
| `Role.admin.yml` | Uppercase letter. |
| `role-admin.yml` | Missing `.` separator. |
| `role.admin.yaml` | Wrong extension. |
| `role.admin.v2.yml` | Three components. |

---

## Invariant 2 — Mandatory leading `_meta` block

Every sync file MUST open with a `_meta` mapping:

```yaml
_meta:
  dependencies:
    - role.admin
    - taxonomy_vocabulary.parent_thing
  entity_type: taxonomy_vocabulary
  langcode: en
  uuid: 0193abc...
```

### `_meta` keys (stable vocabulary)

| Key | Type | Purpose | Default |
|---|---|---|---|
| `dependencies` | `string[]` | `<entity_type>.<entity_id>` ids consumed by the DAG. | `[]` |
| `entity_type` | `string` | Must match the filename prefix. Mismatch raises `ConfigSerializationException`. | (required) |
| `langcode` | `string` | Language code of the config entity. | `en` |
| `uuid` | `string` | Stable across renames; UUID-tracked rename detection compares this. | (required) |

The four keys above are the entire stable `_meta` vocabulary as of M-003.
Additive new keys MAY land in future alpha trains; renames and removals
follow the §4 deprecation cycle (charter `stability-charter.md`).

### `_meta` ordering

Keys within `_meta` are sorted alphabetically. The `_meta` block itself
always appears first in the file — before any field-value key. This is
non-negotiable: it's the anchor every operator looks for when scanning a
diff.

---

## Invariant 3 — Field-value mapping

Following `_meta`, the remaining top-level keys are entity field values.
`FieldDefinition` types map to YAML:

| `FieldDefinition` type | YAML representation | Example |
|---|---|---|
| `string` | scalar string | `label: Editor` |
| `int` | scalar int | `weight: 0` |
| `bool` | scalar bool | `enabled: true` |
| `datetime` | ISO 8601 string | `created_at: '2026-05-16T12:34:56+00:00'` |
| `json` | mapping or sequence | `settings: { foo: 1, bar: [a, b] }` |
| `text` | scalar (block scalar if multi-line) | `description: \|\n  Multi-line\n  description.` |
| `uuid` | scalar string | `target_uuid: 0193-...` |
| `entity_reference` | `<entity_type>.<entity_id>` string | `parent: taxonomy_vocabulary.root` |
| `field_list` | sequence of scalars | `permissions:\n  - edit content` |

The table itself is stable. New field types extend additively; removals or
renames follow the §4 deprecation cycle.

---

## Invariant 4 — Determinism rules

### 4.1 Alphabetical key ordering

- Within `_meta`: sorted alphabetically.
- Within the top-level field group: sorted alphabetically.
- Within nested mappings (e.g. `json` fields): sorted alphabetically.

This is the most important invariant. Re-exporting unchanged config MUST
produce byte-identical YAML; otherwise git diffs become noise and PR reviews
collapse.

### 4.2 Block-scalar shape for multi-line strings

Multi-line strings use YAML's literal (`|`) or folded (`>`) block scalars:

```yaml
description: |
  Line one.
  Line two.
```

Single-line strings use plain or quoted scalars per YAML's default
representation choices.

### 4.3 Empty containers

Empty arrays and maps serialize as `[]` and `{}` — flow style — to keep
diffs compact:

```yaml
_meta:
  dependencies: []
  ...
settings: {}
```

### 4.4 No trailing whitespace, single trailing newline

Files end with exactly one `\n`. Trailing whitespace on lines is forbidden
(the serializer strips it; if you author by hand and CI complains, it's
because you broke this rule).

### 4.5 UTF-8 with no BOM

The format is UTF-8 throughout. No byte-order mark.

---

## Invariant 5 — UUID-tracked renames

When an entity is renamed (new `entity_id`, same `uuid`), the operator
manually:

1. Renames the sync file: `git mv role.editor.yml role.site_editor.yml`.
2. Updates `_meta.entity_type` only if the entity type itself changed (rare).
3. Edits the entity-id portion of any file's `_meta.dependencies` array
   that referenced the old id (search-and-replace by id string).

The next `config:diff` shows the rename rather than a delete+create pair
because `ConfigDiffer` compares by `_meta.uuid`. The next `config:import`
applies it transactionally.

If you forget step 3, `config:validate` and `config:import` raise
`ConfigDependencyMissingException` naming the stale id — fix the references
and retry.

---

## Invariant 6 — Forbidden patterns

The format actively prevents these failure modes:

| Pattern | Why forbidden |
|---|---|
| Per-environment sync stores (`config-sync-staging/`, `config-sync-prod/`) | Re-introduces drift CMI was built to eliminate. Use env vars in `config/waaseyaa.php` instead. |
| Embedding secrets in any field value | The sync store is git-tracked. Use env vars. See [`docs/specs/security-defaults.md`](../specs/security-defaults.md). |
| Hand-editing `_meta.uuid` | Breaks rename detection. UUIDs are generated by `config:export`; don't touch. |
| Manually unsorting keys for "readability" | Breaks determinism; the next `config:export` will revert your ordering. |
| Adding non-`_meta` non-field top-level keys | The serializer ignores them on round-trip; they vanish silently. |

---

## Invariant 7 — Round-trip preservation

For any active config entity that hasn't been edited:

```bash
bin/waaseyaa config:export
git status storage/config-sync/
# (clean — no changes)
```

This is the round-trip property: `export → import → export` produces
byte-identical output. M-003 WP10 tests this end-to-end against Minoo's
config entities.

If round-trip ever breaks, **stop and fix the serializer**. The integrity of
every operator's git history depends on it.

---

## Pointers

- Spec: [`docs/specs/config-management.md`](../specs/config-management.md).
- Cookbook: [`docs/cookbook/config-sync.md`](../cookbook/config-sync.md).
- Charter: [`docs/specs/stability-charter.md`](../specs/stability-charter.md) §5.5.
- ADR: [`docs/adr/018-configuration-management-sync.md`](../adr/018-configuration-management-sync.md).
- Mission archive: [`kitty-specs/config-management-v1-01KRCDEC/`](../../kitty-specs/config-management-v1-01KRCDEC/).
