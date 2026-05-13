<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Discovery;

use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Migration\Exception\MigrationPluginCollisionException;
use Waaseyaa\Migration\Log\Channels;
use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;

/**
 * Boot-time scanner that indexes migration plugins discovered through
 * {@see HasMigrationPluginsInterface}-bearing providers.
 *
 * Lifecycle:
 *
 *   1. The kernel constructs a `PluginRegistry` with the discovered providers.
 *   2. {@see boot()} iterates providers in `installed.json` order, calls
 *      `migrationPlugins()`, and indexes every plugin by `id()` into three
 *      type-segregated maps (source / process / destination).
 *   3. Collisions raise {@see MigrationPluginCollisionException} (with the
 *      reserved-id flag set when a third-party attempts to re-register a
 *      framework-reserved id).
 *   4. After `boot()` the registry is immutable; later writes throw
 *      {@see \LogicException} so accidental late registration is loud.
 *
 * First-use deprecation:
 *
 *   When a caller resolves an experimental plugin for the first time per
 *   process, the registry emits a single warning on
 *   {@see Channels::MIGRATION_DEPRECATION}. Subsequent resolves for the same
 *   id do not re-emit.
 *
 * @api
 */
final class PluginRegistry
{
    /** @var array<string, SourcePluginInterface> */
    private array $sources = [];

    /** @var array<string, ProcessPluginInterface> */
    private array $processes = [];

    /** @var array<string, DestinationPluginInterface> */
    private array $destinations = [];

    /** @var array<string, class-string> Tracks first-claimant FQCN per plugin id for collision messages. */
    private array $claimantByPluginId = [];

    /** @var array<string, true> Plugin ids that have already fired their deprecation notice this process. */
    private array $deprecationFired = [];

    private bool $booted = false;

    private readonly LoggerInterface $logger;

    /** @var list<HasMigrationPluginsInterface> */
    private readonly array $providers;

