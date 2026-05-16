<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waaseyaa\Listing\Exception\UnknownListingException;

#[CoversClass(UnknownListingException::class)]
final class UnknownListingExceptionTest extends TestCase
{
    #[Test]
    public function extendsRuntimeException(): void
    {
        $e = new UnknownListingException('recent');
        self::assertInstanceOf(RuntimeException::class, $e);
    }

    #[Test]
    public function carriesListingId(): void
    {
        $e = new UnknownListingException('recent');
        self::assertSame('recent', $e->listingId);
    }

    #[Test]
    public function messageMentionsListingId(): void
    {
        $e = new UnknownListingException('recent');
        self::assertStringContainsString('recent', $e->getMessage());
    }
}
