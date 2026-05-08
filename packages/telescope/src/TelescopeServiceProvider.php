<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Telescope\CodifiedContext\CodifiedContextObserver;
use Waaseyaa\Telescope\Recorder\CacheRecorder;
use Waaseyaa\Telescope\Recorder\EventRecorder;
use Waaseyaa\Telescope\Recorder\QueryRecorder;
use Waaseyaa\Telescope\Recorder\RequestRecorder;
use Waaseyaa\Telescope\Storage\SqliteTelescopeStore;
use Waaseyaa\Telescope\Storage\TelescopeStoreInterface;

/**
 * Service provider that registers all telescope recorders.
 *
 * Configures the telescope storage backend and wires up all
 * recorders based on provided configuration.
 *
 * Also acts as a Waaseyaa service provider so that the kernel
 * auto-discovers and binds this class as a singleton, making it
 * available for injection (e.g. TelescopeRequestMiddleware).
 */
final class TelescopeServiceProvider extends ServiceProvider
{
    private readonly TelescopeStoreInterface $store;
    private ?QueryRecorder $queryRecorder = null;
    private ?EventRecorder $eventRecorder = null;
    private ?RequestRecorder $requestRecorder = null;
    private ?CacheRecorder $cacheRecorder = null;
    private ?CodifiedContextObserver $ccObserver = null;

    /** @var array<string, mixed> */
    private readonly array $telescopeConfig;

    /**
     * @param array<string, mixed> $telescopeConfig Telescope configuration.
     */
    public function __construct(
        array $telescopeConfig = [],
        ?TelescopeStoreInterface $store = null,
    ) {
        $this->telescopeConfig = $telescopeConfig;
        $this->store = $store ?? $this->createDefaultStore();
    }

    /**
     * Bind this instance as the canonical singleton so middleware and other
     * framework consumers can resolve it via DI.
     */
    public function register(): void
    {
        $this->singleton(self::class, fn() => $this);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->telescopeConfig['enabled'] ?? true);
    }

    public function getStore(): TelescopeStoreInterface
    {
        return $this->store;
    }

    public function getQueryRecorder(): ?QueryRecorder
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if (!$this->isRecordingEnabled('queries')) {
            return null;
        }

        if ($this->queryRecorder === null) {
            $this->queryRecorder = new QueryRecorder(
                store: $this->store,
                slowQueryThreshold: (float) ($this->telescopeConfig['record']['slow_query_threshold'] ?? 100.0),
                slowQueriesOnly: (bool) ($this->telescopeConfig['record']['slow_queries_only'] ?? false),
            );
        }

        return $this->queryRecorder;
    }

    public function getEventRecorder(): ?EventRecorder
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if (!$this->isRecordingEnabled('events')) {
            return null;
        }

        if ($this->eventRecorder === null) {
            $this->eventRecorder = new EventRecorder(store: $this->store);
        }

        return $this->eventRecorder;
    }

    public function getRequestRecorder(): ?RequestRecorder
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if (!$this->isRecordingEnabled('requests')) {
            return null;
        }

        if ($this->requestRecorder === null) {
            $this->requestRecorder = new RequestRecorder(
                store: $this->store,
                ignorePaths: $this->telescopeConfig['ignore_paths'] ?? [],
            );
        }

        return $this->requestRecorder;
    }

    public function getCacheRecorder(): ?CacheRecorder
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if (!$this->isRecordingEnabled('cache')) {
            return null;
        }

        if ($this->cacheRecorder === null) {
            $this->cacheRecorder = new CacheRecorder(store: $this->store);
        }

        return $this->cacheRecorder;
    }

    public function getCodifiedContextObserver(): ?CodifiedContextObserver
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if (!$this->isAgentContextRecordingEnabled()) {
            return null;
        }

        if ($this->ccObserver === null) {
            $this->ccObserver = new CodifiedContextObserver($this->store);
        }

        return $this->ccObserver;
    }

    private function isRecordingEnabled(string $recorder): bool
    {
        return (bool) ($this->telescopeConfig['record'][$recorder] ?? true);
    }

    /**
     * Agent-context (codified-context) observer toggle.
     *
     * When `record.agent_context` is present it wins; otherwise the legacy
     * `record.codified_context` key is consulted.
     */
    private function isAgentContextRecordingEnabled(): bool
    {
        $record = $this->telescopeConfig['record'] ?? [];
        if (!is_array($record)) {
            return true;
        }

        if (array_key_exists('agent_context', $record)) {
            return (bool) $record['agent_context'];
        }

        return (bool) ($record['codified_context'] ?? true);
    }

    private function createDefaultStore(): TelescopeStoreInterface
    {
        $storagePath = $this->telescopeConfig['storage']['path'] ?? null;

        if ($storagePath !== null) {
            return SqliteTelescopeStore::createFromPath($storagePath);
        }

        return SqliteTelescopeStore::createInMemory();
    }
}