    /**
     * @param list<HasMigrationPluginsInterface> $providers Providers to scan, in the order returned by Composer's `installed.json`.
     */
    public function __construct(
        array $providers = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->providers = $providers;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Scan every provider and index its plugins.
     *
     * @throws MigrationPluginCollisionException If any two providers register the same id, or a third-party provider claims a framework-reserved id.
     * @throws \LogicException If called more than once.
     */
    public function boot(): void
    {
        if ($this->booted) {
            throw new \LogicException('PluginRegistry::boot() called twice; the registry is immutable after the first boot.');
        }

        foreach ($this->providers as $provider) {
            foreach ($provider->migrationPlugins() as $plugin) {
                $this->index($plugin);
            }
        }

        $this->booted = true;
    }

    /**
     * Resolve a source plugin by id.
     *
     * @throws \OutOfBoundsException If no source plugin with $id is registered.
     */
    public function getSource(string $id): SourcePluginInterface
    {
        $this->assertBooted();
        if (!isset($this->sources[$id])) {
            throw new \OutOfBoundsException(\sprintf('No source plugin registered with id %s.', var_export($id, true)));
        }
        $plugin = $this->sources[$id];
        $this->recordUse($id, $plugin->stability());

        return $plugin;
    }

    /**
     * Resolve a process plugin by id.
     *
     * @throws \OutOfBoundsException If no process plugin with $id is registered.
     */
    public function getProcess(string $id): ProcessPluginInterface
    {
        $this->assertBooted();
        if (!isset($this->processes[$id])) {
            throw new \OutOfBoundsException(\sprintf('No process plugin registered with id %s.', var_export($id, true)));
        }
        $plugin = $this->processes[$id];
        $this->recordUse($id, $plugin->stability());

        return $plugin;
    }

    /**
     * Resolve a destination plugin by id.
     *
     * @throws \OutOfBoundsException If no destination plugin with $id is registered.
     */
    public function getDestination(string $id): DestinationPluginInterface
    {
        $this->assertBooted();
        if (!isset($this->destinations[$id])) {
            throw new \OutOfBoundsException(\sprintf('No destination plugin registered with id %s.', var_export($id, true)));
        }
        $plugin = $this->destinations[$id];
        $this->recordUse($id, $plugin->stability());

        return $plugin;
    }

    /**
     * Whether {@see boot()} has finished. Used by tests and the kernel.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Index a single plugin instance, enforcing collision and reserved-id
     * rules.
     */
    private function index(
        SourcePluginInterface|ProcessPluginInterface|DestinationPluginInterface $plugin,
    ): void {
        $id = $plugin->id();
        $fqcn = $plugin::class;

        $this->enforceReserved($id, $fqcn);

        if (isset($this->claimantByPluginId[$id])) {
            throw new MigrationPluginCollisionException(
                pluginId: $id,
                firstFqcn: $this->claimantByPluginId[$id],
                secondFqcn: $fqcn,
            );
        }
        $this->claimantByPluginId[$id] = $fqcn;

        if ($plugin instanceof SourcePluginInterface) {
            $this->sources[$id] = $plugin;
            return;
        }
        if ($plugin instanceof ProcessPluginInterface) {
            $this->processes[$id] = $plugin;
            return;
        }
        // DestinationPluginInterface — exhaustively covered by the union type.
        $this->destinations[$id] = $plugin;
    }

    /**
     * Block third-party FQCNs from re-registering framework-reserved ids.
     *
     * The whitelist matches the framework's own first-party namespaces only —
     * `Waaseyaa\Migration\Plugin\*`, `Waaseyaa\Migration\Discovery\*`, etc. The
     * test-fixture namespace (`Waaseyaa\Migration\Tests\*`) is explicitly
     * excluded so unit tests can exercise the "third-party collision" path.
     */
    private function enforceReserved(string $id, string $fqcn): void
    {
        if (!ReservedPluginIds::isReserved($id)) {
            return;
        }
        if ($this->isFrameworkFirstParty($fqcn)) {
            return;
        }

        throw new MigrationPluginCollisionException(
            pluginId: $id,
            firstFqcn: 'Waaseyaa\\Migration (reserved)',
            secondFqcn: $fqcn,
            reserved: true,
        );
    }

    /**
     * True when $fqcn sits in the framework's first-party `Waaseyaa\Migration\`
     * namespace tree but NOT inside `Waaseyaa\Migration\Tests\` (test
     * fixtures simulate third-party code).
     *
     * Anonymous classes are never first-party: PHP prefixes anonymous classes
     * that implement an interface with the interface's FQCN, so e.g.
     * `new class() implements ProcessPluginInterface {}` declared in a
     * third-party namespace still produces an FQCN beginning with
     * `Waaseyaa\Migration\Plugin\ProcessPluginInterface@anonymous`. The
     * `@anonymous` segment is the deterministic marker.
     */
    private function isFrameworkFirstParty(string $fqcn): bool
    {
        if (str_contains($fqcn, '@anonymous')) {
            return false;
        }
        if (!str_starts_with($fqcn, 'Waaseyaa\\Migration\\')) {
            return false;
        }
        if (str_starts_with($fqcn, 'Waaseyaa\\Migration\\Tests\\')) {
            return false;
        }

        return true;
    }

    /**
     * Emit a single deprecation notice per experimental plugin id per process.
     */
    private function recordUse(string $id, string $stability): void
    {
        if ($stability !== 'experimental') {
            return;
        }
        if (isset($this->deprecationFired[$id])) {
            return;
        }
        $this->deprecationFired[$id] = true;
        $this->logger->warning(
            \sprintf('Migration plugin %s is marked experimental; its behaviour may change without notice.', var_export($id, true)),
            [
                'channel' => Channels::MIGRATION_DEPRECATION,
                'plugin_id' => $id,
                'stability' => $stability,
            ],
        );
    }

    private function assertBooted(): void
    {
        if (!$this->booted) {
            throw new \LogicException('PluginRegistry::boot() must be called before plugins can be resolved.');
        }
    }
}
