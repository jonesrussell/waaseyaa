# Cookbook: Configuration Sync (CMI)

**Audience:** application authors and operators who need multi-environment
configuration promotion (dev → staging → production) for Waaseyaa apps.
**Substrate:** `waaseyaa/config` + `waaseyaa/cli` (M-003).
**Spec:** [`docs/specs/config-management.md`](../specs/config-management.md).
**Charter:** [`stability-charter.md`](../specs/stability-charter.md) §5.5.
**Governing ADR:** [ADR 018](../adr/018-configuration-management-sync.md).

This guide walks the end-to-end CMI workflow:

1. Set up a sync store in a new app.
2. Export the active store to YAML.
3. Edit, diff, and validate before importing.
4. Import in dependency order.
5. Roll back a bad import.
6. **Per-environment overrides** — the load-bearing pattern operators must
   internalise.

By the end you'll have a `storage/config-sync/` directory you can commit to
git, a CI gate that validates sync files before deploy, and confidence that a
broken `config:import` can be unwound to a known-good state.

---

## Step 1 — Confirm the substrate is wired

CMI ships in `waaseyaa/config` (Layer 1) and `waaseyaa/cli` (Layer 6). If your
app uses `waaseyaa/framework` or `waaseyaa/cms`, both are already on your
classpath.

Verify:

```bash
# All six commands appear under bin/waaseyaa list
bin/waaseyaa list | grep config:
# Expected:
# config:diff
# config:export
# config:import
# config:reset
# config:status
# config:validate
```

If any are missing, run `bin/waaseyaa optimize:manifest` (or restart your dev
server) to refresh attribute discovery.

---

## Step 2 — Choose the sync-store location

The default is `storage/config-sync/` resolved relative to the project root.
You almost always want this committed to git so your team shares the same
canonical configuration.

`config/waaseyaa.php`:

```php
return [
    'config' => [
        // Default: 'storage/config-sync/'. Override only if you have a strong reason.
        'sync_path' => 'storage/config-sync/',
    ],
    // ...
];
```

Add the directory to your repository:

```bash
mkdir -p storage/config-sync
git add storage/config-sync/.gitkeep
git commit -m "chore: add config sync store"
```

> **Why git-track it?** The sync store is your declarative source of truth.
> A diff in `storage/config-sync/role.editor.yml` becomes a reviewable PR;
> the matching `config:import` after deploy promotes it to the active store.

---

## Step 3 — Export the active store

In development, edit config entities through the admin UI / API as usual.
When you're ready to promote, dump everything to YAML:

```bash
bin/waaseyaa config:export
# 12 created, 3 updated, 41 unchanged.
```

Each config entity becomes one file under `storage/config-sync/`:

```
storage/config-sync/
├── role.admin.yml
├── role.editor.yml
├── role.member.yml
├── taxonomy_vocabulary.tags.yml
├── taxonomy_vocabulary.community_categories.yml
└── …
```

Useful flags:

- `--diff` — write only files whose content would change (preserves git's
  mtime-aware diffing).
- `--dry-run` — preview the writes without touching the filesystem.

The output format is stable; CI consumers can grep for the summary line.

---

## Step 4 — Inspect with `config:status` and `config:diff`

Before pushing, see what's actually different:

```bash
bin/waaseyaa config:status
#       in-sync : 51
#         drift : 3
#     sync-only : 0
#   active-only : 0
#
#   role.editor                drift
#   taxonomy_vocabulary.tags   drift
#   menu.main                  drift
```

Drill into a specific entity:

```bash
bin/waaseyaa config:diff role.editor
# ─── active ───┐
# ┌── sync ───
# @@ -3,7 +3,7 @@
#    entity_type: role
#    langcode: en
#    uuid: 0193-abc-...
# -label: Editor
# +label: Site Editor
#  permissions:
#    - edit content
#    - publish content
```

Machine-parseable output for CI:

```bash
bin/waaseyaa config:status --format=json | jq .
```

`config:diff` exits non-zero when differences exist; wire it into a pre-deploy
guard to refuse drift you didn't intend.

---

## Step 5 — Validate before importing

`config:validate` runs the same `FieldDefinition::validators()` pipeline the
runtime uses, but against the sync files:

```bash
bin/waaseyaa config:validate
# role.editor                  ok
# taxonomy_vocabulary.tags     ok
# menu.main                    FAILED
#   - links[3].title: must not be empty
#   - links[3].url:   must be a valid path
# Exit: 1
```

Wire this into CI as a deploy gate:

```yaml
# .github/workflows/deploy.yml
- name: Validate config sync store
  run: bin/waaseyaa config:validate
```

A failed validate blocks the import. Don't let bad YAML reach staging.

---

## Step 6 — Import (the deploy step)

`config:import` walks the dependency graph (declared via
`ConfigDependencyInterface::configDependencies()`) and applies entities in
topological order. Each entity import is its own DB transaction.

