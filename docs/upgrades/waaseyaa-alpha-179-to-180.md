# Upgrade Guide: waaseyaa alpha.179 → alpha.180

**Released:** 2026-05-16
**Migration effort:** small (additive only; no existing call sites change)
**Required for:** apps that intend to manage configuration across environments via the new CMI substrate; apps with custom config entities should audit `BackendRestrictionEnforcer` boot-time gates; extension authors using `config:*` CLI verbs must rename to non-reserved namespaces if they collide.

---

## Summary

Mission `config-management-v1-01KRCDEC` (M-003) lands the **Drupal-shape
Configuration Management substrate** — an active/sync store split for
multi-environment configuration promotion. It ships:

- `ConfigDependencyInterface` + DAG ordering for dependency-aware import
- A deterministic sync-store YAML format under `storage/config-sync/`
- Six CLI commands on the reserved `config:*` namespace
  (`export`, `import`, `diff`, `status`, `validate`, `reset`)
- `config.audit` log channel for import/export/reset events
- Boot-time `BackendRestrictionEnforcer` (`sql-blob` / `sql-column` only)
- `config:*` namespace reservation with fail-fast collision detection
- A stable error model (six exception classes carrying §4.4 codes)

There are **no breaking changes**. The mission is purely additive — apps
that don't use sync-store promotion see no behavioural change.

The new stable surface is canonicalised in
[`docs/specs/stability-charter.md`](../specs/stability-charter.md) §5.5, the
subsystem spec lives at
[`docs/specs/config-management.md`](../specs/config-management.md), and the
operator cookbook is
[`docs/cookbook/config-sync.md`](../cookbook/config-sync.md).

**Beta-gate criterion 9** (charter §3.2) — Drupal-comparison-matrix §3.5 (CMI)
— flips from `unshipped` to **SATISFIED** by this mission.

---

## Breaking changes

None.

---

## Deprecations

None.

---

## New stable surface

Full enumeration in charter §5.5. Highlights below; see
[`docs/specs/config-management.md`](../specs/config-management.md) §10 for
the complete tier map.

### Dependency declarations

| FQCN | Purpose |
|---|---|
| `Waaseyaa\Config\Dependency\ConfigDependencyInterface` | Config-entity contract — `configDependencies(): string[]` returns `<entity_type>.<entity_id>` ids consumed by the DAG. Default no-op on `ConfigEntityBase`. |
| `Waaseyaa\Config\Dependency\Exception\ConfigDependencyCycleException` | Raised when the sync-store DAG contains a cycle; carries the full cycle path. |
| `Waaseyaa\Config\Dependency\Exception\ConfigDependencyMissingException` | Raised when a `_meta.dependencies` entry references an id absent from both stores. |

### Sync-store format I/O

| FQCN | Purpose |
|---|---|
| `Waaseyaa\Config\Sync\ConfigSyncFile` | Parsed sync file (in-memory value object). |
| `Waaseyaa\Config\Sync\ConfigSyncSerializer` | Entity → YAML; alphabetical key order; leading `_meta` block. |
| `Waaseyaa\Config\Sync\ConfigSyncDeserializer` | YAML → `ConfigSyncFile`; enforces `_meta.entity_type` ↔ filename match. |
| `Waaseyaa\Config\Sync\ConfigSyncRepository` | Filesystem read/write under `config.sync_path`. |
| `Waaseyaa\Config\Sync\ConfigSyncFileSourceInterface` | Extension point for alternative sync sources (test fixtures, in-memory drivers). |
| `Waaseyaa\Config\Sync\ConfigManifestEntry` | Per-entity manifest row. |

### Orchestrators

