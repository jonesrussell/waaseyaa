# Contract: `config:*` CLI Namespace

**Stability scope:** charter §5.5 (amended at mission close)
**FRs covered:** FR-017..FR-049
**Owned by:** WP03 (export), WP04 (import), WP05 (diff + status), WP06 (validate), WP07 (reset), WP09 (namespace reservation)

## Reserved verb namespace

The framework reserves the following six verb names framework-side:

| Reserved verb | Class | Purpose |
|---|---|---|
| `config:export` | `ConfigExportCommand` | active → sync write |
| `config:import` | `ConfigImportCommand` | sync → active write (DAG order) |
| `config:diff` | `ConfigDiffCommand` | unified-diff sync vs active |
| `config:status` | `ConfigStatusCommand` | summary counts + per-entity table |
| `config:validate` | `ConfigValidateCommand` | runs `FieldDefinition::validators()` over sync YAML |
| `config:reset` | `ConfigResetCommand` | single-entity reset to sync-store value |

The list lives at `Waaseyaa\Cli\Command\Config\ConfigCommand::RESERVED_VERBS`. `CliKernel`'s boot-time command-registration hook reads it.

**Reservation enforcement (FR-048):** App or extension code registering a command whose name equals any reserved verb AND whose class does not extend `ConfigCommand` causes `CliKernel` to throw `ConfigCommandCollisionException` and refuse to boot. The exception carries:

- `reservedVerb` — the colliding verb.
- `offendingFqcn` — the FQCN of the colliding command class.
- `code` — stable string `'config.cli.collision'`.

**Permitted app extensions (FR-049):** Apps MAY register `config:<custom>` verbs that are NOT in the reserved set. Examples: `config:audit-export`, `config:lint`, `config:rebuild-cache`. They own those entirely; the framework does not pre-empt the broader `config:` prefix.

## Per-command interface

### `bin/waaseyaa config:export [--diff] [--dry-run]`

**Behaviour (FR-017..FR-021):**
1. Enumerate every config entity in the active store via `ConfigManagerInterface::listAllEntityTypes()` + `listEntitiesOfType()`.
2. Serialize each entity to a `ConfigSyncFile` via `ConfigSyncSerializer::serialize()`.
3. Write each via `ConfigSyncRepository::put()`.

