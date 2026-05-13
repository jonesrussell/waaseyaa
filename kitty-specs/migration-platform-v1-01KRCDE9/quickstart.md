# Quickstart — Migration Platform v1

**Mission:** `migration-platform-v1-01KRCDE9` (M-002)
**Phase:** 1 — Design & Contracts

Three short walkthroughs covering the three roles that interact with the new substrate. Code blocks use PHP 8.5 idioms — `final readonly class`, named-args, strict types.

---

## A. Migration author — declare a CSV → User migration

Goal: define a `MigrationDefinition` for "import users from `users.csv` into Waaseyaa User entities", run it, observe id-map state.

### A.1 Provider class

Declare a service provider implementing `HasMigrationsInterface`:

```php
declare(strict_types=1);

namespace App\Migration;

use Waaseyaa\Foundation\ServiceProvider;
use Waaseyaa\Migration\Capability\HasMigrationsInterface;
use Waaseyaa\Migration\Definition\MigrationDefinition;
use Waaseyaa\Migration\Destination\EntityDestination;
use Waaseyaa\Migration\Process\HtmlSanitizeProcessor;
use Waaseyaa\Migration\Process\TypeCoerceProcessor;
use Waaseyaa\Migration\Tests\Fixtures\CsvSource;   // dev-only — see step A.4

/**
 * @api
 */
final class AppMigrationProvider extends ServiceProvider implements HasMigrationsInterface
{
    /** @return array<MigrationDefinition> */
    public function migrations(): array
    {
        return [
            new MigrationDefinition(
                id: 'csv_users_to_accounts',
                source: new CsvSource(
                    path: storage_path('imports/users.csv'),
                    keyFields: ['email'],
                ),
                process: [
                    'name'  => 'full_name',
                    'mail'  => 'email',
                    'bio'   => new HtmlSanitizeProcessor(sourceField: 'biography'),
                    'roles' => new TypeCoerceProcessor(sourceField: 'role_csv', target: 'array'),
                ],
                destination: new EntityDestination(
                    entityType: 'user',
                    bundle: 'imported',
                    langcode: 'en',
                ),
                description: 'One-off import from legacy users.csv',
            ),
        ];
    }
}
```

### A.2 Register the provider

Either via `composer.json`:

```json
{
    "extra": {
        "waaseyaa": {
            "providers": [
                "App\\Migration\\AppMigrationProvider"
            ]
        }
    }
}
```

Or via filesystem fallback in `config/waaseyaa.php`:

```php
return [
    'migration' => [
        'manifest_paths' => [
            __DIR__ . '/../migrations/',
        ],
    ],
];
```

Re-run `bin/waaseyaa optimize:manifest` to pick up the new provider.

### A.3 Run it

```
$ bin/waaseyaa import:run csv_users_to_accounts
[csv_users_to_accounts] 100 / 1000 ... 0 err, 0 skipped, run=01J5T2... eta=0m37s
...
== import:run csv_users_to_accounts ==
total:     1000
imported:  1000
skipped:   0
failed:    0
elapsed:   42s
throughput: 1428 records/min
exit:      0
```

### A.4 Observe the id-map

```php
use Waaseyaa\Migration\IdMap\MigrationIdMap;
use Waaseyaa\Migration\IdMap\SourceId;

$idMap = $container->get(MigrationIdMap::class);
$result = $idMap->lookupDestination(
    'csv_users_to_accounts',
    new SourceId(sourceType: 'csv_user', keys: ['email' => 'wayne@example.org']),
);

// $result is a WriteResult or null
echo $result?->destinationUuid;
```

A second `import:run csv_users_to_accounts` invocation skips every record whose `source_record_hash` is unchanged (FR-031) — zero duplicate entities.

> Note: `CsvSource` is a test-fixture only (FR-052). It is autoloaded under `autoload-dev`, so in production builds with `composer install --no-dev`, references to `Waaseyaa\Migration\Tests\Fixtures\CsvSource` will fail. The `csv_users_to_accounts` example is illustrative; real apps ship their own first-party `SourcePluginInterface` implementation in a separate composer package (see view B).

---

## B. Source-reader package author — ship a hypothetical XML reader

Goal: implement `SourcePluginInterface` for a hypothetical line-delimited XML format. Ship it as a separate composer package. Pass the conformance suite.

### B.1 The package layout

```
my-vendor/waaseyaa-migrate-source-xml/
├── composer.json                                # waaseyaa-migrate-source-xml
├── src/
│   ├── XmlLineSource.php                        # SourcePluginInterface impl
│   ├── XmlMigrationProvider.php                 # HasMigrationPluginsInterface
│   └── ...
└── tests/
    └── Conformance/XmlLineSourceConformanceTest.php
```

### B.2 The source plugin

