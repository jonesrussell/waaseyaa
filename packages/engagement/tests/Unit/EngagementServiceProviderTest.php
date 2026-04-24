<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Engagement\EngagementServiceProvider;

/**
 * @covers \Waaseyaa\Engagement\EngagementServiceProvider
 */
final class EngagementServiceProviderTest extends TestCase
{
    #[Test]
    public function registersReactionCommentAndFollowEntityTypes(): void
    {
        $provider = new EngagementServiceProvider();
        $provider->register();

        $types = $provider->getEntityTypes();
        self::assertCount(3, $types);
        $ids = array_map(static fn ($t) => $t->id(), $types);
        self::assertContains('reaction', $ids);
        self::assertContains('comment', $ids);
        self::assertContains('follow', $ids);
    }
}
