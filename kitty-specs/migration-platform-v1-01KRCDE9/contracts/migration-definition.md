# Contract — Migration Definition

**Mission:** `migration-platform-v1-01KRCDE9` (M-002)
**Spec sections:** §3.2 (FR-011..FR-017), §6.
**Owning WPs:** WP02.
**Charter anchor:** §5.8 (new — Migration platform).

This document is the normative contract for `MigrationDefinition`, `HasMigrationsInterface`, discovery, dependency-graph + cycle detection, and the boot-time registration sequence.

---

## Value object

```php
namespace Waaseyaa\Migration\Definition;

use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;

/**
 * @api
 */
final readonly class MigrationDefinition
{
    public function __construct(
        public string $id,
        public SourcePluginInterface $source,
        /**
         * Field-keyed process map. Key is the destination field name.
         * Value is one of:
         *   - ProcessPluginInterface          (single processor)
         *   - string                          (source field name; runs PassThrough)
         *   - array<ProcessPluginInterface|string>  (chain; array order)
         *
         * @var array<string, ProcessPluginInterface|string|array<ProcessPluginInterface|string>>
         */
        public array $process,
        public DestinationPluginInterface $destination,
        /** @var string[] migration ids this depends on */
        public array $dependencies = [],
        public ?string $description = null,
        public int $memoryBudgetBytes = 268_435_456,    // 256 MiB (Q4 resolution)
        public float $errorRateWarn = 0.01,              // Q5 resolution
        public float $errorRateHalt = 0.10,              // Q5 resolution
    ) {}
}
```

### Constructor validation (WP02)

The constructor MUST validate:

- `$id` is non-empty and matches `/^[a-z][a-z0-9_]*$/` (lowercase alpha-snake).
- `$process` is non-empty; every value is `ProcessPluginInterface`, `string`, or `array<ProcessPluginInterface|string>`.
- `$dependencies` are all distinct non-empty strings.
- `$memoryBudgetBytes` ≥ 0.
- `$errorRateWarn` ∈ [0.0, 1.0], `$errorRateHalt` ∈ [0.0, 1.0], `$errorRateWarn ≤ $errorRateHalt`.

Validation failure raises `\InvalidArgumentException` at construction. Constructor failures are programmer errors, not runtime errors — they fail fast at boot.

---

## Process-map shape (FR-016)

Three forms supported per destination field:

```php
// Form 1: shorthand source field name → PassThroughProcessor
'process' => [
    'title' => 'post_title',
],

// Form 2: a single processor
'process' => [
    'body' => new HtmlSanitizeProcessor(sourceField: 'post_content'),
],

// Form 3: a chain (array order)
'process' => [
    'slug' => [
        new ConcatProcessor(sourceFields: ['post_slug'], suffix: '-archive'),
        new TypeCoerceProcessor(target: 'string'),
    ],
],
```

The runner resolves all three forms to an ordered list of `ProcessPluginInterface` instances at definition-registration time, not at run time.

---

## Provider capability

```php
namespace Waaseyaa\Migration\Capability;

use Waaseyaa\Migration\Definition\MigrationDefinition;

/**
 * @api
 *
 * Provider capability that surfaces concrete MigrationDefinition instances at
 * boot. Same Composer-based discovery as HasNativeCommandsInterface.
 */
interface HasMigrationsInterface
{
    /** @return array<MigrationDefinition> */
    public function migrations(): array;
}
```

### Filesystem fallback (FR-013)

Apps that do not ship migrations inside a package can declare a filesystem path in `config/waaseyaa.php`:

```php
return [
    'migration' => [
        'manifest_paths' => [
            __DIR__ . '/../migrations/',
        ],
    ],
];
```

Each PHP file under a declared path MUST `return new MigrationDefinition(...)`. The registry loads all such files in lexicographic order and registers each result.

Both mechanisms (provider capability + filesystem) register into the same global registry. There is no precedence ranking between them — id collisions raise `MigrationPluginCollisionException` regardless of source.