| FQCN | Purpose |
|---|---|
| `Waaseyaa\Config\Sync\ConfigExporter` | Active → sync (backs `config:export`). |
| `Waaseyaa\Config\Sync\ConfigImporter` | Sync → active, DAG-ordered, per-entity transaction. |
| `Waaseyaa\Config\Sync\ConfigImportApplyHookInterface` | Per-applied-entity hook (extension point). |
| `Waaseyaa\Config\Sync\ConfigDiffer` | Unified-diff renderer; UUID-tracked rename detection. |
| `Waaseyaa\Config\Sync\ConfigStatusReporter` | in-sync / drift / sync-only / active-only counts. |
| `Waaseyaa\Config\Sync\ConfigSyncValidator` | Runs `FieldDefinition::validators()` over each sync file. |
| `Waaseyaa\Config\Sync\ConfigResetter` | Single-entity rollback from sync; logs to `config.audit`. |

### Audit log channel

| Symbol | Purpose |
|---|---|
| `Waaseyaa\Config\Audit\ConfigAuditChannel::CHANNEL` | String constant `'config.audit'`. Charter §4.4 amendment. |
| `Waaseyaa\Config\Audit\ConfigAuditEvent` | Event payload (entity-type, id, operation, actor, before-after summary). |

### Backend restriction

| FQCN | Purpose |
|---|---|
| `Waaseyaa\Config\Backend\BackendRestrictionEnforcer` | Boot-time guard. `ALLOWED_BACKEND_IDS = ['sql-blob', 'sql-column']`. |
| `Waaseyaa\Config\Exception\InvalidConfigBackendException` | Carries entity-type id, disallowed backend id, declarer FQCN. |

### CLI commands (under `Waaseyaa\CLI\Command\Config\`)

| Command | Class |
|---|---|
| `bin/waaseyaa config:export [--diff] [--dry-run]` | `ConfigExportCommand` |
| `bin/waaseyaa config:import [--dry-run] [--delete-orphans] [--halt-on-error] [--no-dependency-check]` | `ConfigImportCommand` |
| `bin/waaseyaa config:diff [<entity-type>.<id>]` | `ConfigDiffCommand` |
| `bin/waaseyaa config:status [--format=plain|json]` | `ConfigStatusCommand` |
| `bin/waaseyaa config:validate` | `ConfigValidateCommand` |
| `bin/waaseyaa config:reset <entity-type>.<id> [--yes]` | `ConfigResetCommand` |

`Waaseyaa\CLI\Command\Config\ConfigCommand` is the abstract base; it exposes
`RESERVED_VERBS`, `RESERVED_FULL_VERBS`, and `RESERVED_FQCNS` constants
consumed by the boot-time collision check.

### Exception classes

| FQCN | Raised when |
|---|---|
| `Waaseyaa\Config\Exception\InvalidConfigBackendException` | Config entity declares non-`sql-blob` / non-`sql-column` backend. |
| `Waaseyaa\Config\Exception\ConfigSerializationException` | `_meta.entity_type` mismatch or other YAML format error. |
| `Waaseyaa\Config\Exception\ConfigImportFailedException` | Per-entity error during `config:import`. |
| `Waaseyaa\Config\Exception\ConfigCommandCollisionException` | App command claims a reserved `config:*` sub-verb at boot. |

### Config keys

| Key | Default | Purpose |
|---|---|---|
| `config.sync_path` | `storage/config-sync/` | Root path for sync-store files; resolved relative to project root. |

---

## Migration steps for consumer apps

If your app intends to **use** CMI for environment promotion:

1. Confirm the substrate ships:

   ```bash
   bin/waaseyaa list | grep config:
   # Should list six commands (export, import, diff, status, validate, reset)
   ```

2. Create and git-track the sync store:

   ```bash
   mkdir -p storage/config-sync
   touch storage/config-sync/.gitkeep
   git add storage/config-sync
   git commit -m "chore: add config sync store"
   ```

3. Export your current active store to seed the sync directory:

   ```bash
   bin/waaseyaa config:export
   git add storage/config-sync/
   git commit -m "chore: seed config sync store from active store"
   ```

