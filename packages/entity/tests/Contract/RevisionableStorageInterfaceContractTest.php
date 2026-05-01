<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Waaseyaa\Entity\Storage\RevisionableStorageInterface;

/**
 * Locks `RevisionableStorageInterface`, the published spec section in
 * `docs/specs/entity-system.md`, and the canonical method roster in lockstep.
 *
 * Reflection-only — DIR-008 permits reflection in contract tests.
 *
 * Mission #824 WP04 surface B (closes #837). The test fails loudly if the
 * interface gains, loses, or reshapes a method without the spec roster being
 * updated in the same change.
 */
#[CoversNothing]
final class RevisionableStorageInterfaceContractTest extends TestCase
{
    /**
     * Canonical roster of methods declared directly on
     * `RevisionableStorageInterface` (parent `EntityStorageInterface` methods
     * are deliberately excluded — this contract covers only the revision
     * surface). Each entry pins parameter shape and return type so the test
     * catches signature drift, not just method-name drift.
     *
     * @var list<array{
     *     name: string,
     *     params: list<array{name: string, types: list<string>, allowsNull: bool}>,
     *     return: array{types: list<string>, allowsNull: bool},
     * }>
     */
    private const array EXPECTED_METHODS = [
        [
            'name' => 'loadRevision',
            'params' => [
                ['name' => 'entityId', 'types' => ['int', 'string'], 'allowsNull' => false],
                ['name' => 'revisionId', 'types' => ['int'], 'allowsNull' => false],
            ],
            'return' => ['types' => ['Waaseyaa\\Entity\\EntityInterface'], 'allowsNull' => true],
        ],
        [
            'name' => 'loadMultipleRevisions',
            'params' => [
                ['name' => 'entityId', 'types' => ['int', 'string'], 'allowsNull' => false],
                ['name' => 'revisionIds', 'types' => ['array'], 'allowsNull' => false],
            ],
            'return' => ['types' => ['array'], 'allowsNull' => false],
        ],
        [
            'name' => 'deleteRevision',
            'params' => [
                ['name' => 'entityId', 'types' => ['int', 'string'], 'allowsNull' => false],
                ['name' => 'revisionId', 'types' => ['int'], 'allowsNull' => false],
            ],
            'return' => ['types' => ['void'], 'allowsNull' => false],
        ],
        [
            'name' => 'getLatestRevisionId',
            'params' => [
                ['name' => 'entityId', 'types' => ['int', 'string'], 'allowsNull' => false],
            ],
            'return' => ['types' => ['int'], 'allowsNull' => true],
        ],
        [
            'name' => 'getRevisionIds',
            'params' => [
                ['name' => 'entityId', 'types' => ['int', 'string'], 'allowsNull' => false],
            ],
            'return' => ['types' => ['array'], 'allowsNull' => false],
        ],
    ];

    private const string SPEC_PATH = __DIR__ . '/../../../../docs/specs/entity-system.md';

    #[Test]
    public function interfaceMethodRosterMatchesDeclaration(): void
    {
        $iface = new ReflectionClass(RevisionableStorageInterface::class);

        $declared = array_map(
            static fn (ReflectionMethod $m): string => $m->getName(),
            array_filter(
                $iface->getMethods(),
                static fn (ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === RevisionableStorageInterface::class,
            ),
        );
        sort($declared);

        $expected = array_map(static fn (array $entry): string => $entry['name'], self::EXPECTED_METHODS);
        sort($expected);

        self::assertSame(
            $expected,
            $declared,
            'RevisionableStorageInterface drifted from the contract roster. '
                . 'Update self::EXPECTED_METHODS deliberately and refresh '
                . 'the matching code block in docs/specs/entity-system.md.',
        );
    }

    #[Test]
    public function eachMethodSignatureMatchesExpectedShape(): void
    {
        $iface = new ReflectionClass(RevisionableStorageInterface::class);

        foreach (self::EXPECTED_METHODS as $expected) {
            $method = $iface->getMethod($expected['name']);

            $params = $method->getParameters();
            self::assertCount(
                count($expected['params']),
                $params,
                sprintf('%s: parameter count mismatch.', $expected['name']),
            );

            foreach ($params as $i => $param) {
                $expectedParam = $expected['params'][$i];
                self::assertSame(
                    $expectedParam['name'],
                    $param->getName(),
                    sprintf('%s: parameter %d name mismatch.', $expected['name'], $i),
                );
                self::assertSame(
                    $expectedParam['types'],
                    self::reflectionTypeNames($param->getType()),
                    sprintf('%s: parameter $%s type mismatch.', $expected['name'], $param->getName()),
                );
                self::assertSame(
                    $expectedParam['allowsNull'],
                    $param->allowsNull(),
                    sprintf('%s: parameter $%s nullability mismatch.', $expected['name'], $param->getName()),
                );
            }

            self::assertSame(
                $expected['return']['types'],
                self::reflectionTypeNames($method->getReturnType()),
                sprintf('%s: return type mismatch.', $expected['name']),
            );
            self::assertSame(
                $expected['return']['allowsNull'],
                $method->getReturnType()?->allowsNull() ?? false,
                sprintf('%s: return nullability mismatch.', $expected['name']),
            );
        }
    }

    #[Test]
    public function specSectionDocumentsEveryRosterMethod(): void
    {
        $spec = file_get_contents(self::SPEC_PATH);
        self::assertNotFalse($spec, 'Cannot read entity-system.md spec.');

        $section = self::extractSpecSection($spec);
        self::assertNotSame(
            '',
            $section,
            'Could not locate the "### RevisionableStorageInterface" section in entity-system.md.',
        );

        foreach (self::EXPECTED_METHODS as $entry) {
            self::assertStringContainsString(
                'public function ' . $entry['name'] . '(',
                $section,
                sprintf(
                    'Spec section is missing method %s(). Refresh the code block in '
                        . 'docs/specs/entity-system.md to match the interface.',
                    $entry['name'],
                ),
            );
        }
    }

    /**
     * @return list<string> Sorted, FQCN-style names; primitives lower-case.
     */
    private static function reflectionTypeNames(?\ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }
        if ($type instanceof ReflectionUnionType) {
            $names = array_map(
                static fn (\ReflectionType $t): string => $t instanceof ReflectionNamedType ? $t->getName() : (string) $t,
                $type->getTypes(),
            );
            sort($names);
            return $names;
        }
        return [];
    }

    /**
     * Extract the `### RevisionableStorageInterface` section up to (but not
     * including) the next `### ` heading.
     */
    private static function extractSpecSection(string $markdown): string
    {
        $start = strpos($markdown, '### RevisionableStorageInterface');
        if ($start === false) {
            return '';
        }
        $rest = substr($markdown, $start);
        $nextHeading = strpos($rest, "\n### ", 5);
        return $nextHeading === false ? $rest : substr($rest, 0, $nextHeading);
    }
}
