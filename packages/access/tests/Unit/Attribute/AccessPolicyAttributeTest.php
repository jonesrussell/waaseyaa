<?php

declare(strict_types=1);

namespace Waaseyaa\Access\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\Attribute\AccessPolicy;

#[CoversClass(AccessPolicy::class)]
final class AccessPolicyAttributeTest extends TestCase
{
    #[Test]
    public function bundlesDefaultsToEmptyArray(): void
    {
        $attr = new AccessPolicy(id: 'node_access', entityTypes: ['node']);

        self::assertSame([], $attr->bundles);
    }

    #[Test]
    public function bundlesAreStoredOnTheAttribute(): void
    {
        $attr = new AccessPolicy(
            id: 'business_access',
            entityTypes: ['group'],
            bundles: ['business'],
        );

        self::assertSame(['business'], $attr->bundles);
    }

    #[Test]
    public function bundlesAcceptMultipleValues(): void
    {
        $attr = new AccessPolicy(
            id: 'group_access',
            entityTypes: ['group'],
            bundles: ['business', 'organization'],
        );

        self::assertSame(['business', 'organization'], $attr->bundles);
    }
}