**Flags:**
- `--diff` — write only when the new YAML content differs from the existing sync file. Unchanged files are not touched (preserves git's mtime-aware diff semantics).
- `--dry-run` — compute writes without filesystem effect; print a summary of what would change.

**Output:**
- Per-file line: `created storage/config-sync/role.admin.yml` (or `updated`, or `unchanged`).
- Summary line: `X created, Y updated, Z unchanged.` (FR-020).

**Exit codes:**
- `0` — success.
- `1` — any serialization error.

**Audit log entry (`config.audit` channel):**
```
[info] config:export actor=<user> sync_path=<path> created=X updated=Y unchanged=Z dry_run=<bool>
```

### `bin/waaseyaa config:import [--dry-run] [--delete-orphans] [--halt-on-error] [--no-dependency-check]`

**Behaviour (FR-022..FR-029):**
1. Enumerate every sync file via `ConfigSyncRepository::list()`.
2. Validate every file via `ConfigSyncValidator::validate()` UNLESS `--no-dependency-check`. Validation failures block import.
3. Build the dependency graph via `DependencyResolver::resolve()` UNLESS `--no-dependency-check`. Cycles / missing deps raise typed exceptions.
4. Apply files in topological order; each entity in its own transaction.
5. Display per-entity diff for changes when STDOUT is a TTY; suppress in CI.
6. Orphan handling: by default warn (log to `config.audit`, do not delete). `--delete-orphans` opts into deletion.
7. Continue on per-entity error unless `--halt-on-error`.

**Flags:**
- `--dry-run` — compute would-be writes; no DB mutation.
- `--delete-orphans` — delete active-store entities with no matching sync-store file.
- `--halt-on-error` — stop after first per-entity failure.
- `--no-dependency-check` — emergency bypass; skip validation AND DAG.

**Output:**
- Per-entity line: `imported role.admin (updated)` or `failed taxonomy_vocabulary.foo: <reason>`.
- Summary line: `N created, M updated, K deleted, J failed, P unchanged.` (per spec §7.2).

**Exit codes:**
- `0` — `J failed === 0`.
- `1` — any `J failed > 0`, or validation failure pre-import.

**Audit log entries** for the run summary + each per-entity failure + each `--no-dependency-check` use (warning level).

### `bin/waaseyaa config:diff [<entity-type>.<id>]`

**Behaviour (FR-030..FR-033):**
1. Without argument: enumerate every ref in either store; compute `DiffResult` per ref via `ConfigDiffer::diff(null)`.
2. With argument: scope to one ref via `ConfigDiffer::diff($ref)`.
3. For each result, render a unified diff of YAML (sync side = `---`, active side = `+++`).
4. Render rename annotation when `DiffResult::$status === STATUS_RENAMED`.

**Output:** standard unified diff text per entity, with `---`/`+++` headers showing both sides. UUID-tracked renames render as:

```
=== renamed: role.coordinator → role.community_coordinator (uuid: 0193ab…) ===
```

**Exit codes:**
- `0` — no differences (either no diffs found, or only `STATUS_IN_SYNC` results).
- `1` — any drift / sync-only / active-only / renamed result.

### `bin/waaseyaa config:status [--format=plain|json]`

**Behaviour (FR-034..FR-036):**
1. Compute `StatusReport` via `ConfigStatusReporter::status()`.
2. Print counts: `in-sync / drift / sync-only / active-only / renamed`.
3. When STDOUT is a TTY AND total ref count < 50, print per-entity table grouped by entity type.
4. With `--format=json`, emit machine-parseable JSON regardless of TTY:
   ```json
   {
     "counts": {"in_sync": 12, "drift": 3, "sync_only": 0, "active_only": 1, "renamed": 0},
     "entries": [
       {"ref": "role.admin", "status": "in_sync"},
       {"ref": "role.coordinator", "status": "drift"},
       ...
     ]
   }
   ```
5. Read-only; no side effects on either store.

**Exit codes:**
- `0` — always (status is informational). Use `config:diff` exit code for CI gating.

### `bin/waaseyaa config:validate`

**Behaviour (FR-037..FR-040):**
1. Enumerate every sync file.
2. For each, instantiate the would-be entity via `ConfigSyncDeserializer::toEntity()`.
3. Run `FieldDefinition::validators()` on each field.
4. Output per-entity errors with per-field detail.

**Output:** per-entity validation report:
```
role.admin: OK
taxonomy_vocabulary.community_categories:
  - field 'description': must be at least 1 character
  - field 'weight': must be non-negative
```

**Exit codes:**
- `0` — every entity valid.
- `1` — any entity invalid.

**CI usage:** `config:validate` is the recommended deploy-time gate before `config:import`. A failed validate is much cheaper than a half-applied import.

### `bin/waaseyaa config:reset <entity-type>.<id> [--yes]`

**Behaviour (FR-041..FR-043):**
1. Confirm with the operator (skip if `--yes`).
2. Read the sync file via `ConfigSyncRepository::get($ref)`.
3. Overwrite the active-store entity via `ConfigManagerInterface::saveEntity()` inside a transaction.
4. Log to `config.audit` with before/after summary.

**Confirmation behaviour:**
- Interactive (TTY, no `--yes`): prompt `"Reset role.admin from sync store? [y/N]"`. Accept `y` / `Y` / `yes` / `Yes`; anything else aborts (exit 0; no-op).
- Non-interactive (no TTY, no `--yes`): refuse to proceed; exit 1 with message `"Refusing to reset without --yes flag in non-interactive mode."` Never hang waiting for input.
- `--yes`: skip the prompt entirely.

**Output:** confirmation echo + audit log entry.

**Exit codes:**
- `0` — reset applied (or aborted by user with `n`).
- `1` — entity not found in sync store, or non-interactive without `--yes`.

## Cross-command conventions

- **Working directory:** all commands resolve `config.sync_path` relative to the project root, NOT the current working directory. Operators may invoke from any directory.
- **Color output:** all commands respect `NO_COLOR` and `--no-ansi`. Default behaviour is auto-detect (TTY = color, pipe = no color).
- **`--quiet` / `--verbose`:** standard Symfony Console verbosity flags supported throughout.
- **Help output:** each command's `--help` documents its flags, exit codes, and links to `docs/cookbook/config-sync.md`.
- **Logging:** every audit-worthy operation logs to `config.audit` channel via `LoggerInterface::info()` (or `warning` for `--no-dependency-check`). Operators route this channel to dedicated retention.

## Versioning of the CLI surface

The six verb names + the documented flags are on stable surface from v0.x. Flag additions are permitted (additive); flag removals require charter §4 deprecation cycle. Exit-code semantics are stable; CI scripts may rely on `0` / `1` distinction.

The `--format=json` output schema for `config:status` is on stable surface. Field additions are permitted; field renames or removals require deprecation cycle.
