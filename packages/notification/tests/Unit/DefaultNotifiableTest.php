<?php

declare(strict_types=1);

namespace Waaseyaa\Notification\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Notification\DefaultNotifiable;

/**
 * @covers \Waaseyaa\Notification\DefaultNotifiable
 * @covers \Waaseyaa\Notification\NotifiableTrait
 */
final class DefaultNotifiableTest extends TestCase
{
    #[Test]
    public function routeNotificationForResolvesMailFromValues(): void
    {
        $n = new DefaultNotifiable('user', 1, ['mail' => 'a@example.com']);
        self::assertSame('a@example.com', $n->routeNotificationFor('mail'));
    }

    #[Test]
    public function getNotifiableIdStringifiesId(): void
    {
        $n = new DefaultNotifiable('node', 42, []);
        self::assertSame('42', $n->getNotifiableId());
    }

    #[Test]
    public function getNotifiableTypeReturnsEntityTypeId(): void
    {
        $n = new DefaultNotifiable('node', 1, []);
        self::assertSame('node', $n->getNotifiableType());
    }
}
