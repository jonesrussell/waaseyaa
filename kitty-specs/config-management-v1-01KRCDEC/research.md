# Research: Configuration Management v1

**Phase:** 0 (research)
**Mission:** M-003 / `config-management-v1-01KRCDEC`
**Date:** 2026-05-16

## Open questions resolved

### R-01 — Drupal CMI patterns reviewed

**Decision:** Mirror Drupal CMI's three-layer model verbatim — active store (DB), sync store (filesystem YAML), CLI verbs over both. The `_meta` block in each sync file carries entity-type, UUID, dependencies, and langcode; remaining keys are field values keyed alphabetically.

**Rationale:** Drupal CMI has ~10 years of operational refinement. The shape solves problems Waaseyaa hasn't seen yet but will hit (rename-via-UUID-preservation, cycle handling in dependency graphs, per-environment promotion via git). Reinventing the shape would invite the same lessons in production. The pattern that fits is well-known and battle-tested.

**Operative deltas from Drupal:**
- No `core.extension.yml` analogue. Waaseyaa's package manifest (compiled at boot) plays that role.
- No admin UI for sync (CLI-only in v0.x — spec §1.2 non-goal).
- Orphan-default is **warn**, not delete (safer for early operators).
- Per-environment overrides deferred to a future ADR (Drupal `$config['x']['y']` runtime overrides not implemented).