```php
declare(strict_types=1);

namespace MyVendor\WaaseyaaMigrateSourceXml;

use Waaseyaa\Migration\DTO\SourceRecord;
use Waaseyaa\Migration\IdMap\SourceId;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;

/**
 * @api
 *
 * Streams one XML record per line from a file. Memory-bounded.
 */
final class XmlLineSource implements SourcePluginInterface
{
    public function __construct(
        private readonly string $path,
        /** @var string[] */
        private readonly array $keyFields,
        private readonly string $sourceType = 'xml_record',
    ) {}

    public function id(): string
    {
        return 'xml_line_source';
    }

    public function stability(): string
    {
        return 'stable';
    }

    public function records(): iterable
    {
        $handle = fopen($this->path, 'r');
        if ($handle === false) {
            throw new \Waaseyaa\Migration\Exception\SourceReadException(
                code: 'source_io_error',
                message: "Cannot open {$this->path}",
            );
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $xml = simplexml_load_string($line);
                $fields = [];
                foreach ($xml->children() as $child) {
                    $fields[$child->getName()] = (string) $child;
                }
                yield new SourceRecord(
                    sourceType: $this->sourceType,
                    fields: $fields,
                );
            }
        } finally {
            fclose($handle);
        }
    }

    public function sourceIdFor(SourceRecord $record): SourceId
    {
        $keys = [];
        foreach ($this->keyFields as $field) {
            $keys[$field] = $record->fields[$field] ?? null;
        }
        return new SourceId(sourceType: $this->sourceType, keys: $keys);
    }

    public function count(): ?int
    {
        return null;     // streaming; cannot pre-compute
    }
}
```

### B.3 Register via provider capability

```php
namespace MyVendor\WaaseyaaMigrateSourceXml;

use Waaseyaa\Foundation\ServiceProvider;
use Waaseyaa\Migration\Capability\HasMigrationPluginsInterface;

/**
 * @api
 */
final class XmlMigrationProvider extends ServiceProvider implements HasMigrationPluginsInterface
{
    public function migrationPlugins(): array
    {
        // Plugin instances are templates; concrete configuration happens in
        // the consuming app's MigrationDefinition declarations.
        // For now, return an empty array — the plugin class is imported by
        // MigrationDefinition consumers directly.
        return [];
    }
}
```

(For source plugins, the typical pattern is direct construction inside `MigrationDefinition` rather than registry registration. Process plugins go through the registry; source/destination plugins are usually directly instantiated.)

### B.4 Inherit the conformance test base

```php
declare(strict_types=1);

namespace MyVendor\WaaseyaaMigrateSourceXml\Tests\Conformance;

use MyVendor\WaaseyaaMigrateSourceXml\XmlLineSource;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;
use Waaseyaa\Migration\Testing\SourceConformanceTestCase;

final class XmlLineSourceConformanceTest extends SourceConformanceTestCase
{
    protected function buildPluginUnderTest(): SourcePluginInterface
    {
        return new XmlLineSource(
            path: $this->buildSmallFixturePath(),
            keyFields: ['id'],
        );
    }

    protected function buildLargeFixturePath(): string
    {
        return __DIR__ . '/Fixtures/large-records.xmldb';   // ≥ 50 MB
    }

    protected function buildSmallFixturePath(): string
    {
        return __DIR__ . '/Fixtures/small-records.xmldb';
    }
}
```

The base class enforces the eight conformance gates from contracts/source-plugin.md (C1–C8). Failure of any gate is a hard block on shipping the package.

### B.5 Composer-config note

`composer.json` MUST `require: { waaseyaa/migration: "^X.Y" }` and `require-dev: { ... }` to pick up `SourceConformanceTestCase` from `waaseyaa/migration` under `autoload-dev`. See CLAUDE.md gotcha about test-helper base classes — same pattern.

---

## C. Operator — run, observe, interrupt, resume, rollback

Goal: a realistic operator session against a real 1000-record dataset. Covers the `import:run-all` → `import:status` → `import:resume` → `import:rollback` loop, plus stale-lock recovery.

### C.1 Run all registered migrations

```
$ bin/waaseyaa import:run-all
[csv_users_to_accounts]    1000 / 1000 ... 0 err, 0 skipped, run=01J5T2... done.
[csv_terms_to_taxonomy]    240 / 240 ... 0 err, 0 skipped, run=01J5T3... done.
[csv_posts_to_teachings]   5000 / 5000 ... 0 err, 0 skipped, run=01J5T4... done.

== import:run-all summary ==
migrations: 3
imported:   6240
failed:     0
skipped:    0
elapsed:    4m51s
exit:       0
```

### C.2 Observe status

