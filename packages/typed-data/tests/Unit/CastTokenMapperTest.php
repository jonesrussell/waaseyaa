<?php

declare(strict_types=1);

namespace Waaseyaa\TypedData\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\TypedData\CastTokenMapper;
use Waaseyaa\TypedData\TypedDataManager;

/**
 * @covers \Waaseyaa\TypedData\CastTokenMapper
 */
final class CastTokenMapperTest extends TestCase
{
    #[Test]
    public function maps_scalar_tokens_for_typed_data_manager(): void
    {
        $manager = new TypedDataManager();
        foreach (['int' => 'integer', 'float' => 'float', 'bool' => 'boolean', 'string' => 'string'] as $token => $expected) {
            $mapped = CastTokenMapper::toDataType($token);
            self::assertSame($expected, $mapped);
            $manager->createDataDefinition($mapped);
        }
    }

    #[Test]
    public function array_and_json_return_null(): void
    {
        self::assertNull(CastTokenMapper::toDataType('array'));
        self::assertNull(CastTokenMapper::toDataType('json'));
    }

    #[Test]
    public function unknown_token_returns_null(): void
    {
        self::assertNull(CastTokenMapper::toDataType('datetime_immutable'));
        self::assertNull(CastTokenMapper::toDataType('unknown'));
    }
}
