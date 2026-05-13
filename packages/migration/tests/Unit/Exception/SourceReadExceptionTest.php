<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Exception\SourceReadException;

#[CoversClass(SourceReadException::class)]
final class SourceReadExceptionTest extends TestCase
{
    #[Test]
    public function exposes_source_id_and_migration_id(): void
    {
        $exception = new SourceReadException(
            sourceId: 'wordpress_post',
            migrationId: 'wp_posts_to_teachings',
            reason: 'HTTP 503 from upstream',
        );

        self::assertSame('wordpress_post', $exception->sourceId);
        self::assertSame('wp_posts_to_teachings', $exception->migrationId);
    }

    #[Test]
    public function carries_stable_code_constant(): void
    {
        self::assertSame('SOURCE_IO_ERROR', SourceReadException::CODE);
    }

    #[Test]
    public function message_uses_documented_format(): void
    {
        $exception = new SourceReadException(
            sourceId: 'wordpress_post',
            migrationId: 'wp_posts_to_teachings',
            reason: 'HTTP 503 from upstream',
        );

        self::assertSame(
            "Source plugin 'wordpress_post' failed for migration 'wp_posts_to_teachings': HTTP 503 from upstream",
            $exception->getMessage(),
        );
    }

    #[Test]
    public function wraps_previous_throwable(): void
    {
        $previous = new \RuntimeException('upstream went away');
        $exception = new SourceReadException(
            sourceId: 'wordpress_post',
            migrationId: 'wp_posts_to_teachings',
            reason: 'IO failure',
            previous: $previous,
        );

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function rejects_empty_source_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceReadException(sourceId: '', migrationId: 'm', reason: 'r');
    }

    #[Test]
    public function rejects_empty_migration_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceReadException(sourceId: 's', migrationId: '', reason: 'r');
    }

    #[Test]
    public function rejects_empty_reason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceReadException(sourceId: 's', migrationId: 'm', reason: '');
    }

    #[Test]
    public function is_a_runtime_exception(): void
    {
        $exception = new SourceReadException('s', 'm', 'r');

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }
}
