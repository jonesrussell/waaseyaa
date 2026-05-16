# Quickstart: Configuration Management v1

**Phase:** 1 (design)
**Mission:** M-003 / `config-management-v1-01KRCDEC`
**Date:** 2026-05-16

This walkthrough demonstrates a Waaseyaa app declaring a config entity with dependencies, exporting its config to the sync store, editing the sync YAML, importing back to the active store, and observing diff / status / reset behaviour. The flow mirrors what the final cookbook recipe (`docs/cookbook/config-sync.md`, authored in WP11) will document.

## Scenario

A community-platform app ships with two config entity types:
- `role` — site roles (`admin`, `coordinator`, `member`).
- `taxonomy_vocabulary` — content-classification vocabularies (`community_categories`, `event_types`).

The `taxonomy_vocabulary.event_types` entity depends on `taxonomy_vocabulary.community_categories` (e.g. event_types extends community_categories as a child taxonomy). The app deploys dev → staging → prod and wants admin-defined config to promote cleanly.

## 1. Install / configure

Default sync path is `storage/config-sync/`. To override, set in `config/waaseyaa.php`:

```php
return [
    'config' => [
        'sync_path' => __DIR__ . '/../storage/config-sync',
    ],
    // ...
];
```

Add the sync directory to git:

```bash
echo "!storage/config-sync/" >> .gitignore       # ensure NOT ignored
git add storage/config-sync/.gitkeep             # placeholder until first export
git commit -m "chore: prepare config sync store"
```

## 2. Declare a config entity with dependencies

```php
namespace App\Entity;

use Waaseyaa\Config\ConfigEntityBase;
use Waaseyaa\Config\Dependency\ConfigDependencyInterface;

final class Vocabulary extends ConfigEntityBase implements ConfigDependencyInterface
{
    public function __construct(array $values = [])
    {
        parent::__construct(
            $values,
            'taxonomy_vocabulary',
            ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
        );
    }

    public function configDependencies(): array
    {
        $parent = $this->get('parent');
        return $parent !== null ? ["taxonomy_vocabulary.$parent"] : [];
    }
}
```

Entities that have no config dependencies (e.g. `Role`) don't need to implement `ConfigDependencyInterface` — `ConfigEntityBase` provides the default no-op returning `[]`.

## 3. Initial export

After the app boots and creates its initial entities (via fixture, admin UI, or migration), run:

```bash
$ bin/waaseyaa config:export
created storage/config-sync/role.admin.yml
created storage/config-sync/role.coordinator.yml
created storage/config-sync/role.member.yml
created storage/config-sync/taxonomy_vocabulary.community_categories.yml
created storage/config-sync/taxonomy_vocabulary.event_types.yml
5 created, 0 updated, 0 unchanged.
```

Inspect one file:

```bash
$ cat storage/config-sync/taxonomy_vocabulary.event_types.yml
```

```yaml
_meta:
  dependencies:
    - taxonomy_vocabulary.community_categories
  entity_type: taxonomy_vocabulary
  langcode: en
  uuid: 0193ce10-b8d2-7000-a3f4-5e6f7a8b9c0d
description: Categories for community events
id: event_types
label: Event Types
parent: community_categories
weight: 10
```

Commit:

```bash
$ git add storage/config-sync/
$ git commit -m "chore(config): initial export"
```

## 4. Edit the sync store, re-import

Edit `storage/config-sync/role.coordinator.yml` to grant a new permission:

```yaml
_meta:
  dependencies: []
  entity_type: role
  langcode: en
  uuid: 0193abcd-7c4d-7000-8b6e-1a2b3c4d5e6f
description: Coordinators manage community calendars.
id: coordinator
label: Coordinator
permissions:
  - calendar.administer
  - membership.approve
  - membership.invite
  - event.publish            # <-- NEW
weight: 10
```

Preview the import (no DB mutation):

```bash
$ bin/waaseyaa config:import --dry-run
Validating sync store... 5 OK.
Building dependency graph... 5 nodes, 1 edge, no cycles.
Would import (topological order):
  role.admin             (unchanged)
  role.coordinator       (updated)
    permissions: + event.publish
  role.member            (unchanged)
  taxonomy_vocabulary.community_categories  (unchanged)
  taxonomy_vocabulary.event_types           (unchanged)
0 created, 1 updated, 0 deleted, 0 failed, 4 unchanged.
```

Apply for real:

```bash
$ bin/waaseyaa config:import
Validating sync store... 5 OK.
Building dependency graph... 5 nodes, 1 edge, no cycles.
Importing:
  role.admin             (unchanged)
  role.coordinator       (updated)
  role.member            (unchanged)
  taxonomy_vocabulary.community_categories  (unchanged)
  taxonomy_vocabulary.event_types           (unchanged)
0 created, 1 updated, 0 deleted, 0 failed, 4 unchanged.
```

Verify no drift remains:

```bash
$ bin/waaseyaa config:diff
$ echo $?
0
```

## 5. Inspect drift between sync and active

Suppose an admin edits the coordinator role through the admin UI (active store), but the sync store has not been re-exported. `config:status` surfaces the drift:

