<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration\Dag;

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;

/**
 * A single vertex in the unified migration DAG.
 *
 * Nodes carry their identity (`id`, `package`), kind discriminator
 * (`kind`), declared dependencies (`dependencies`), and a back-reference
 * to the underlying authoring object (`legacy` OR `v2` — exactly one is
 * non-null per kind). The Migrator dispatches on `kind` and reads the
 * non-null source object to apply the migration.
 *
 * Construct via the static factories ({@see fromLegacy()}, {@see fromV2()})
 * — they enforce the kind/source invariant and pull the right metadata
 * out of each authoring contract.
 */
final readonly class MigrationNode
{
    /**
     * @param list<string> $dependencies migration_ids and/or package names
     */
    public function __construct(
        public string $id,
        public string $package,
        public MigrationKind $kind,
        public array $dependencies,
        public ?Migration $legacy = null,
        public ?MigrationInterfaceV2 $v2 = null,
    ) {
        $hasLegacy = $this->legacy !== null;
        $hasV2 = $this->v2 !== null;

        if ($hasLegacy === $hasV2) {
            throw new \InvalidArgumentException(
                'MigrationNode requires exactly one of {legacy, v2} sources.',
            );
        }

        if ($hasLegacy && $kind !== MigrationKind::Legacy) {
            throw new \InvalidArgumentException(
                'MigrationNode kind disagrees with the supplied source: legacy source requires Legacy kind.',
            );
        }

        if ($hasV2 && $kind !== MigrationKind::V2) {
            throw new \InvalidArgumentException(
                'MigrationNode kind disagrees with the supplied source: v2 source requires V2 kind.',
            );
        }
    }

    /**
     * Build a node from a legacy {@see Migration}, given the ledger
     * name + composer package the {@see \Waaseyaa\Foundation\Migration\MigrationLoader}
     * already assigns.
     */
    public static function fromLegacy(string $name, string $package, Migration $migration): self
    {
        return new self(
            id: $name,
            package: $package,
            kind: MigrationKind::Legacy,
            dependencies: $migration->after,
            legacy: $migration,
        );
    }

    /**
     * Build a node from a v2 migration. Identity, package, and edges
     * come from the {@see MigrationInterfaceV2} contract — there is no
     * external mapping.
     */
    public static function fromV2(MigrationInterfaceV2 $migration): self
    {
        return new self(
            id: $migration->migrationId(),
            package: $migration->package(),
            kind: MigrationKind::V2,
            dependencies: $migration->dependencies(),
            v2: $migration,
        );
    }
}