4. Wire validation into CI:

   ```yaml
   - run: bin/waaseyaa config:validate
   ```

5. Adopt the per-environment-overrides pattern (env vars in
   `config/waaseyaa.php`, NOT per-env sync stores) — see
   [`docs/cookbook/config-sync.md`](../cookbook/config-sync.md) §10.

If your app does **not** intend to use sync-store promotion: no action
required. The substrate is opt-in. CMI does not modify existing
`ConfigEntityBase` consumers.

### One caveat — backend restriction is enforced at boot

If your app declares a **custom config entity type** with a backend OTHER
than `sql-blob` or `sql-column`, kernel boot will fail with
`InvalidConfigBackendException` after this upgrade. The remediation is to
migrate the entity to a permitted backend (see ADR 010) — config entities
fundamentally cannot live on `vector` or `remote` backends because CMI
requires deterministic, queryable serialization.

### One caveat — `config:*` CLI namespace is reserved

If your app or any installed extension registers a CLI command whose name
matches any of the six reserved sub-verbs (`config:export`, `config:import`,
`config:diff`, `config:status`, `config:validate`, `config:reset`), kernel
boot will fail with `ConfigCommandCollisionException`. The remediation is to
rename your command — apps may freely use any `config:<custom>` verb that is
NOT in the reserved set (e.g. `config:audit-export`, `config:snapshot`).

---

## Backward compatibility

Fully additive:

- Existing `ConfigEntityBase` consumers see no behavioural change.
- `ConfigDependencyInterface` has a default no-op implementation on
  `ConfigEntityBase`; entity types that don't declare dependencies behave as
  if they returned `[]`.
- No existing class signatures change.
- The `config.audit` channel is new — non-aware log handlers ignore it.

---

## Smoke test

```bash
# Verify the substrate is discovered
bin/waaseyaa list | grep config:
# Expect six lines

# Verify config:status works against an empty sync store
bin/waaseyaa config:status
# Expect: "0 in-sync, N drift, 0 sync-only, N active-only" (N = your entity count)

# Verify round-trip preservation
bin/waaseyaa config:export
git status storage/config-sync/    # initial export — all files new
git add storage/config-sync/
git commit -m "test: initial config export"
bin/waaseyaa config:export
git status storage/config-sync/    # second export — clean working tree
```

---

## Common questions

### Do I need to write `ConfigDependencyInterface::configDependencies()` on every config entity?

No. `ConfigEntityBase` provides a default no-op implementation that returns
`[]`. Override only when your entity actually depends on another (e.g.
`role.editor` depends on `permission_set.content_editing`).

### Where do per-environment values live?

In env vars consumed by `config/waaseyaa.php`. **Not** in
per-environment sync stores. The cookbook §10 walks through the pattern.

### Can I extend the sync-store format with custom `_meta` fields?

Not in this alpha. The four-key `_meta` vocabulary (`dependencies`,
`entity_type`, `langcode`, `uuid`) is the entire stable surface as of
M-003. Additive new keys may land in future trains under the §4 cycle.

### What about config translation (per-langcode config entities)?

Out of scope for M-003. The `_meta.langcode` field exists for forward
compatibility; every shipped config entity defaults to `en`. A future ADR
will bridge ADR 017 (per-field translation) and ADR 018 (CMI).

---

## Release notes pointer

- CHANGELOG: see `[Unreleased]` (M-003 bullet, `waaseyaa/config` +
  `waaseyaa/cli`).
- Charter: `docs/specs/stability-charter.md` §5.5 (CMI sync substrate) and
  §3.2 criterion 9 (CMI gap → SATISFIED).
- Spec (canonical): `docs/specs/config-management.md`.
- Conventions: `docs/conventions/cmi-sync-format.md`.
- Cookbook: `docs/cookbook/config-sync.md`.
- ADR: `docs/adr/018-configuration-management-sync.md`.
- Mission archive: `kitty-specs/config-management-v1-01KRCDEC/`.
