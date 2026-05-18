<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent;

use Waaseyaa\AI\Agent\Enum\HitlMode;
use Waaseyaa\Foundation\Discovery\PackageManifest;

/**
 * Catalogue of {@see AgentDefinition} value objects.
 *
 * Walks the `agent_definitions` section of {@see PackageManifest}
 * (populated by {@see \Waaseyaa\Foundation\Discovery\PackageManifestCompiler})
 * on first access and rebuilds {@see AgentDefinition} VOs from the cached
 * attribute payload. Definitions discovered this way are returned as
 * immutable singletons for the request lifetime.
 *
 * @api
 */
final class AgentDefinitionRegistry
{
    /** @var array<string, AgentDefinition> */
    private array $definitions = [];

    private bool $hydrated = false;

    public function __construct(
        private readonly PackageManifest $manifest,
    ) {}

    public function get(string $id): AgentDefinition
    {
        $this->hydrate();
        if (!isset($this->definitions[$id])) {
            throw new \InvalidArgumentException(
                sprintf('AgentDefinitionRegistry: unknown agent id "%s".', $id),
            );
        }

        return $this->definitions[$id];
    }

    public function has(string $id): bool
    {
        $this->hydrate();

        return isset($this->definitions[$id]);
    }

    /**
     * @return iterable<AgentDefinition>
     */
    public function all(): iterable
    {
        $this->hydrate();

        return array_values($this->definitions);
    }

    private function hydrate(): void
    {
        if ($this->hydrated) {
            return;
        }
        $this->hydrated = true;

        foreach ($this->manifest->agentDefinitions as $entry) {
            $id = $entry['id'];
            if ($id === '') {
                continue;
            }
            $destructiveDefault = null;
            if ($entry['destructive_default'] !== null) {
                $destructiveDefault = HitlMode::tryFrom($entry['destructive_default']);
            }

            $this->definitions[$id] = new AgentDefinition(
                id: $id,
                label: $entry['label'],
                description: $entry['description'],
                prompt: $entry['prompt'],
                system: $entry['system'],
                tools: $entry['tools'],
                model: $entry['model'],
                maxIterations: $entry['max_iterations'],
                destructiveDefault: $destructiveDefault,
                requiresCapability: $entry['requires_capability'],
            );
        }
    }
}
