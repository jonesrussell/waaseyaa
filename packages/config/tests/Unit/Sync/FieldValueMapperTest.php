<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Exception\ConfigSerializationException;
use Waaseyaa\Config\Sync\FieldValueMapper;

#[CoversClass(FieldValueMapper::class)]
final class FieldValueMapperTest extends TestCase
{
    private FieldValueMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new FieldValueMapper();
    }

    #[Test]
    public function stringRoundTrip(): void
    {
        self::assertSame('Coordinator', $this->mapper->toYamlValue('string', 'label', 'Coordinator'));
        self::assertSame('42', $this->mapper->toYamlValue('string', 'label', 42));
    }

    #[Test]
    public function intRoundTrip(): void
    {
        self::assertSame(10, $this->mapper->toYamlValue('int', 'weight', 10));
        self::assertSame(-5, $this->mapper->toYamlValue('int', 'weight', '-5'));
    }

    #[Test]
    public function intRejectsNonNumericString(): void
    {
        $this->expectException(ConfigSerializationException::class);
        $this->mapper->toYamlValue('int', 'weight', 'ten');
    }

    #[Test]
    public function boolRoundTrip(): void
    {
        self::assertTrue($this->mapper->toYamlValue('bool', 'enabled', true));
        self::assertFalse($this->mapper->toYamlValue('bool', 'enabled', false));
        self::assertTrue($this->mapper->toYamlValue('bool', 'enabled', 1));
        self::assertFalse($this->mapper->toYamlValue('bool', 'enabled', 0));
        self::assertTrue($this->mapper->toYamlValue('bool', 'enabled', 'true'));
        self::assertFalse($this->mapper->toYamlValue('bool', 'enabled', 'false'));
    }

    #[Test]
    public function boolRejectsArbitraryString(): void
    {
        $this->expectException(ConfigSerializationException::class);
        $this->mapper->toYamlValue('bool', 'enabled', 'maybe');
    }

    #[Test]
    public function datetimeAcceptsIsoString(): void
    {
        $result = $this->mapper->toYamlValue('datetime', 'created_at', '2026-05-15T18:42:01+00:00');
        self::assertSame('2026-05-15T18:42:01+00:00', $result);
    }

    #[Test]
    public function datetimeAcceptsDateTimeObject(): void
    {
        $dt = new \DateTimeImmutable('2026-05-15T18:42:01+00:00');
        $result = $this->mapper->toYamlValue('datetime', 'created_at', $dt);
        self::assertSame('2026-05-15T18:42:01+00:00', $result);
    }

    #[Test]
    public function datetimeNormalisesToAtom(): void
    {
        $result = $this->mapper->toYamlValue('datetime', 'created_at', '2026-05-15 18:42:01 UTC');
        self::assertMatchesRegularExpression('/^2026-05-15T18:42:01\+00:00$/', $result);
    }

    #[Test]
    public function datetimeRejectsGibberish(): void
    {
        $this->expectException(ConfigSerializationException::class);
        $this->mapper->toYamlValue('datetime', 'created_at', 'not-a-date');
    }

    #[Test]
    public function jsonAcceptsAssociativeAndSortsKeys(): void
    {
        $result = $this->mapper->toYamlValue('json', 'settings', ['theme' => 'dark', 'retention_days' => 30]);
        self::assertSame(['retention_days' => 30, 'theme' => 'dark'], $result);
    }

    #[Test]
    public function jsonRecursesIntoNestedMaps(): void
    {
        $input = ['outer' => ['z' => 1, 'a' => 2], 'alpha' => 'x'];
        $result = $this->mapper->toYamlValue('json', 'settings', $input);
        self::assertSame(['alpha' => 'x', 'outer' => ['a' => 2, 'z' => 1]], $result);
    }

    #[Test]
    public function jsonPreservesListOrder(): void
    {
        $result = $this->mapper->toYamlValue('json', 'items', ['c', 'a', 'b']);
        self::assertSame(['c', 'a', 'b'], $result);
    }

    #[Test]
    public function jsonAcceptsNull(): void
    {
        self::assertNull($this->mapper->toYamlValue('json', 'settings', null));
    }

    #[Test]
    public function jsonRejectsScalar(): void
    {
        $this->expectException(ConfigSerializationException::class);
        $this->mapper->toYamlValue('json', 'settings', 'plain');
    }

    #[Test]
    public function textRoundTripIncludingMultiline(): void
    {
        $multi = "line one\nline two\nline three";
        self::assertSame($multi, $this->mapper->toYamlValue('text', 'description', $multi));
    }

    #[Test]
    public function uuidRoundTrip(): void
    {
        $u = '0193abcd-7c4d-7000-8b6e-1a2b3c4d5e6f';
        self::assertSame($u, $this->mapper->toYamlValue('uuid', 'id', $u));
    }

    #[Test]
    public function entityReferenceAcceptsValidRef(): void
    {
        self::assertSame(
            'role.member',
            $this->mapper->toYamlValue('entity_reference', 'default_role', 'role.member'),
        );
    }

    #[Test]
    public function entityReferenceRejectsMalformedRef(): void
    {
        $this->expectException(ConfigSerializationException::class);
        $this->mapper->toYamlValue('entity_reference', 'default_role', 'no_dot_here');
    }

    #[Test]
    public function fieldListAcceptsScalarSequence(): void
    {
        $list = ['calendar.administer', 'membership.approve'];
        self::assertSame($list, $this->mapper->toYamlValue('field_list', 'permissions', $list));
    }

    #[Test]
    public function fieldListRejectsAssociativeArray(): void
    {
        $this->expectException(ConfigSerializationException::class);
        $this->mapper->toYamlValue('field_list', 'permissions', ['a' => 1]);
    }

    #[Test]
    public function fieldListRejectsArrayOfArrays(): void
    {
        $this->expectException(ConfigSerializationException::class);
        $this->mapper->toYamlValue('field_list', 'permissions', [['nested']]);
    }

    #[Test]
    public function unknownTypeThrows(): void
    {
        $this->expectException(ConfigSerializationException::class);
        $this->mapper->toYamlValue('totally_unknown', 'x', 'y');
    }

    #[Test]
    public function fromYamlValueMirrorsToYamlValue(): void
    {
        // The current type table has symmetric round-tripping.
        $cases = [
            ['string', 'label', 'Coordinator', 'Coordinator'],
            ['int', 'weight', 10, 10],
            ['bool', 'enabled', true, true],
            ['uuid', 'id', '0193abcd-7c4d-7000-8b6e-1a2b3c4d5e6f', '0193abcd-7c4d-7000-8b6e-1a2b3c4d5e6f'],
            ['entity_reference', 'default_role', 'role.member', 'role.member'],
        ];
        foreach ($cases as [$type, $name, $input, $expected]) {
            self::assertSame($expected, $this->mapper->fromYamlValue($type, $name, $input));
        }
    }

    #[Test]
    public function mapFieldsToYamlSortsAndCoercesAll(): void
    {
        $types = [
            'description' => 'text',
            'id' => 'string',
            'label' => 'string',
            'permissions' => 'field_list',
            'weight' => 'int',
        ];
        $fields = [
            'weight' => 10,
            'label' => 'Coordinator',
            'permissions' => ['calendar.administer'],
            'id' => 'coordinator',
            'description' => 'Coordinators manage community calendars.',
        ];

        $result = $this->mapper->mapFieldsToYaml($types, $fields);

        self::assertSame(
            ['description', 'id', 'label', 'permissions', 'weight'],
            array_keys($result),
            'mapFieldsToYaml returns alphabetically-sorted keys.',
        );
        self::assertSame(10, $result['weight']);
    }

    #[Test]
    public function mapFieldsToYamlRejectsUnknownField(): void
    {
        $this->expectException(ConfigSerializationException::class);
        $this->mapper->mapFieldsToYaml(['label' => 'string'], ['label' => 'x', 'mystery' => 'y']);
    }
}
