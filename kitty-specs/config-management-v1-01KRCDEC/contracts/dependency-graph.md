# Contract: Dependency Graph + Ordering Semantics

**Stability scope:** charter §5.5 (amended at mission close) — `ConfigDependencyInterface` and the cycle/missing exception types are on stable surface
**FRs covered:** FR-001..FR-008, FR-050..FR-052
**Owned by:** WP01

## `ConfigDependencyInterface`

```php
namespace Waaseyaa\Config\Dependency;

interface ConfigDependencyInterface
{
    /**
     * Declare config entities this entity depends on.
     *
     * Each entry is `<entity_type>.<entity_id>` and references another
     * config entity that MUST exist (in the sync store or active store)
     * before this one can be imported.
     *
     * @return list<string>
     */
    public function configDependencies(): array;
}
```

**Stability commitments:**
- The interface name `Waaseyaa\Config\Dependency\ConfigDependencyInterface` is on stable surface.
- The method name `configDependencies()` is on stable surface.
- The return type `list<string>` is on stable surface.
- Adding new methods to this interface is a breaking change requiring charter §4 deprecation cycle.

**Default no-op implementation:** `Waaseyaa\Config\ConfigEntityBase::configDependencies(): array { return []; }`. Entity-type-specific classes override only when they actually have dependencies. Most do not.

## Dependency declaration semantics

A dependency is **a hard precondition**: importer MUST apply the dependency entity before the dependent. Examples:

- A `menu` config entity depends on a `taxonomy_vocabulary` it references → the vocabulary must be imported first.
- A `permission` entity depends on the `role` it applies to → the role must exist first.
- An `oauth_client` entity depends on a `scope` definition → scope first.

Soft references (e.g. UI hints, search-indexer config) are NOT dependencies; they MAY be omitted from `configDependencies()` if the dependent functions correctly without the referent.

**Cross-package dependencies:** a config entity in package A MAY declare a dependency on a config entity in package B. The graph spans the entire app's config-entity registry; the importer does not partition by package. Provider load order is irrelevant to import ordering — the DAG computation happens after all providers register.

## DAG computation

```php
final class DependencyResolver
{
    public function resolve(array $files): DependencyGraph;
    //                     list<ConfigSyncFile>
}
```

### Algorithm

1. Build a node set: every `ConfigSyncFile::ref()` (`<entity_type>.<entity_id>`).
2. Build an edge set: for each file, for each entry in `_meta.dependencies`, add an edge from `dep` → `dependent`.
3. Validate every edge endpoint:
   - **Edge tail (the dependency)** MUST exist either in the sync-file set OR in the active store. Otherwise → `ConfigDependencyMissingException(missingRef: $tail, requiredBy: $head)`.
   - **Edge head (the dependent)** is always present by construction (it's the file we're scanning).
4. Run DFS over the graph with white/gray/black coloring:
   - White = unvisited.
   - Gray = on the current DFS stack.
   - Black = fully visited.
   - Encountering a gray node from another gray node = cycle. Reconstruct the path via the parent map; raise `ConfigDependencyCycleException(cyclePath: [...])`.
5. On successful DFS completion, post-order traversal yields the topological order (reverse it for "dependencies first" semantics).
6. **Tie-break:** within a layer of nodes that share the same "topological level" (no remaining dependencies), sort lexicographically by ref. Guarantees deterministic ordering across runs / processes / OSes.

### Complexity

- O(V + E) for the DFS proper.
- Sort tie-break is O(L log L) per layer where L is the layer size; total sort work bounded by O(V log V).
- Memory: O(V + E) for adjacency lists + visited set.
- NFR-C2 sentinel: < 100 ms for 200 nodes / 400 edges on a Sonnet-class machine.

## `DependencyGraph` value object

```php
final readonly class DependencyGraph
{
    public function __construct(
        public array $edges,            // array<string, list<string>>
        public array $topologicalOrder, // list<string>
    ) {
        // Constructor enforces: $topologicalOrder is a complete acyclic ordering of keys($edges).
    }

    public function nodes(): array;           // list<string>
    public function hasNode(string $ref): bool;
    public function edgesFrom(string $ref): array;
    public function isAcyclic(): bool;        // true by construction
}
```