Dry run on staging first:

```bash
bin/waaseyaa config:import --dry-run
# Would update: role.editor
# Would update: taxonomy_vocabulary.tags
# Would update: menu.main
# 3 entities would change.
```

Apply for real:

```bash
bin/waaseyaa config:import
# Importing role.editor… ok
# Importing taxonomy_vocabulary.tags… ok
# Importing menu.main… ok
# 0 created, 3 updated, 0 deleted, 0 failed, 51 unchanged.
```

### Flags worth knowing

| Flag | Default | Use |
|---|---|---|
| `--dry-run` | off | Preview without writes; safe to run anywhere. |
| `--delete-orphans` | off | Active-store entities with no matching sync file are deleted. Default is **warn-only** (see below). |
| `--halt-on-error` | off | Stop at the first per-entity failure. Default is to log and continue. |
| `--no-dependency-check` | off | **Emergency bypass.** Skip cycle + missing-dep detection. Use only when recovering from a broken state; every invocation is logged to `config.audit` at `warning` level. |

### Orphan handling

When an entity is present in the active store but **not** in the sync store,
CMI calls it an orphan. The default behaviour is **warn** — log a line per
orphan to `config.audit`, do not delete. Reason: silent data loss after a
careless `config:export` of an incomplete environment is worse than the small
inconvenience of an unwanted entity persisting one extra deploy.

Operators who want Drupal-style "the sync store is authoritative" semantics
opt in:

```bash
bin/waaseyaa config:import --delete-orphans
```

The first run after enabling will remove every entity not represented in
sync. Audit `config:status` output first.

---

## Step 7 — Roll back a single entity (`config:reset`)

When a single entity drifted (manual edit in the admin UI, hot-fix in
production, etc.) and you want to snap it back to the sync-store value:

```bash
bin/waaseyaa config:reset role.editor
# This will overwrite role.editor in the active store with the sync value.
# Continue? [y/N]
```

Or skip the prompt in CI:

```bash
bin/waaseyaa config:reset role.editor --yes
```

Every reset logs to `config.audit` with the actor, the before-after summary,
and a timestamp. Wire that channel into your log aggregator so post-incident
reviews can replay manual interventions.

`config:reset` is per-entity. To reset everything, run `config:import`.

---

## Step 8 — Handle conflicts

When two developers edit the same entity in parallel, the sync-store YAML
file conflicts the same way any source file would. Resolve the YAML conflict
in git, then run:

```bash
bin/waaseyaa config:validate     # confirm the merged YAML parses + validates
bin/waaseyaa config:diff role.editor  # confirm the merged intent
git add storage/config-sync/role.editor.yml
git commit
```

The format is designed for human-readable diffs: keys are sorted
alphabetically, `_meta` is always first, empty maps render as `{}`. If your
merge produces a noisy diff, you probably mis-ordered keys — run
`config:export --diff` against a clean active store to regenerate canonical
YAML.

---

## Step 9 — Recover from a broken import

If a `config:import` partially applied and you need to back out:

1. **Identify the broken entities.** Read the per-entity error messages from
   the failed run (logged to `config.audit`).
2. **Restore prior YAML.** `git checkout HEAD~1 -- storage/config-sync/` (or
   any known-good commit). The active store still contains the partial writes;
   you're restoring the *sync store* to the last good intent.
3. **Re-apply.** `bin/waaseyaa config:import`. Per-entity transactions mean
   each entity rolls back independently on failure, so re-applying is
   idempotent.

If a cycle accidentally landed (rare; `ConfigDependencyCycleException` would
have blocked import) and you need to break the wedge to recover:

```bash
bin/waaseyaa config:import --no-dependency-check
```

This bypasses the DAG check, applies entities in filesystem order, and logs
a `warning` to `config.audit`. **Use once, then fix the cycle, then resume
normal imports.**

---

## §10 — Per-environment overrides (the load-bearing pattern)

CMI does **not** ship runtime config overrides (Drupal `$config['x']['y']`
style). When you need a value that differs between dev / staging / production
— a feature flag, an API endpoint, a debug toggle — **use env vars consumed
inside `config/waaseyaa.php`**, NOT per-environment sync-store overrides.

Why: the sync store is your single declarative source of truth. Per-env sync
stores fragment the source of truth and re-introduce every problem CMI was
built to solve (drift, manual reconciliation, accidental promotion of
env-specific values).

### The pattern

`config/waaseyaa.php`:

```php
return [
    'feature_x' => [
        // Boolean — defaults to false in production-shaped environments.
        'enabled' => (bool) ($_ENV['FEATURE_X_ENABLED'] ?? false),

        // Integer — coerce explicitly so a stray "100\n" doesn't break math.
        'budget' => (int) ($_ENV['FEATURE_X_BUDGET'] ?? 100),

        // String list — split on comma; trim whitespace.
        'allowed_emails' => array_filter(array_map(
            'trim',
            explode(',', $_ENV['FEATURE_X_ALLOWED_EMAILS'] ?? '')
        )),
    ],

    'database' => [
        // Connection string never goes in the sync store.
        'url' => $_ENV['DATABASE_URL'] ?? 'sqlite://./storage/waaseyaa.sqlite',
    ],
];
```