```
$ bin/waaseyaa import:status
ID                              STATE       TOTAL  IMPORTED  FAILED  SKIPPED  LAST RUN
csv_users_to_accounts           complete    1000   1000      0       0        2026-05-13 09:14:07
csv_terms_to_taxonomy           complete    240    240       0       0        2026-05-13 09:14:51
csv_posts_to_teachings          complete    5000   5000      0       0        2026-05-13 09:18:58
```

### C.3 Simulate an interruption mid-run

```
$ bin/waaseyaa import:run csv_posts_to_teachings   # re-run after a source data change
[csv_posts_to_teachings] 100 / 5000 ... 0 err, 0 skipped, run=01J5T5...
[csv_posts_to_teachings] 200 / 5000 ... 0 err, 0 skipped, run=01J5T5...
^C
$ bin/waaseyaa import:status csv_posts_to_teachings
ID                              STATE       TOTAL  IMPORTED  FAILED  SKIPPED  LAST RUN
csv_posts_to_teachings          partial     5000   217       0       0        2026-05-13 09:21:15
```

The `^C` triggers the `SIGINT` handler — the filesystem lock at `storage/migration-locks/csv_posts_to_teachings.lock` is released. `migration_run_state` shows 217 records committed (per-record commit default; D9).

### C.4 Resume

```
$ bin/waaseyaa import:resume csv_posts_to_teachings
[csv_posts_to_teachings] 218 / 5000 ... 0 err, 0 skipped, run=01J5T5...
...
[csv_posts_to_teachings] 5000 / 5000 ... done.
$ bin/waaseyaa import:status csv_posts_to_teachings
ID                              STATE       TOTAL  IMPORTED  FAILED  SKIPPED  LAST RUN
csv_posts_to_teachings          complete    5000   5000      0       0        2026-05-13 09:24:02
```

`import:resume` re-iterates the source from position 218 (the `MAX(position)` for the prior `run_id`). Records 1–217 are skipped via id-map lookup (FR-031). No duplicates.

### C.5 Stale-lock recovery (FR-062)

Suppose the runner was killed via `kill -9` (no signal handler ran):

```
$ bin/waaseyaa import:run csv_posts_to_teachings
ERROR: MigrationConcurrencyException
  code:    migration_concurrent_run
  lock:    storage/migration-locks/csv_posts_to_teachings.lock
  pid:     48217

Another invocation appears to be holding this lock. Verify the PID is no
longer running, then remove the lock file:

    rm storage/migration-locks/csv_posts_to_teachings.lock

Then retry.
exit: 3
```

Operator verifies:

```
$ ps -p 48217
  PID TTY      STAT   TIME COMMAND
$
```

PID is dead. Remove the lock:

```
$ rm storage/migration-locks/csv_posts_to_teachings.lock
$ bin/waaseyaa import:resume csv_posts_to_teachings
[csv_posts_to_teachings] 218 / 5000 ... resuming.
```

### C.6 Full rollback

Suppose the source CSV had a column-mapping bug; we want to undo everything and start over with a fixed `MigrationDefinition`.

```
$ bin/waaseyaa import:rollback csv_posts_to_teachings
Rolled back 5000 records (0 best-effort failures logged).
exit: 0
$ bin/waaseyaa import:rollback csv_terms_to_taxonomy
Rolled back 240 records (0 best-effort failures logged).
exit: 0
$ bin/waaseyaa import:rollback csv_users_to_accounts
Rolled back 1000 records (0 best-effort failures logged).
exit: 0
```

Cross-migration rollback ordering is operator concern (Complexity Tracking #4 in plan.md): walk in reverse-dependency order. Then `import:reset` if you want to wipe id-map state too:

```
$ bin/waaseyaa import:reset csv_users_to_accounts --yes
Cleared 0 id-map rows for csv_users_to_accounts (already empty post-rollback).
exit: 0
```

Now `import:status` returns to `pending` for every migration, and a fresh `import:run-all` re-imports from scratch.

---

## Where to look next

- **The spec:** `kitty-specs/migration-platform-v1-01KRCDE9/spec.md` — 625 lines, 62 FRs, full normative content.
- **The plan:** `kitty-specs/migration-platform-v1-01KRCDE9/plan.md` — this mission's implementation roadmap.
- **The contracts:** `kitty-specs/migration-platform-v1-01KRCDE9/contracts/` — five files; one per interface / CLI surface.
- **The research log:** `kitty-specs/migration-platform-v1-01KRCDE9/research.md` — every decision with rationale + alternatives + risk register.
- **The data model:** `kitty-specs/migration-platform-v1-01KRCDE9/data-model.md` — every stable-surface symbol with its file path + WP.
- **The governing ADR:** `docs/adr/012a-migration-substrate-in-core.md` — the strategic decision.
- **Sibling-mission prereqs (MET):** `kitty-specs/entity-storage-v2-01KRCDDC/` (M-001) + `kitty-specs/entity-storage-translations-v1-01KRF0FQ/` (M-006).
