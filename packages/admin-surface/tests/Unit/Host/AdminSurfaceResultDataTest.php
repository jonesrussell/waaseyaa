<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Tests\Unit\Host;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;

#[CoversClass(AdminSurfaceResultData::class)]
final class AdminSurfaceResultDataTest extends TestCase
{
    #[Test]
    public function successWithData(): void
    {
        $result = AdminSurfaceResultData::success(['name' => 'Test']);

        $array = $result->toArray();

        self::assertTrue($array['ok']);
        self::assertSame(['name' => 'Test'], $array['data']);
        self::assertArrayNotHasKey('error', $array);
    }

    #[Test]
    public function successWithMeta(): void
    {
        $result = AdminSurfaceResultData::success(
            ['id' => '1'],
            ['total' => 42],
        );

        $array = $result->toArray();

        self::assertTrue($array['ok']);
        self::assertSame(['total' => 42], $array['meta']);
    }

    #[Test]
    public function errorWithStatusAndTitle(): void
    {
        $result = AdminSurfaceResultData::error(404, 'Not Found');

        $array = $result->toArray();

        self::assertFalse($array['ok']);
        self::assertSame(404, $array['error']['status']);
        self::assertSame('Not Found', $array['error']['title']);
        self::assertArrayNotHasKey('detail', $array['error']);
        self::assertArrayNotHasKey('data', $array);
    }

    #[Test]
    public function errorWithDetail(): void
    {
        $result = AdminSurfaceResultData::error(
            422,
            'Validation Failed',
            'The title field is required.',
        );

        $array = $result->toArray();

        self::assertFalse($array['ok']);
        self::assertSame('Validation Failed', $array['error']['title']);
        self::assertSame('The title field is required.', $array['error']['detail']);
    }
}
