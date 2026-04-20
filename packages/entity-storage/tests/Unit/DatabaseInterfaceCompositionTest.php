<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DatabaseInterface;

/**
 * Compositional gate that would have caught the alpha.150 quoteIdentifier
 * regression before it shipped.
 *
 * Backstory: alpha.150 added `$this->database->quoteIdentifier(...)` calls
 * inside SqlEntityQuery's bundle JOIN code. The method existed on
 * Waaseyaa\Database\DatabaseInterface and on DBALDatabase in the framework
 * source tree, so all in-tree tests passed. But entity-storage's composer
 * constraint on waaseyaa/database-legacy was the bare `^0.1`, which let
 * Composer co-resolve a stale alpha.145 sibling that pre-dated the
 * interface extension. The first consumer to upgrade fataled with
 * "Call to undefined method DBALDatabase::quoteIdentifier()" the moment a
 * bundle-scoped query ran.
 *
 * This test enforces two compositional invariants so the same class of
 * defect cannot ship again:
 *
 *   1. Every method called on $this->database anywhere in entity-storage
 *      source must be declared on DatabaseInterface. New private helpers
 *      added to DBALDatabase that are not on the interface will fail this
 *      test, forcing a contract decision.
 *
 *   2. entity-storage's composer.json must anchor sibling waaseyaa/*
 *      packages to a pre-release-tagged floor (CP005 enforces this for
 *      the monorepo; this test re-asserts at the package level so a
 *      regressed entity-storage manifest fails locally before lint runs).
 */
#[CoversNothing]
final class DatabaseInterfaceCompositionTest extends TestCase
{
    #[Test]
    public function everyMethodCalledOnTheDatabaseFieldIsDeclaredOnTheInterface(): void
    {
        $sourceDir = __DIR__ . '/../../src';
        $sources = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[] = $file->getPathname();
            }
        }

        $calledMethods = [];
        foreach ($sources as $source) {
            $code = (string) file_get_contents($source);
            preg_match_all('/\$this->database->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $code, $matches);
            foreach ($matches[1] as $method) {
                $calledMethods[$method] ??= [];
                $calledMethods[$method][] = basename($source);
            }
        }

        self::assertNotEmpty(
            $calledMethods,
            'Expected to find at least one $this->database->...() call in entity-storage source.',
        );

        $reflection = new \ReflectionClass(DatabaseInterface::class);
        $declared = array_map(static fn(\ReflectionMethod $m) => $m->getName(), $reflection->getMethods());

        $missing = [];
        foreach ($calledMethods as $method => $callsites) {
            if (!in_array($method, $declared, true)) {
                $missing[$method] = $callsites;
            }
        }

        self::assertSame(
            [],
            $missing,
            sprintf(
                'These methods are called on $this->database in entity-storage but are not '
                . 'declared on Waaseyaa\\Database\\DatabaseInterface: %s. '
                . 'Either add them to the interface (so all implementations and floor '
                . 'versions support them) or refactor the callsite to use a method that is '
                . 'on the interface. Otherwise older sibling versions of '
                . 'waaseyaa/database-legacy that satisfy the constraint floor will fatal '
                . 'at runtime — the alpha.150 quoteIdentifier regression class.',
                json_encode($missing, \JSON_PRETTY_PRINT),
            ),
        );
    }

    #[Test]
    public function composerConstraintsOnSiblingPackagesAreAnchoredToPreReleaseFloor(): void
    {
        $manifestPath = __DIR__ . '/../../composer.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: \JSON_THROW_ON_ERROR);

        $unanchored = [];
        foreach (['require', 'require-dev'] as $section) {
            foreach (($manifest[$section] ?? []) as $pkg => $version) {
                if (!is_string($pkg) || !is_string($version)) {
                    continue;
                }
                if (!str_starts_with($pkg, 'waaseyaa/')) {
                    continue;
                }
                if ($version === '@dev' || str_contains($version, '*')) {
                    continue;
                }
                if (!preg_match('/-(alpha|beta|rc|dev)\./', $version)) {
                    $unanchored[$pkg] = $version;
                }
            }
        }

        self::assertSame(
            [],
            $unanchored,
            'These sibling waaseyaa/* constraints in packages/entity-storage/composer.json '
            . 'lack a pre-release-anchored floor: ' . json_encode($unanchored)
            . '. Composer can resolve a stale sibling that pre-dates methods entity-storage '
            . 'now calls — the alpha.150 regression class. Anchor to the current floor '
            . '(for example ^0.1.0-alpha.150).',
        );
    }
}
