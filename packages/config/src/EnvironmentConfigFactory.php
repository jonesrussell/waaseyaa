<?php

declare(strict_types=1);

namespace Waaseyaa\Config;

/**
 * Config factory decorator that provides environment-based config overrides.
 *
 * Resolution order (last wins):
 * 1. Base config from inner factory (config/sync/{name}.yml)
 * 2. Environment overlay from envStorage (config/environments/{WAASEYAA_ENV}/{name}.yml)
 * 3. Environment variables with WAASEYAA_CONFIG_* prefix (runtime overrides)
 *
 * Env var naming convention:
 *   WAASEYAA_CONFIG_{CONFIG_NAME}__{PROPERTY_PATH}
 *
 * Where:
 * - CONFIG_NAME: the config name with dots replaced by underscores, uppercased
 *   e.g., "system.site" -> "SYSTEM_SITE"
 * - PROPERTY_PATH: the property path with dots replaced by double underscores
 *   e.g., "site_name" -> "SITE_NAME", "connection.host" -> "CONNECTION__HOST"
 * - The config name and property path are separated by double underscore "__"
 *
 * Example: WAASEYAA_CONFIG_SYSTEM_SITE__SITE_NAME overrides system.site -> site_name
 */
final class EnvironmentConfigFactory implements ConfigFactoryInterface
{
    /** @var \Closure(string): ?string */
    private readonly \Closure $envVarResolver;

    /**
     * @param ConfigFactoryInterface $inner The base config factory.
     * @param StorageInterface $envStorage Storage for environment-specific overrides.
     * @param string $environment The current environment name (e.g., "local", "staging", "production").
     * @param string $envVarPrefix Prefix for environment variable overrides.
     * @param (callable(string): ?string)|null $envVarResolver Custom env var resolver (defaults to getenv()).
     */
    public function __construct(
        private readonly ConfigFactoryInterface $inner,
        private readonly StorageInterface $envStorage,
        private readonly string $environment,
        private readonly string $envVarPrefix = 'WAASEYAA_CONFIG_',
        ?callable $envVarResolver = null,
    ) {
        $this->envVarResolver = $envVarResolver !== null
            ? \Closure::fromCallable($envVarResolver)
            : static fn(string $name): ?string => (($v = getenv($name)) !== false) ? $v : null;
    }

    public function get(string $name): ConfigInterface
    {
        $baseConfig = $this->inner->get($name);
        $baseData = $baseConfig->getRawData();

        if ($baseConfig->isNew() && !$this->envStorage->exists($name)) {
            return $baseConfig;
        }

        // Layer 2: Apply environment overlay
        $envData = $this->envStorage->read($name);
        if ($envData !== false) {
            $baseData = array_replace_recursive($baseData, $envData);
        }

        // Layer 3: Apply env var overrides
        $baseData = $this->applyEnvVarOverrides($name, $baseData);

        return new Config(
            name: $name,
            storage: $this->envStorage,
            data: $baseData,
            immutable: true,
            isNew: false,
        );
    }

    public function getEditable(string $name): ConfigInterface
    {
        return $this->inner->getEditable($name);
    }

    public function loadMultiple(array $names): array
    {
        $configs = [];

        foreach ($names as $name) {
            $configs[$name] = $this->get($name);
        }

        return $configs;
    }

    public function rename(string $oldName, string $newName): static
    {
        $this->inner->rename($oldName, $newName);

        return $this;
    }

    public function listAll(string $prefix = ''): array
    {
        return $this->inner->listAll($prefix);
    }

    /**
     * Get the current environment name.
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Apply environment variable overrides to config data.
     *
     * @param string $configName The config name.
     * @param array<string, mixed> $data The current config data.
     * @return array<string, mixed> The data with env var overrides applied.
     */
    private function applyEnvVarOverrides(string $configName, array $data): array
    {
        // Convert config name to env var prefix format:
        // "system.site" -> "SYSTEM_SITE"
        $configPrefix = strtoupper(str_replace('.', '_', $configName));

        // Check all top-level keys and nested keys
        $this->resolveEnvVarsRecursive(
            $data,
            $this->envVarPrefix . $configPrefix,
        );

        return $data;
    }

    /**
     * Recursively check for env var overrides matching the key path.
     *
     * @param array<string, mixed> $data Reference to data being modified.
     * @param string $envPrefix The current env var prefix being built up.
     */
    private function resolveEnvVarsRecursive(array &$data, string $envPrefix): void
    {
        foreach ($data as $key => &$value) {
            // Build the env var name for this key
            // Keys use double underscore as separator between config name and path
            $envKey = $envPrefix . '__' . strtoupper($key);

            if (is_array($value) && $value !== [] && !array_is_list($value)) {
                // Recurse into nested associative arrays
                $this->resolveEnvVarsRecursive($value, $envKey);
            }

            // Check for a direct env var override
            $envValue = ($this->envVarResolver)($envKey);
            if ($envValue !== null) {
                $value = $envValue;
            }
        }
    }
}
