<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Mcp;

use Waaseyaa\Config\Schema\Ai\McpServersConfig;
use Waaseyaa\Config\StorageInterface as ConfigStorageInterface;

/**
 * Surfaces the `tool.mcp.<alias>.<name>` capabilities derived from
 * {@see McpServersConfig} so the access subsystem can mark them as
 * grantable without WP-07 having to mutate {@code AgentCapabilities}
 * (which is WP-02's owned surface).
 *
 * The actual remote-server enumeration is delegated to
 * {@see McpClientToolSource::discoveredCapabilities()}; this class is
 * the thin adapter the access seed mechanism consumes. For static
 * (configuration-only) grants we also surface the per-server prefix
 * itself (e.g. `tool.mcp.github.*`) so role grants can match a wildcard.
 *
 * @api
 */
final class McpCapabilitiesSource
{
    public function __construct(
        private readonly ConfigStorageInterface $configStorage,
        private readonly ?McpClientToolSource $toolSource = null,
    ) {}

    /**
     * Capability *prefixes* (one per configured server), derived from
     * config without touching the network. These are always safe to seed
     * because they are pure configuration data.
     *
     * @return list<string>
     */
    public function configuredPrefixes(): array
    {
        $raw = $this->configStorage->read(McpServersConfig::CONFIG_NAME);
        $rows = McpServersConfig::normalise($raw);

        $prefixes = [];
        foreach ($rows as $row) {
            if (!$row['enabled']) {
                continue;
            }
            $prefixes[] = $row['capability_prefix'];
        }

        return array_values(array_unique($prefixes));
    }

    /**
     * Concrete `tool.mcp.<alias>.<name>` capabilities discovered by
     * actually calling the remote servers' `tools/list` endpoint. Returns
     * an empty list (with no error) if no tool source is wired or the
     * servers are unavailable — the per-prefix wildcard from
     * {@see configuredPrefixes()} remains the durable grant target.
     *
     * @return list<string>
     */
    public function discoveredCapabilities(): array
    {
        if ($this->toolSource === null) {
            return [];
        }

        return $this->toolSource->discoveredCapabilities();
    }
}