**Alternatives considered:**
- Laravel-shape config-as-code only — rejected (would forfeit the admin-editable config model that drives CMS / community consumers).
- WordPress-style "no sync mechanism; environments diverge" — rejected (the spec exists specifically because that pattern doesn't scale beyond single-env apps).

### R-02 — Existing `ConfigEntityBase` surface analysis

**Decision:** Add `ConfigDependencyInterface` as a NEW stable surface. Provide a default no-op implementation on `ConfigEntityBase` returning `[]`. Existing config entity classes (e.g. `Role`, `Permission`, `Vocabulary` in apps) continue compiling unchanged; only entities that genuinely declare dependencies override.

**Rationale:** Spec §3.1 FR-003 mandates a default no-op so the mission is fully additive — zero breaking change to consumers. The existing `Waaseyaa\Config\ConfigEntityBase` already carries the `id`, `uuid`, `langcode` fields needed for the `_meta` block; no new properties on the base class.

**Surface audit findings:**
- `Waaseyaa\Config\ConfigEntityBase` exposes `getId()`, `uuid()`, `langcode()` — sufficient for `_meta` block construction.
- `Waaseyaa\Config\ConfigManifest` already exists for in-memory manifest needs; CMI's filesystem manifest entries live separately as `ConfigManifestEntry` value objects to avoid conflating in-memory active manifest with on-disk sync manifest entries.
- Existing `Waaseyaa\Config\Storage\StorageInterface` operates on the active store only. The sync store gets a NEW `ConfigSyncRepository` (filesystem-only) on a distinct contract — they do not share an abstraction.

**No conflict** with existing `ConfigFactoryInterface` / `ConfigManagerInterface`; CMI sits beside them as a new sync-layer surface.

**Alternatives considered:**
- Hang dependency-declaration off `ConfigEntityBase` via a property — rejected; interface is more discoverable and intent-revealing.
- Auto-derive dependencies from field references — rejected for v0.x; explicit declaration matches Drupal and keeps the graph stable across refactors.

### R-03 — CLI namespace decisions

**Decision:** Six commands on the reserved `config:*` verb namespace:

| Verb | Class | Purpose | Stable since |
|---|---|---|---|
| `config:export` | `ConfigExportCommand` | active → sync write | v0.x (this mission) |
| `config:import` | `ConfigImportCommand` | sync → active write (DAG order) | v0.x |
| `config:diff` | `ConfigDiffCommand` | unified-diff sync vs active | v0.x |
| `config:status` | `ConfigStatusCommand` | summary counts + per-entity table | v0.x |
| `config:validate` | `ConfigValidateCommand` | runs `FieldDefinition::validators()` over sync YAML | v0.x |
| `config:reset` | `ConfigResetCommand` | single-entity reset to sync-store value | v0.x |

Apps registering any of these six exact verbs fail at boot via `ConfigCommandCollisionException` (FR-048). Apps MAY register `config:<custom>` verbs (e.g. `config:audit-export`) that are NOT in the reserved set — they own those (FR-049).

**Rationale:** Six is the minimal complete set Drupal proved sufficient. The verb names match Drupal's `drush config:*` semantics so muscle-memory transfers. Reservation lives in `ConfigCommand` (a base class in `packages/cli/src/Command/Config/`) which exposes a `RESERVED_VERBS` constant; the `CliKernel`'s boot-time command-registration check reads it.

**Alternatives considered:**
- Subcommand under a single `config` group (Symfony Console nesting) — rejected; flat verb namespace matches existing Waaseyaa CLI conventions (`bin/waaseyaa migrate:up`, `bin/waaseyaa schema:check`).
- Allow apps to register any `config:*` verb without collision-checking — rejected; collisions in this namespace are correctness bugs (operator types `config:import`, gets the wrong command).

### R-04 — Dependency-graph ordering for deterministic import

**Decision:** Topological sort via depth-first search. Tie-break by lexicographic entity-id (`<entity_type>.<entity_id>`) when multiple nodes have no remaining incoming edges. Cycle detection uses the standard DFS coloring (white/gray/black); raising `ConfigDependencyCycleException` with the full cycle path as a `list<string>`.

**Rationale:** Deterministic ordering matters for two reasons:
1. **Failure-mode reproduction.** When an import fails on entity X, the operator needs to know X was imported in the same order it would be on a re-run. Non-deterministic ordering would mean different failure modes on retry.
2. **Snapshot testability.** Contract tests assert "given graph G, ordering O" — that's only stable with a deterministic tie-break.

Lexicographic tie-break is the canonical choice (Drupal uses it; trivial to implement; no dependency on insertion order).

**Cycle-path rendering:** `ConfigDependencyCycleException::getCycle()` returns the full path; `getMessage()` truncates to 5 hops + `…` for console / log readability. Test access uses `getCycle()`.

**Alternatives considered:**
- Kahn's algorithm — equivalent topology; DFS chosen for clearer cycle-path reconstruction (the gray-node-on-revisit gives the path directly).
- Insertion-order tie-break — rejected; depends on file-system enumeration order which is not portable across OSes.

### R-05 — Active/sync split interaction with multi-environment promotion

**Decision:** CMI is **environment-agnostic**. The same sync store applies to dev / staging / prod. Per-environment values (API keys, mail-from addresses, feature toggles) go through env vars read by `config/waaseyaa.php`, NOT through the sync store. Operators who hit "feature X enabled in staging only" are directed prominently in the cookbook to the env-var pattern.

**Rationale:** ADR 018 explicitly defers per-environment **runtime config-store overrides** (the Drupal `$config['x']['y']`-style mechanism) to a future ADR. v0.x ships the sync layer only. Operators with env-specific needs use:

```php
// config/waaseyaa.php
'mail' => [
    'from' => env('MAIL_FROM', 'noreply@example.com'),
    'sendgrid_key' => env('SENDGRID_API_KEY'),
],
'features' => [
    'experimental_panel' => env('FEATURE_EXPERIMENTAL_PANEL', false),
],
```

If a future ADR adds runtime overrides, this mission's surface is **unaffected** — overrides are a parallel mechanism that reads from the active store at request time, not a sync-store extension.

**Documentation imperative:** the cookbook (WP11) must lead with the env-var pattern so operators do not roll their own per-env sync stores when they hit this friction. FR-061 enforces this.

**Alternatives considered:**
- Ship per-env-sync subdirectories (`config-sync/staging/`, `config-sync/prod/`) — rejected; multiplies the surface, fragments cache keys, and recreates the problem ADR 018 explicitly defers.
- Ship config inheritance from a "base" sync store + per-env overlay — rejected; overlay semantics are subtle and out of scope for v0.x.

### R-06 — Compatibility with future `sql-column` config entity opt-in (entity-storage-v2 coordination point)

**Decision:** CMI's serialization is **field-definition-driven**, not backend-driven. A config entity migrated from `sql-blob` to `sql-column` (via entity-storage-v2's migration generator) continues to export/import identically — same `ConfigSyncFile` YAML, same dependency declarations, same validator pipeline. The two missions are independent.

