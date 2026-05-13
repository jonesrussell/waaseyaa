---
work_package_id: WP02
title: MigrationDefinition + discovery + dependency graph
dependencies:
- WP01
requirement_refs:
- FR-011
- FR-012
- FR-013
- FR-014
- FR-015
- FR-016
- FR-017
planning_base_branch: main
merge_target_branch: main
branch_strategy: lane
subtasks:
- T008
- T009
- T010
- T011
- T012
- T013
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/src/Discovery/
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/src/MigrationDefinition.php
- packages/migration/src/Discovery/HasMigrationsInterface.php
- packages/migration/src/Discovery/MigrationRegistry.php
- packages/migration/src/Discovery/DependencyGraph.php
- packages/migration/src/Discovery/CycleDetector.php
- packages/migration/src/Discovery/FilesystemManifestLoader.php
- packages/migration/src/Exception/MigrationCycleException.php
- packages/migration/src/Exception/MigrationDependencyMissingException.php
- packages/migration/tests/Unit/MigrationDefinitionTest.php
- packages/migration/tests/Unit/Discovery/MigrationRegistryTest.php
- packages/migration/tests/Unit/Discovery/DependencyGraphTest.php
- packages/migration/tests/Unit/Discovery/FilesystemManifestLoaderTest.php
priority: p1
tags:
- stable-surface
- layer-3
- discovery
---

# WP02 — MigrationDefinition + discovery + dependency graph

## Objective

Ship the `MigrationDefinition` value object, the `HasMigrationsInterface` provider capability, the filesystem-manifest loader, the dependency-graph computation with cycle detection, and the two related typed exceptions (`MigrationCycleException`, `MigrationDependencyMissingException`). After this WP merges, providers and apps can declare migrations; the registry can compute a deterministic execution order; cycle detection produces useful error paths.

This is half of the §5.8 charter surface and a hard dependency for WP06 (the runner needs the registry to walk migrations in dependency order).

## Dependencies

- Internal: WP01 (plugin contracts + `PluginRegistry`).
- External: None.
- Charter anchors: extends the §5.8 (proposed) surface with `MigrationDefinition`, `HasMigrationsInterface`, the dependency-graph exceptions.

## Scope (in / out)

**In scope**
- `MigrationDefinition` final readonly value object with constructor validation per `contracts/migration-definition.md` (FR-011, FR-012, FR-016).
- `HasMigrationsInterface` provider capability returning `iterable<MigrationDefinition>` (FR-013).
- `FilesystemManifestLoader` scanning paths declared in `config/waaseyaa.php` `migration.manifest_paths` (FR-013).
- `MigrationRegistry` boot scanner indexing definitions by id, raising on collision (FR-017), validating dependencies (FR-014), building the DAG (FR-015).
- `DependencyGraph` + `CycleDetector` (classical DFS three-colour marking).
- `MigrationCycleException` carrying the cycle path (e.g. `['wp_posts', 'wp_terms', 'wp_posts']`).
- `MigrationDependencyMissingException` carrying both the missing id and the requesting migration id.
- Updated `ServiceProvider::boot()` from WP01: register `MigrationRegistry` alongside `PluginRegistry`.