Constructed only by `DependencyResolver::resolve()` in production. Tests may construct directly with carefully-chosen fixtures.

## Exception types

### `ConfigDependencyCycleException`

```php
final class ConfigDependencyCycleException extends \RuntimeException
{
    public function __construct(
        public readonly array  $cyclePath,    // list<string>, the full cycle
        public readonly string $code = 'config.dependency.cycle',
        ?\Throwable $previous = null,
    );

    public function getCycle(): array;        // full cycle path, no truncation
}
```

- **Cycle path format:** `[A, B, C, A]` — first and last elements equal; explicit closure of the cycle. Length ≥ 3.
- **`getMessage()` output** truncates at 5 hops with `…` for log/console readability:
  ```
  Config dependency cycle: role.admin → permission.x → role.admin
  ```
  or for longer cycles:
  ```
  Config dependency cycle: a → b → c → d → e → … → a
  ```
- **`getCycle()`** returns the full path untruncated for programmatic / test access.
- **`code`:** stable string `'config.dependency.cycle'`.

### `ConfigDependencyMissingException`

```php
final class ConfigDependencyMissingException extends \RuntimeException
{
    public function __construct(
        public readonly string $missingRef,   // the ref that does not exist
        public readonly string $requiredBy,   // the ref that requires it
        public readonly string $code = 'config.dependency.missing',
        ?\Throwable $previous = null,
    );
}
```

- **`code`:** stable string `'config.dependency.missing'`.
- **`getMessage()`:** `"Config dependency '<missingRef>' required by '<requiredBy>' is not present in sync store or active store."`

## Emergency bypass: `--no-dependency-check`

`bin/waaseyaa config:import --no-dependency-check` bypasses both cycle detection and missing-dependency detection (FR-007). The importer skips the DAG entirely and applies files in lexicographic order.

**Audit trail:** every use of `--no-dependency-check` logs at `warning` level to the `config.audit` channel:

```
[warning] config:import invoked with --no-dependency-check actor=<user> sync_path=<path> file_count=<n>
```

The flag exists only for emergency recovery scenarios — restoring from a known-good sync store after a botched dependency-declaration refactor, etc. The cookbook documents it as such and discourages routine use.

## Behaviour on rename + dependency change

If a sync file is renamed (UUID preserved, id changes) AND a different file declares a dependency on the OLD id, that dependency points at a missing ref:

- The graph computation will raise `ConfigDependencyMissingException` (the old id is not in the sync store anymore).
- Resolution: the operator MUST update the dependency declaration in the referring file to use the new id, then re-export.

The framework does not attempt to auto-rewrite dependency declarations on rename. This is intentional — silent rewrites would mask broken references.

## Behaviour on cross-package install order

Cross-package dependencies (config in package A depends on config in package B) work because:

- All `ServiceProvider`s register their entity types before any `config:*` command runs.
- The DAG is computed at command invocation time against the full registered registry.
- Provider registration order is irrelevant to import ordering — only dependency declarations matter.

If package B is uninstalled, package A's dependent config raises `ConfigDependencyMissingException` at import. The operator must reinstall B or remove A's dependency before importing.

## Test coverage commitments (WP01, WP10)

- **WP01 unit tests:**
  - Empty graph → empty topological order.
  - Single node → single-element order.
  - Linear chain `A → B → C` → `[A, B, C]`.
  - Diamond `A → B, A → C, B → D, C → D` → `[A, B, C, D]` (B, C lex-sorted).
  - Cycle `A → B → A` → `ConfigDependencyCycleException(cyclePath: [A, B, A])`.
  - Long cycle `A → B → C → D → A` → exception with full path.
  - Missing dependency → `ConfigDependencyMissingException` with both refs.
- **WP10 integration test (FR-056):** a fixture with a deliberate cycle MUST raise `ConfigDependencyCycleException` with the full cycle path; assert via `getCycle()`.
