<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Fixtures;

use Waaseyaa\Config\Sync\ConfigSyncFile;
use Waaseyaa\Config\Sync\ConfigSyncFileSourceInterface;

/**
 * Minoo-shaped round-trip fixture (FR-054, FR-055).
 *
 * Exercises a realistic three-entity mix patterned on the production Minoo
 * config catalogue:
 *
 *  - `role.admin` — leaf role (no dependencies).
 *  - `role.member` — leaf role.
 *  - `taxonomy_vocabulary.tags` — independent leaf vocabulary.
 *  - `menu.main` — composite that depends on `role.admin` (only admins may
 *    see the main admin menu).
 *
 * The shape is the smallest one that gives the dependency resolver real
 * non-trivial work to do (two topological layers, lex tie-breaking, a
 * cross-type dependency) while still being self-contained.
 *
 * Callers either feed the fixture into a {@see \Waaseyaa\Config\Sync\ConfigExporter}
 * (via {@see self::asSource()}) or pre-seed a
 * {@see \Waaseyaa\Config\Sync\ConfigSyncRepository} with {@see self::files()}
 * for import round-trip assertions.
 *
 * @api
 */
final class MinooRoundTripFixture
{
    /**
     * Full set of fixture entities, keyed by ref. Iteration order is the
     * canonical "export" order — alphabetical by ref, matching what the
     * real active-store walker delivers (FR-008 stable ordering).
     *
     * @return array<string, ConfigSyncFile>
     */
    public static function files(): array
    {
        $entries = [
            self::makeFile('menu', 'main', ['role.admin'], [
                'description' => 'Primary navigation',
                'label' => 'Main',
            ]),
            self::makeFile('role', 'admin', [], [
                'label' => 'Admin',
                'permissions' => ['administer site', 'edit any node'],
            ]),
            self::makeFile('role', 'member', [], [
                'label' => 'Member',
                'permissions' => ['access content'],
            ]),
            self::makeFile('taxonomy_vocabulary', 'tags', [], [
                'description' => 'Free-form tags',
                'label' => 'Tags',
            ]),
        ];

        $byRef = [];
        foreach ($entries as $file) {
            $byRef[$file->ref()] = $file;
        }

        return $byRef;
    }

    /**
     * Same data as {@see self::files()} but exposed via the iteration surface
     * the exporter actually consumes.
     */
    public static function asSource(): ConfigSyncFileSourceInterface
    {
        $files = array_values(self::files());

        return new class($files) implements ConfigSyncFileSourceInterface {
            /** @param list<ConfigSyncFile> $files */
            public function __construct(private readonly array $files) {}

            public function iterate(): iterable
            {
                yield from $this->files;
            }
        };
    }

    /**
     * Produce a mutated copy of the fixture with `role.admin` relabelled.
     * Used by the round-trip "modify in sync store" tests (FR-054).
     *
     * @return array<string, ConfigSyncFile>
     */
    public static function mutated(): array
    {
        $files = self::files();
        $files['role.admin'] = new ConfigSyncFile(
            entityType: 'role',
            entityId: 'admin',
            uuid: ConfigSyncFile::deterministicUuid('role', 'admin'),
            dependencies: [],
            langcode: 'en',
            fields: [
                'label' => 'Administrator',
                'permissions' => ['administer site', 'edit any node'],
            ],
        );

        return $files;
    }

    /**
     * @param list<string>         $dependencies
     * @param array<string, mixed> $fields
     */
    private static function makeFile(
        string $entityType,
        string $entityId,
        array $dependencies,
        array $fields,
    ): ConfigSyncFile {
        ksort($fields, \SORT_STRING);

        return new ConfigSyncFile(
            entityType: $entityType,
            entityId: $entityId,
            uuid: ConfigSyncFile::deterministicUuid($entityType, $entityId),
            dependencies: $dependencies,
            langcode: 'en',
            fields: $fields,
        );
    }
}