**Rationale:** `FieldValueMapper` (WP02) reads the `FieldDefinition` to determine YAML representation per the type table in spec §5.3. The backend a field actually lives on (blob, column, future variants) is irrelevant at the YAML layer — `FieldDefinition` carries the typed-data type, and that type drives the mapping. Backend changes occur below the field abstraction.

**Coordination test:** WP10 adds a fixture entity migrated mid-test from `sql-blob` to `sql-column`; the export YAML is byte-identical before and after migration. This is the contract proof.

**Alternatives considered:**
- Backend-specific serializers — rejected; would force CMI to know about backends, violating the entity-storage abstraction. The whole point of `FieldDefinition` is to hide backend choice from consumers.
- Defer CMI until entity-storage-v2 ships — rejected; the missions are demonstrably independent. Config entities work today on `sql-blob`; CMI works today on `sql-blob`; entity-storage-v2 doesn't change either invariant.

## Naming / pattern reconciliation

### R-07 — Sync-store path conventions

**Decision:** Default sync path is `storage/config-sync/` resolved relative to the project root. Overridable via the `config.sync_path` key in `config/waaseyaa.php`. The directory is **git-tracked by convention**; consumer apps add it to their committed paths (FR-015).

**Rationale:** `storage/` already exists as the project-root convention for stateful runtime files (`storage/waaseyaa.sqlite`, `storage/cache/`). Co-locating the sync store keeps the operator mental model coherent: "stuff that matters to my deploy lives in `storage/`". The `-sync` suffix distinguishes it from `config/` (PHP code) and from any future `storage/config-cache/` (runtime artifacts).

### R-08 — `_meta` block schema choices

**Decision:** Top-level `_meta` block sorts before all field values; keys inside `_meta` sort alphabetically. The block carries exactly four keys: `entity_type`, `uuid`, `dependencies` (list), `langcode` (default `en` for non-translatable config). Additional `_meta` keys MUST follow the deprecation cycle (charter §4).

**Rationale:** Stable diffs require deterministic key ordering. Putting `_meta` first means the operator-facing top of every file shows the file's identity before its payload — the diff signal-to-noise is highest there.

**Future surface stability:** the `_meta` block is on stable surface (charter §5.5 amendment, FR-016). Adding new keys requires charter §4 deprecation cycle; removing keys is an explicit breaking change. The "remaining keys are field values" pattern means new field types extend the format without amending the spec.

### R-09 — Exception code conventions

**Decision:** Each new exception carries a stable string `code` field per charter §4.4:

| Exception | Code |
|---|---|
| `ConfigDependencyCycleException` | `config.dependency.cycle` |
| `ConfigDependencyMissingException` | `config.dependency.missing` |
| `InvalidConfigBackendException` | `config.backend.invalid` |
| `ConfigSerializationException` | `config.sync.serialization` |
| `ConfigImportFailedException` | `config.import.failed` |
| `ConfigCommandCollisionException` | `config.cli.collision` |

**Rationale:** Stable codes let operators grep logs and CI signal for specific failure modes without parsing exception messages. The dotted-namespace shape matches existing Waaseyaa convention (`waaseyaa.kernel.boot.failed`, etc.).

### R-10 — `config.audit` log channel placement

**Decision:** `config.audit` log channel is a new framework-side stable surface. The channel constant lives at `Waaseyaa\Config\Audit\ConfigAuditChannel::CHANNEL = 'config.audit'`. Charter §4.4 is amended in WP11 to list it.

**Rationale:** A dedicated channel separates configuration audit from general application logging — operators / SRE can route it to a long-retention sink (e.g. dedicated index, archival storage) without affecting other log streams. The channel name is stable surface because operator log routing depends on it.

**What gets logged:**
- Every `config:export` invocation (operation, actor, file count, summary line).
- Every `config:import` invocation (operation, actor, per-entity success/failure, final summary).
- Every `config:reset` invocation (operation, actor, entity ref, before/after diff summary).
- `--no-dependency-check` usage at warning level (emergency-bypass audit trail).

### R-11 — Deterministic UUID generation for legacy entities

**Decision:** When `config:export` encounters an active-store entity without a UUID (legacy pre-CMI), generate a deterministic UUID: `sha-256(entity_type + '.' + entity_id)`, truncated and re-shaped to UUID v5 format. The generated UUID is then written back to the active store on first export so subsequent operations see a stable UUID.