```bash
$ bin/waaseyaa config:status
in_sync     : 4
drift       : 1
sync_only   : 0
active_only : 0
renamed     : 0

role:
  role.admin               in_sync
  role.coordinator         drift
  role.member              in_sync
taxonomy_vocabulary:
  taxonomy_vocabulary.community_categories  in_sync
  taxonomy_vocabulary.event_types           in_sync
```

`config:diff` shows the unified diff:

```bash
$ bin/waaseyaa config:diff role.coordinator
--- sync/role.coordinator.yml
+++ active/role.coordinator
@@ -7,7 +7,8 @@
 description: Coordinators manage community calendars.
 id: coordinator
 label: Coordinator
 permissions:
   - calendar.administer
   - membership.approve
   - membership.invite
+  - event.publish
 weight: 10
```

Decide: either re-export (active is the source of truth) or reset (sync is the source of truth).

```bash
# Promote active → sync:
$ bin/waaseyaa config:export --diff
updated storage/config-sync/role.coordinator.yml
0 created, 1 updated, 4 unchanged.

# OR rollback active → sync (restore from version control state):
$ bin/waaseyaa config:reset role.coordinator
Reset role.coordinator from sync store? [y/N] y
Reset role.coordinator (active store overwritten).
```

## 6. Failure modes

### Cycle detection

A misconfigured pair of entities that depend on each other:

```yaml
# storage/config-sync/taxonomy_vocabulary.foo.yml
_meta:
  dependencies: [taxonomy_vocabulary.bar]
  ...

# storage/config-sync/taxonomy_vocabulary.bar.yml
_meta:
  dependencies: [taxonomy_vocabulary.foo]
  ...
```

```bash
$ bin/waaseyaa config:import
Validating sync store... 5 OK.
Building dependency graph...

ERROR: ConfigDependencyCycleException (config.dependency.cycle)
  Config dependency cycle: taxonomy_vocabulary.foo → taxonomy_vocabulary.bar → taxonomy_vocabulary.foo
```

Exit code: `1`. The operator removes one side of the dependency declaration and re-runs.

### Missing dependency

A sync file references a config entity that does not exist anywhere:

```yaml
# storage/config-sync/menu.main.yml
_meta:
  dependencies: [taxonomy_vocabulary.nonexistent]
  ...
```

```bash
$ bin/waaseyaa config:import
ERROR: ConfigDependencyMissingException (config.dependency.missing)
  Config dependency 'taxonomy_vocabulary.nonexistent' required by 'menu.main' is not present in sync store or active store.
```

Exit code: `1`. Operator adds the missing config or removes the bad dependency declaration.

### Schema mismatch

A sync file's `_meta.entity_type` does not match the filename:

```yaml
# storage/config-sync/role.admin.yml
_meta:
  entity_type: taxonomy_vocabulary   # WRONG — filename says role
  ...
```

```bash
$ bin/waaseyaa config:validate
role.admin: FAIL
  - ConfigSerializationException (config.sync.serialization)
    Filename prefix 'role' does not match _meta.entity_type 'taxonomy_vocabulary'.
```

Exit code: `1`. Operator fixes the file by hand.

### Forbidden backend

A config entity declares its storage backend as `vector`:

```php
// boot fails:
ERROR: InvalidConfigBackendException (config.backend.invalid)
  Config entity 'event_types' uses backend 'vector'; only 'sql-blob' and 'sql-column' are permitted.
  Declaring class: App\Entity\Vocabulary
```

Kernel refuses to boot. The operator backs out the backend change and rebuilds the manifest.

### Reserved verb collision

An app registers a command named `config:import`:

```php
ERROR: ConfigCommandCollisionException (config.cli.collision)
  Reserved verb 'config:import' is owned by Waaseyaa\Cli\Command\Config\ConfigImportCommand.
  Offending class: App\Cli\MyCustomImporter
```

Kernel refuses to boot. The operator renames the app command to a non-reserved verb (e.g. `config:my-import`).

## 7. Operator cookbook (advance reading)

Three patterns the full cookbook (`docs/cookbook/config-sync.md`) will document in depth:

1. **Per-environment values via env vars** — `MAIL_FROM`, `SENDGRID_API_KEY`, `FEATURE_*` toggles. NOT per-env sync stores. Read in `config/waaseyaa.php`.
2. **CI deploy gate** — `config:validate` first; `config:import --halt-on-error` second; rollback recipe (revert sync-store commit + redeploy + import) third.
3. **Bad-import recovery** — `config:diff` to enumerate partial state; `config:reset <ref>` per entity; re-`config:import` once the cause is fixed.

## 8. What this mission ships

After WP01–WP11 land:

- `ConfigDependencyInterface` (stable surface)
- Sync-store YAML format with `_meta` block (stable surface)
- `config.sync_path` config key (stable surface)
- `config.audit` log channel (stable surface)
- Six `bin/waaseyaa config:*` commands (stable surface)
- Six exception classes with stable `code` strings (stable surface)
- Backend restriction enforced at boot (kernel refuses to boot on violation)
- `config:*` verb namespace reserved (boot fails on app collision)
- `docs/specs/config-management.md` (canonical doctrine spec)
- `docs/cookbook/config-sync.md` (operator guide)
- Charter §5.5 amendment listing the new stable surface
- Upgrade-guide entry for the alpha train shipping CMI

End-state: every Waaseyaa consumer can promote admin-defined config dev → staging → prod via `git push` + `config:import`.
