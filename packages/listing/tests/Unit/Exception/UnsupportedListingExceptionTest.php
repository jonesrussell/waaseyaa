<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waaseyaa\Listing\Exception\UnsupportedListingException;

#[CoversClass(UnsupportedListingException::class)]
final class UnsupportedListingExceptionTest extends TestCase
{
    #[Test]
    public function extendsRuntimeException(): void
    {
        $e = new UnsupportedListingException('recent', 'weight', 'no supportsQuery');
        self::assertInstanceOf(RuntimeException::class, $e);
    }

    #[Test]
    public function carriesAllContextFields(): void
    {
        $e = new UnsupportedListingException('recent', 'weight', 'backend rejects query');

        self::assertSame('recent', $e->listingId);
        self::assertSame('weight', $e->fieldName);
        self::assertSame('backend rejects query', $e->reason);
    }

    #[Test]
    public function fieldNameIsNullableWhenNotAttributable(): void
    {
        $e = new UnsupportedListingException('recent', null, 'page size exceeds cap');
        self::assertNull($e->fieldName);
    }

    #[Test]
    public function messageMentionsListingIdReasonAndFieldWhenSet(): void
    {
        $e = new UnsupportedListingException('recent', 'weight', 'no supportsQuery');
        $msg = $e->getMessage();

        self::assertStringContainsString('recent', $msg);
        self::assertStringContainsString('weight', $msg);
        self::assertStringContainsString('no supportsQuery', $msg);
    }

    #[Test]
    public function messageWithoutFieldDoesNotMentionFieldFragment(): void
    {
        $e = new UnsupportedListingException('recent', null, 'page size exceeds cap');
        self::assertStringContainsString('page size exceeds cap', $e->getMessage());
        self::assertStringNotContainsString('field "', $e->getMessage());
    }

    #[Test]
    public function previousIsPropagated(): void
    {
        $previous = new \DomainException('downstream');
        $e = new UnsupportedListingException('recent', null, 'boom', $previous);
        self::assertSame($previous, $e->getPrevious());
    }
}
