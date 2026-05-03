<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Discovery;

final class PackageManifest
{
    public function __construct(
        /** @var string[] */
        public readonly array $providers = [],
        /**
         * Per-package `extra.waaseyaa.migrations` declaration (per spec §15 Q9 / WP11).
         *
         * Each value is EITHER a single legacy directory path string OR
         * an ordered list of mixed FQCN namespace roots and path strings:
         *
         * - string `"migrations"` — single legacy directory path.
         * - `["Vendor\\Pkg\\Migrations\\v2", "../patches/v2"]` — ordered list.
         *
         * `MigrationLoader` walks the list in order and concatenates the
         * results; `MigrationGraph` (WP06) handles cross-entry dependency
         * edges. Per Q9, the string form is supported indefinitely — no
         * `@deprecated` notice without an ADR.
         *
         * @var array<string, string|list<string>>
         */
        public readonly array $migrations = [],
        /** @var array<string, string> */
        public readonly array $fieldTypes = [],
        /** @var array<string, class-string> */
        public readonly array $formatters = [],
        /** @var array<string, list<array{class: string, priority: int}>> */
        public readonly array $middleware = [],
        /** @var array<string, array{title: string, description?: string}> */
        public readonly array $permissions = [],
        /** @var array<class-string, string[]> */
        public readonly array $policies = [],
        /** @var array<string, array{surface: 'aggregate'|'implementation'|'tooling', activation: 'discovery'|'none'|'provider'}> */
        public readonly array $packageDeclarations = [],
        /** @var list<class-string> Classes carrying #[AsEntityType] that also implement DefinesEntityType */
        public readonly array $attributeEntityTypes = [],
    ) {}

    /**
     * Create from a cached array (loaded from storage/framework/packages.php).
     *
     * Legacy caches may still contain `commands` and `routes` keys; they are ignored (see docs/adr/0001-manifest-routes-commands-removal.md).
     *
     * @throws \InvalidArgumentException If required keys are missing or have wrong types
     */
    public static function fromArray(array $data): self
    {
        // Legacy keys — never consumed at runtime (see ADR docs/adr).
        unset($data['commands'], $data['routes']);

        $requiredKeys = ['providers', 'migrations', 'field_types', 'middleware'];
        $optionalKeys = ['permissions', 'policies', 'formatters', 'package_declarations', 'attribute_entity_types'];
        $missing = array_diff($requiredKeys, array_keys($data));

        if ($missing !== []) {
            throw new \InvalidArgumentException(sprintf(
                'PackageManifest cache is missing required keys: %s',
                implode(', ', $missing),
            ));
        }

        foreach ([...$requiredKeys, ...$optionalKeys] as $key) {
            if (isset($data[$key]) && !is_array($data[$key])) {
                throw new \InvalidArgumentException(sprintf(
                    'PackageManifest cache key "%s" must be an array, got %s',
                    $key,
                    get_debug_type($data[$key]),
                ));
            }
        }

        return new self(
            providers: $data['providers'],
            migrations: $data['migrations'],
            fieldTypes: $data['field_types'],
            formatters: $data['formatters'] ?? [],
            middleware: $data['middleware'],
            permissions: $data['permissions'] ?? [],
            policies: $data['policies'] ?? [],
            packageDeclarations: $data['package_declarations'] ?? [],
            attributeEntityTypes: $data['attribute_entity_types'] ?? [],
        );
    }

    /**
     * Export to a cacheable array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'providers' => $this->providers,
            'migrations' => $this->migrations,
            'field_types' => $this->fieldTypes,
            'formatters' => $this->formatters,
            'middleware' => $this->middleware,
            'permissions' => $this->permissions,
            'policies' => $this->policies,
            'package_declarations' => $this->packageDeclarations,
            'attribute_entity_types' => $this->attributeEntityTypes,
        ];
    }
}