**Out of scope**
- Source / process / destination plugin discovery (already in WP01).
- CLI commands that operate on the registry (`import:run-all`'s topological walk uses the DAG; the command itself lands in WP06).
- Filesystem watching / hot reload — not v0.x.

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Execution worktree per-lane at finalize-tasks time. Run `spec-kitty agent action implement WP02 --agent opus` to enter the worktree.

## Implementation guidance

### Subtask T008 — `MigrationDefinition` value object

**Purpose**: Ship the canonical manifest value object — the single PHP object every migration author writes.

**FRs covered**: FR-011, FR-012, FR-016.

**Files**:
- `packages/migration/src/MigrationDefinition.php` (new, ~140 lines).

**Steps**:
1. Define `final readonly class MigrationDefinition`, `@api`.
2. Constructor signature (per `contracts/migration-definition.md`):
   ```php
   public function __construct(
       public string $id,
       public SourcePluginInterface $source,
       /** @var array<string, ProcessPluginInterface|string|array<ProcessPluginInterface|string>> */
       public array $process,
       public DestinationPluginInterface $destination,
       /** @var list<string> */
       public array $dependencies = [],
       public ?string $description = null,
       public int $memoryBudgetBytes = 268_435_456,        // 256 MB default per Q4
       public float $errorRateWarn = 0.01,                  // per Q5
       public float $errorRateHalt = 0.10,                  // per Q5
   ) {
       // validate
   }
   ```
3. Constructor validators (FR-014 hot-path is at registry boot; FR-012 is per-instance):
   - `$id` non-empty, matches `/^[a-z][a-z0-9_]*$/`.
   - `$process` non-empty.
   - Every `$process` value is `ProcessPluginInterface`, a non-empty `string`, or `array<ProcessPluginInterface|string>` (chain). Chain entries each match the same constraints (recursive).
   - `$dependencies` are all distinct non-empty strings (no self-reference: `$dep !== $id`).
   - `$memoryBudgetBytes` ≥ 0.
   - `$errorRateWarn`, `$errorRateHalt` ∈ [0.0, 1.0] and `$errorRateWarn ≤ $errorRateHalt`.
   - Failures raise `\InvalidArgumentException` with a precise message.
4. Add `processForField(string $destinationField): list<ProcessPluginInterface|string>` accessor — normalizes the heterogeneous shape into a flat list. Used by the runner (WP06). Document that a bare string in the original `$process` map normalizes into a single-element list `[$stringSourceField]`.

**Validation**:
- [ ] Unit test: every validator rejection path.
- [ ] Unit test: valid construction round-trips.
- [ ] Unit test: `processForField()` returns the same list for the three shapes (`Processor`, `string`, `array`).

**Edge cases**:
- An empty `$dependencies` array is valid (most leaf migrations).
- `$bundle` is NOT part of `MigrationDefinition` — it travels through `EntityDestination`'s constructor (decision D8). Make this clear in PHPDoc to avoid confusion with the `$process` map.

### Subtask T009 — `HasMigrationsInterface` provider capability

**Purpose**: Define the provider-capability marker for migration discovery.

**FRs covered**: FR-013.

**Files**:
- `packages/migration/src/Discovery/HasMigrationsInterface.php` (new, ~30 lines).

**Steps**:
1. Define a marker interface (parallel to `HasMigrationPluginsInterface` from WP01), `@api`:
   ```php
   /** @return iterable<MigrationDefinition> */
   public function migrations(): iterable;
   ```
2. PHPDoc documents the two registration mechanisms: provider capability (preferred for source-reader packages) and filesystem path (for one-off app migrations).

**Validation**:
- [ ] Unit test: a fake provider implementing the interface yields definitions; the registry picks them up (test driven from T011).

### Subtask T010 — `FilesystemManifestLoader`

**Purpose**: Scan filesystem paths declared in `config/waaseyaa.php` and load PHP files returning `MigrationDefinition` instances.

**FRs covered**: FR-013.

**Files**:
- `packages/migration/src/Discovery/FilesystemManifestLoader.php` (new, ~120 lines).

**Steps**:
1. `final class FilesystemManifestLoader` (`@api`).
2. Constructor: `__construct(array $manifestPaths, ?LoggerInterface $logger = null)`. Each path must be an absolute filesystem path; relative paths raise `\InvalidArgumentException`.
3. `load(): iterable<MigrationDefinition>` walks each path, finds `*.php` files, `require`s each one in an isolated scope (anonymous function + `return` value), and asserts the returned value is a `MigrationDefinition`. Anything else raises `\RuntimeException` with the offending file path.
4. Logging on `Channels::MIGRATION_DEPRECATION`-adjacent log channel (use `migration.discovery` for the discovery channel — add the constant in `Channels.php`).
5. Use `\SplFileInfo` + `\RecursiveIteratorIterator` for the walk; sort by full path for deterministic order (R4 risk hedge).

**Validation**:
- [ ] Unit test: pass two paths, write fixture `.php` files returning `MigrationDefinition` instances, assert the loader yields them in deterministic order.
- [ ] Unit test: a file that does not return `MigrationDefinition` raises.
- [ ] Unit test: a non-existent path raises `\InvalidArgumentException`.

**Edge cases**:
- A path that exists but contains no `.php` files yields an empty iterable. Log at `info` level for operator visibility (R-discovery-silent risk).
- Symlinked directories: follow by default (`\RecursiveDirectoryIterator::FOLLOW_SYMLINKS` flag). Document in PHPDoc.

### Subtask T011 — `MigrationRegistry` + `DependencyGraph` + `CycleDetector`

**Purpose**: The boot-time registry that indexes definitions and computes the DAG.

**FRs covered**: FR-013, FR-014, FR-015, FR-017.

**Files**:
- `packages/migration/src/Discovery/MigrationRegistry.php` (new, ~220 lines).
- `packages/migration/src/Discovery/DependencyGraph.php` (new, ~140 lines).
- `packages/migration/src/Discovery/CycleDetector.php` (new, ~110 lines).

**Steps**:
1. `MigrationRegistry`:
   - Constructor: `__construct(PluginRegistry $plugins, FilesystemManifestLoader $filesystemLoader, ?LoggerInterface $logger = null)`.
   - `boot(iterable $providers): void` per `contracts/migration-definition.md` boot sequence:
     1. Iterate providers; for each `HasMigrationsInterface`, accumulate definitions.
     2. Iterate the filesystem loader; accumulate definitions.
     3. Index by `$id`; duplicate raises `MigrationPluginCollisionException` (FR-017 — reuses WP01's exception; the spec explicitly shares the namespace).
     4. For every definition, validate dependencies against the registry. Missing → `MigrationDependencyMissingException` (FR-014).
     5. Build `DependencyGraph::fromDefinitions($definitions)`; if `CycleDetector::detect()` returns a non-empty cycle, raise `MigrationCycleException` (FR-015).
     6. Mark immutable. Post-boot mutations throw `\LogicException`.
   - Public accessors: `get(string $id): MigrationDefinition`, `all(): list<MigrationDefinition>`, `topologicallySorted(): list<MigrationDefinition>` (returns definitions in dependency order for `import:run-all`).
2. `DependencyGraph`:
   - `final class DependencyGraph` (`@api`).
   - `fromDefinitions(iterable $definitions): self` factory.
   - Internal adjacency list `array<string, list<string>>` mapping id → dependency ids.
   - `topologicalOrder(): list<string>` returns Kahn's-algorithm output; deterministic tie-breaking by lexicographic id.
   - `dependencies(string $id): list<string>`; `dependents(string $id): list<string>` (reverse-edges, for status display).
3. `CycleDetector`:
   - `detect(DependencyGraph $graph): list<string>` — returns the offending cycle path or `[]` if acyclic.
   - Implementation: classical DFS with three-color marking (`WHITE`, `GRAY`, `BLACK`). When a `GRAY` node is re-encountered during traversal, walk the recursion stack to extract the cycle path.

**Validation**:
- [ ] Unit test: cycle of length 2 (`a → b → a`) is detected and the path is exactly `['a', 'b', 'a']`.
- [ ] Unit test: cycle of length 3.
- [ ] Unit test: diamond dependency (`a → b → d`, `a → c → d`) is acyclic and `topologicalOrder()` is stable.
- [ ] Unit test: missing dependency in `MigrationRegistry::boot()` raises `MigrationDependencyMissingException` with both ids.
- [ ] Unit test: collision raises `MigrationPluginCollisionException`.
- [ ] Unit test: post-boot mutation raises `\LogicException`.

**Edge cases**:
- A self-loop (`a → a`) is rejected at `MigrationDefinition` construction (T008 validator), so the cycle detector should never see one — but include a regression test anyway.
- Two migrations with no dependencies must topologically sort by lexicographic id (deterministic — covers Q3 ordering).

### Subtask T012 — `MigrationCycleException` + `MigrationDependencyMissingException`

**Purpose**: The two typed exceptions for FR-014 / FR-015.

**FRs covered**: FR-014, FR-015, FR-045 (continued — exception surface).

**Files**:
- `packages/migration/src/Exception/MigrationCycleException.php` (new, ~50 lines).
- `packages/migration/src/Exception/MigrationDependencyMissingException.php` (new, ~50 lines).

**Steps**:
1. `MigrationCycleException`:
   - Extends `\RuntimeException`. `@api`.
   - `public readonly array $cyclePath` (`list<string>`).
   - `public const CODE = 'MIGRATION_DEPENDENCY_CYCLE'`.
   - Message: `"Dependency cycle detected: <a> -> <b> -> <a>"` (path joined by ` -> `).
2. `MigrationDependencyMissingException`:
   - Extends `\RuntimeException`. `@api`.
   - `public readonly string $missingId`, `public readonly string $requestingMigrationId`.
   - `public const CODE = 'MIGRATION_DEPENDENCY_MISSING'`.
   - Message: `"Migration '<requestingId>' declares missing dependency '<missingId>'"`.

**Validation**:
- [ ] Unit test: property round-trip + message format.

### Subtask T013 — Integration test: end-to-end discovery + ServiceProvider wiring

**Purpose**: Prove the boot sequence works through a real `ServiceProvider` + `PackageManifestCompiler` integration. This is the WP's only integration test.

**FRs covered**: FR-011..FR-017 (composition test).

**Files**:
- `packages/migration/tests/Integration/DiscoveryBootstrapTest.php` (new, ~180 lines).
- `packages/migration/src/ServiceProvider.php` (modify — extend `boot()` to construct `MigrationRegistry` and wire it).

**Steps**:
1. In `ServiceProvider::boot()`: after `PluginRegistry::boot()`, construct `FilesystemManifestLoader` from `config('waaseyaa.migration.manifest_paths', [])`, then `MigrationRegistry::boot(...)`. Bind the registry as a singleton.
2. The integration test boots a minimal kernel with two fake providers — one shipping plugins (via `HasMigrationPluginsInterface`), one shipping definitions (via `HasMigrationsInterface`). Assert the registry reports both registered.
3. A second test in the file boots a cycle scenario and asserts `MigrationCycleException` propagates from `ServiceProvider::boot()`.

**Validation**:
- [ ] `./vendor/bin/phpunit packages/migration/tests/Integration/DiscoveryBootstrapTest.php` green.
- [ ] Full suite green.

**Edge cases**:
- The kernel boot guard requires `APP_ENV=local` if `APP_DEBUG=true` (CLAUDE.md gotcha). The test must explicitly set `APP_ENV=testing` to avoid the guard.

## Tests

- **Unit**: T008 / T011 / T012 cover the per-class logic.
- **Integration**: T013 covers the boot sequence with real kernel wiring.
- **Conformance**: not yet — WP10.

## Definition of Done

- [ ] All six subtasks complete.
- [ ] All seven FRs cited in code as `@spec FR-xxx`.
- [ ] `composer phpstan` clean for `packages/migration/`.
- [ ] `composer cs-check` clean (run twice — `feedback_cs_fix_two_passes.md`).
- [ ] `bin/check-composer-policy` clean.
- [ ] `bin/check-package-layers` clean.
- [ ] `bin/audit-dead-code` reports no new findings.
- [ ] `./vendor/bin/phpunit` passes the full suite (not just `packages/migration/`).
- [ ] All new public symbols carry `@api`.
- [ ] No `psr/log` imports; only `Waaseyaa\Foundation\Log\LoggerInterface`.
- [ ] `topologicalOrder()` is deterministic — two consecutive calls yield identical lists.

## Risks

- **R1 — Cycle detector false-positives on diamond patterns**: classical DFS three-color marking handles diamonds correctly. Cover with a dedicated test (T011 validation).
- **R2 — Filesystem-discovery silent empty paths**: a misconfigured `manifest_paths` could yield zero migrations and look like everything is normal. Mitigation: T010 logs at `info` per non-empty path and at `notice` if a path resolves to zero matching files.
- **R3 — Definition collision between provider and filesystem**: same id discovered through both mechanisms must collide cleanly. The registry's collision check fires regardless of source — covered by T011 validation.

## Reviewer guidance

- Check: every new public class/interface carries `@api`.
- Check: `CycleDetector` uses three-color marking (not Kahn-with-counter — wrong algorithm for path extraction). The cycle-path field on `MigrationCycleException` must contain the full cycle including the duplicate end-node.
- Check: `MigrationRegistry::topologicallySorted()` is stable across runs.
- Check: `FilesystemManifestLoader` sorts paths before yielding, otherwise OS-dependent inode order causes flaky tests.
- Verify: the `\InvalidArgumentException` thrown by `MigrationDefinition::__construct()` includes the specific failing constraint (not a generic "invalid migration" message).
- Confirm: the integration test in T013 actually boots a real kernel (not a mock) and exercises `PackageManifestCompiler` discovery.