---

## Boot-time registration sequence

`MigrationRegistry::boot()`:

1. Iterate providers (Composer-discovered).
2. For each `HasMigrationPluginsInterface`, register plugins (see contracts/source-plugin.md, contracts/process-plugin.md, contracts/destination-plugin.md).
3. For each `HasMigrationsInterface`, register definitions; duplicate `id` → `MigrationPluginCollisionException` (FR-017 — definition ids share namespace with plugin ids).
4. Load filesystem-declared definitions from `migration.manifest_paths` (FR-013).
5. For each definition, validate `dependencies[]` against the registry. Missing → `MigrationDependencyMissingException` (FR-014) carrying the missing dependency id and the requesting migration id.
6. Build the dependency DAG via `DependencyGraph` + `CycleDetector` (FR-015). Cycle → `MigrationCycleException` carrying the cycle path (e.g. `['wp_posts', 'wp_terms', 'wp_posts']`).
7. Mark the registry as immutable. Post-boot mutation attempts raise `\LogicException` (programmer error).

---

## Dependency graph (FR-014, FR-015)

`DependencyGraph` is a directed acyclic graph keyed by migration id. Edges: `A → B` means "A depends on B" (B must run before A).

Topological sort returns the order `import:run-all` executes (FR-033). Within a topo-sort layer (independent migrations), the framework runs them sequentially in lexicographic order — parallelism inside a single CLI invocation is **out of scope for v0.x**.

### Cycle detection

`CycleDetector` uses Tarjan's SCC algorithm. On a found cycle:

```php
throw new MigrationCycleException(
    cyclePath: ['wp_posts_to_teachings', 'wp_users_to_accounts', 'wp_posts_to_teachings'],
    message: 'Migration dependency cycle detected: ...',
    code: 'migration_dependency_cycle',
);
```

The cycle path includes the closing repeat (the first id repeats at the end) so log readers can see the loop.

---

## Semantic invariants

1. **Immutability post-boot.** After `MigrationRegistry::boot()` returns, definitions are read-only. There is no public mutator.
2. **Globally unique ids** (FR-017). Migration ids and plugin ids share a namespace; collisions raise `MigrationPluginCollisionException`. A migration MAY NOT share an id with a process plugin.
3. **Eager dependency validation** (FR-014). Missing dependencies are caught at boot, not at run time. This protects operators from learning about a missing dependency 10000 records into a 100000-record run.
4. **Cycle detection at registration** (FR-015). Same reason.
5. **Process map resolved at registration.** Shorthand strings (form 1) and chain arrays (form 3) compile to ordered `ProcessPluginInterface[]` lists at registration time. The runner does not re-parse on every record.

---

## Error conditions

| Error | When | Type | Code |
|---|---|---|---|
| Duplicate migration id | two definitions claim the same `$id` | `MigrationPluginCollisionException` | `migration_id_collision` |
| Missing dependency | `$dependencies[]` references an unknown migration id | `MigrationDependencyMissingException` | `migration_dependency_missing` |
| Dependency cycle | the DAG contains a cycle | `MigrationCycleException` | `migration_dependency_cycle` |
| Invalid constructor args | id format, process map shape, error-rate bounds | `\InvalidArgumentException` | (PHP-native) |
| Process map references unknown source field | (caught at run time, not registration) | `ProcessException` | `process_source_field_missing` |

The registry's exceptions are run-level errors per FR-048 — they always halt regardless of `--halt-on-error`.

---

## Out of scope (FR boundaries)

- **Migration parameters / templating.** A definition is a fully-formed PHP object; the framework does NOT support templated migrations with bind-time parameters in v0.x.
- **Migration as config entity.** Q8 resolution: NO for v0.x. Migrations are PHP objects in code.
- **Cross-package definition merging.** Two providers cannot collaboratively define one migration. One id = one definition.
- **Hot-reload.** Definitions are loaded once at boot. There is no `$registry->reload()`.
