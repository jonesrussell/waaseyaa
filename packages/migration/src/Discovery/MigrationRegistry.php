<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Discovery;

use Waaseyaa\Migration\Exception\MigrationCycleException;
use Waaseyaa\Migration\Exception\MigrationDependencyMissingException;
use Waaseyaa\Migration\Exception\MigrationPluginCollisionException;
use Waaseyaa\Migration\MigrationDefinition;

/**
 * Indexes every {@see MigrationDefinition} contributed by providers or the
 * filesystem loader at boot, validates dependencies, and exposes the resulting
 * DAG for downstream consumers (FR-013, FR-014, FR-015, FR-017).
 *
 * Lifecycle:
 *
 * 1. Construct with the providers implementing {@see HasMigrationsInterface}
 *    and an optional {@see FilesystemManifestLoader}.
 * 2. Call {@see boot()}. The registry consumes both sources, indexes the
 *    definitions by id (collisions raise {@see MigrationPluginCollisionException}),
 *    validates every declared dependency exists (missing raises
 *    {@see MigrationDependencyMissingException}), and constructs the
 *    {@see DependencyGraph}. Cycles raise {@see MigrationCycleException}.
 * 3. Post-`boot()` the registry is immutable. Any further attempt to register
 *    raises `\LogicException` (programmer error).
 *
 * @api
 */
final class MigrationRegistry
{
    /** @var array<string, MigrationDefinition> */
    private array $definitions = [];

    private ?DependencyGraph $graph = null;

    private bool $booted = false;

    /**
     * @param iterable<HasMigrationsInterface> $providers Providers that ship migrations as part of their `extra.waaseyaa.providers` surface.
     * @param ?FilesystemManifestLoader $filesystemLoader Optional loader scanning `config/waaseyaa.php` `migration.manifest_paths`.
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly ?FilesystemManifestLoader $filesystemLoader = null,
    ) {}

    /**
     * Single-shot boot. Idempotent calls raise `\LogicException` so callers
     * cannot accidentally re-register after the registry has been frozen.
     *
     * @throws MigrationPluginCollisionException When two sources register the same migration id.
     * @throws MigrationDependencyMissingException When a definition declares a dependency that is not registered.
     * @throws MigrationCycleException When the dependency graph contains a cycle.
     * @throws \LogicException When called more than once.
     */
    public function boot(): void
    {
        if ($this->booted) {
            throw new \LogicException('MigrationRegistry::boot() may only be called once per registry.');
        }

        $this->ingestProviderDefinitions();
        $this->ingestFilesystemDefinitions();
        $this->validateDependencies();
        $this->graph = DependencyGraph::fromDefinitions($this->definitions);
        $this->detectCycles($this->graph);

        $this->booted = true;
    }

    /**
     * Fetch a registered definition.
     *
     * @throws \OutOfBoundsException When `$id` is not registered.
     * @throws \LogicException Before {@see boot()}.
     */
    public function get(string $id): MigrationDefinition
    {
        $this->assertBooted();
        if (!isset($this->definitions[$id])) {
            throw new \OutOfBoundsException(\sprintf(
                'MigrationRegistry: migration %s is not registered.',
                \var_export($id, true),
            ));
        }
        return $this->definitions[$id];
    }

    /**
     * Whether a migration with the given id is registered.
     *
     * @throws \LogicException Before {@see boot()}.
     */
    public function has(string $id): bool
    {
        $this->assertBooted();
        return isset($this->definitions[$id]);
    }

    /**
     * Every registered definition. Order is registration order — for
     * dependency-aware iteration use {@see topologicallySorted()}.
     *
     * @return list<MigrationDefinition>
     *
     * @throws \LogicException Before {@see boot()}.
     */
    public function all(): array
    {
        $this->assertBooted();
        return \array_values($this->definitions);
    }

    /**
     * Definitions in dependency order — every prerequisite appears before its
     * dependent. Powers `bin/waaseyaa import:run-all` (FR-033).
     *
     * @return list<MigrationDefinition>
     *
     * @throws \LogicException Before {@see boot()}.
     */
    public function topologicallySorted(): array
    {
        $this->assertBooted();
        \assert($this->graph !== null);

        $ordered = [];
        foreach ($this->graph->topologicalOrder() as $id) {
            $ordered[] = $this->definitions[$id];
        }
        return $ordered;
    }

    /**
     * Read-only handle on the dependency graph. Useful for status displays and
     * `import:rollback` cascade walks.
     *
     * @throws \LogicException Before {@see boot()}.
     */
    public function graph(): DependencyGraph
    {
        $this->assertBooted();
        \assert($this->graph !== null);
        return $this->graph;
    }

    private function ingestProviderDefinitions(): void
    {
        foreach ($this->providers as $provider) {
            $contributedBy = $provider::class;
            // `HasMigrationsInterface::migrations()` returns
            // `iterable<MigrationDefinition>`; providers that yield other
            // types violate the contract and produce a native TypeError at
            // `indexDefinition()`'s parameter binding.
            foreach ($provider->migrations() as $definition) {
                $this->indexDefinition($definition, $contributedBy);
            }
        }
    }

    private function ingestFilesystemDefinitions(): void
    {
        if ($this->filesystemLoader === null) {
            return;
        }
        foreach ($this->filesystemLoader->load() as $definition) {
            // FilesystemManifestLoader is the canonical "first claimant" for
            // its definitions; collisions report the loader namespace.
            $this->indexDefinition($definition, FilesystemManifestLoader::class);
        }
    }

    private function indexDefinition(MigrationDefinition $definition, string $contributedBy): void
    {
        if (isset($this->definitions[$definition->id])) {
            $existing = $this->definitions[$definition->id];
            throw new MigrationPluginCollisionException(
                pluginId: $definition->id,
                firstFqcn: $existing::class,
                secondFqcn: $contributedBy === MigrationDefinition::class
                    ? $definition::class
                    : $contributedBy,
            );
        }
        $this->definitions[$definition->id] = $definition;
    }

    private function validateDependencies(): void
    {
        foreach ($this->definitions as $id => $definition) {
            foreach ($definition->dependencies as $dependency) {
                if (!isset($this->definitions[$dependency])) {
                    throw new MigrationDependencyMissingException(
                        missingDependencyId: $dependency,
                        requestingMigrationId: $id,
                    );
                }
            }
        }
    }

    private function detectCycles(DependencyGraph $graph): void
    {
        $cycle = new CycleDetector()->detect($graph);
        if ($cycle !== []) {
            throw new MigrationCycleException(cyclePath: $cycle);
        }
    }

    private function assertBooted(): void
    {
        if (!$this->booted) {
            throw new \LogicException('MigrationRegistry::boot() must be called before definitions can be resolved.');
        }
    }
}