Environment files (`.env.production`, `.env.staging`, `.env.local`):

```bash
# .env.staging
FEATURE_X_ENABLED=true
FEATURE_X_BUDGET=500

# .env.production
# FEATURE_X_ENABLED unset — defaults to false
```

Now `feature_x.enabled` is `true` in staging, `false` in production, and the
sync store is **identical** in both. Promotions touch zero values per
environment.

### What goes where

| Value type | Sync store | `config/waaseyaa.php` (env-driven) |
|---|---|---|
| Roles, permissions, menus, taxonomies | ✅ | — |
| Workflow states, content types, field bundles | ✅ | — |
| Feature flags (enabled in some envs, off in others) | — | ✅ |
| API endpoints / external service URLs | — | ✅ |
| Database / cache / queue connection strings | — | ✅ |
| Secrets (API keys, OAuth client secrets) | **NEVER** — see [`docs/specs/security-defaults.md`](../specs/security-defaults.md) | ✅ via env vars |
| Debug toggles | — | ✅ (`APP_DEBUG`) |
| Caching strategy, log level | — | ✅ |

If you find yourself wanting to export a different sync-store snapshot for
each environment, stop and reach for env vars. The friction is the design.

---

## §11 — CI gate recipe

Wire the validator and the diff check into your deploy pipeline:

```yaml
# .github/workflows/deploy.yml
jobs:
  validate-config:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.5' }
      - run: composer install --prefer-dist --no-progress
      - name: Validate sync store YAML
        run: bin/waaseyaa config:validate
      - name: Confirm no unintended drift from main
        run: |
          bin/waaseyaa config:status --format=json > status.json
          if [ "$(jq '.drift' status.json)" != "0" ]; then
            echo "::error::Unintended config drift detected"
            cat status.json
            exit 1
          fi

  deploy-staging:
    needs: validate-config
    runs-on: ubuntu-latest
    steps:
      - # … deploy app code …
      - name: Apply config sync
        run: bin/waaseyaa config:import
        env:
          # Per-env overrides — env vars, NOT sync-store entries
          FEATURE_X_ENABLED: 'true'
```

`config:validate` exits 1 on any validation error. `config:status --format=json`
returns drift counts you can assert against. Together they make sync drift a
build failure, not a production surprise.

---

## §12 — Common questions

### Should I commit `storage/config-sync/` to git?

Yes. The sync store is your declarative source of truth — it belongs alongside
your source code. The repo path is `storage/config-sync/` by convention; if
you change `config.sync_path` to point elsewhere, commit that path too.

### What about secrets?

Never put secrets in the sync store. The sync store is git-tracked and
operators will leak it. Use env vars. See
[`docs/specs/security-defaults.md`](../specs/security-defaults.md).

### Can I import config from a Drupal site?

Not directly — Drupal's CMI YAML has a different `_meta` shape and uses
different field-type vocabularies. Use the migration platform
([`docs/specs/migration-platform.md`](../specs/migration-platform.md)) instead.

### What about config translation?

Out of scope for M-003. The `_meta.langcode` field exists for forward
compatibility, but every shipped config entity defaults to `en` and CMI does
not yet support per-langcode config files. A future ADR will bridge ADR 017
(per-field translation) and ADR 018 (CMI).

### What happens if a `config:import` is interrupted mid-stream?

Each entity import is its own transaction. Already-committed entities stay
committed; the entity in flight rolls back atomically. Re-running
`config:import` resumes from the next entity in topological order, and
already-applied entities show as `unchanged`.

### How do I know which entities depend on which?

`bin/waaseyaa config:diff` shows per-entity differences but not the graph.
Inspect `_meta.dependencies` in each YAML file, or read the implementing
class's `configDependencies()` return value. A future ADR may add a
`config:graph` rendering command; today the YAML is the source.

---

## §13 — Pointers

- Spec (canonical): [`docs/specs/config-management.md`](../specs/config-management.md).
- Format conventions: [`docs/conventions/cmi-sync-format.md`](../conventions/cmi-sync-format.md).
- ADR: [`docs/adr/018-configuration-management-sync.md`](../adr/018-configuration-management-sync.md).
- Charter: [`docs/specs/stability-charter.md`](../specs/stability-charter.md) §5.5.
- Upgrade guide entry for the introducing alpha: see [`docs/upgrades/`](../upgrades/).
- Mission archive: [`kitty-specs/config-management-v1-01KRCDEC/`](../../kitty-specs/config-management-v1-01KRCDEC/).