**Rationale:** Determinism guarantees that two environments computing UUIDs for the same logical entity arrive at the same UUID, which is the precondition for rename-detection across envs. Random UUIDs at first export would cause divergence on first deploy (dev's UUID for `role.admin` ≠ prod's UUID for `role.admin`), making rename detection unusable.

**Re-confirmation:** spec §10.3 names this as a recommendation; this research confirms and locks it in for WP02 implementation.

### R-12 — Stable surface vs internal classification

**Decision:** The following classes/interfaces are on **stable surface** (charter §5.5 amendment lists them):
- `ConfigDependencyInterface`
- Sync-store YAML format (`_meta` block + field-value mapping)
- `config.sync_path` config key
- `config.audit` log channel constant
- The six `bin/waaseyaa config:*` commands and their flags
- The six exception classes + their code strings
- Default sync path convention (`storage/config-sync/`)

**Internal (not stable surface):**
- `DependencyResolver`, `DependencyGraph`
- `ConfigSyncSerializer`, `ConfigSyncDeserializer`, `FieldValueMapper`
- `ConfigExporter`, `ConfigImporter`, `ConfigDiffer`, `ConfigStatusReporter`, `ConfigSyncValidator`, `ConfigResetter`
- `ConfigSyncRepository` (filesystem driver; replaceable in future without breaking the public surface)
- `ConfigManifestEntry`, `ConfigSyncFile`, `DiffResult`, `StatusReport`

**Rationale:** Interface segregation. The stable surface is the contract apps and operators see; the implementation classes are framework-internal and can evolve without ceremony. This lets us iterate on (e.g.) the diff renderer or status reporter without charter §4 deprecation cycles.

## Cross-mission impact summary

| Mission | Impact | Lands in |
|---|---|---|
| M-001 (entity-storage-v2) | Independent. Config entities may migrate to `sql-column` mid-life; CMI continues working byte-identically. | — |
| M-002 (migration-platform-v1) | None. Migrations are write paths on content; CMI is config-only. | — |
| M-004 (entity-storage-translatable-revisions) | Future ADR may bridge translatable revisions and CMI per-langcode config; out of scope here. | — |
| M-005 (waaseyaa/migrate-source-wordpress) | None. WordPress migration is content, not config. | — |
| M-006 (entity-storage-translations-v1) | None. CMI is langcode-aware (one langcode per file via `_meta.langcode`) but does not depend on entity-translation semantics. | — |
| M-007 (listing-pipeline-v1) | None. Config entities are not listing subjects. | — |
| Charter §3.2 criterion 9 (beta gate) | Cleared on ADR 018 acceptance; this mission's merge ships the implementation. | Mission close (WP10 + WP11) |
| Charter §5.5 (config/env) | Amended additively to list new stable surface. | WP11 |

## Stability-charter amendments authored (WP11)

Two amendments land at mission close:

1. **§5.5 amendment** — list new stable surface: `ConfigDependencyInterface`, sync-store YAML format, `config.sync_path` config key, `config.audit` log channel, the six `config:*` commands, the six exception classes and their codes, default sync path convention.
2. **§4.4 amendment** — add `config.audit` to the canonical log-channel constants table.

(Charter §3.2 criterion 9 status flip — from "📋 planned" to "✅ shipped" — is a status update, not an amendment.)

## Open data-model questions deferred to `data-model.md` / `contracts/`

- Exact constructor signature for `ConfigSyncFile` (parameter order, defaults, public readonly properties vs accessor methods).
- `DependencyGraph` internal representation — adjacency list `array<string, list<string>>` or a typed `Node`/`Edge` value-object pair?
- `ConfigImporter::import()` return shape — count summary, per-entity result list, or both?
- `ConfigDiffer::diff()` output — single string of unified diff, or `DiffResult` value object with sides + hunks for programmatic consumption?
- `ConfigStatusReporter::status()` return — `StatusReport` value object with `inSync`, `drift`, `syncOnly`, `activeOnly` arrays of entity refs, plus a per-type breakdown.
- `ConfigSyncRepository` interface — minimal CRUD (`list()`, `get($ref)`, `put($ref, $file)`, `delete($ref)`) or richer ergonomics (`byEntityType($type)`, `exists($ref)`)?

These are interface-detail decisions captured in `data-model.md` and the `contracts/` files in Phase 1.
