<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Tests\Unit\Host;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AdminSurface\Host\AdminSurfaceSessionData;

#[CoversClass(AdminSurfaceSessionData::class)]
final class AdminSurfaceSessionDataTest extends TestCase
{
    #[Test]
    public function toArrayReturnsFullStructure(): void
    {
        $session = new AdminSurfaceSessionData(
            accountId: '42',
            accountName: 'Admin User',
            roles: ['admin', 'editor'],
            policies: ['administer content', 'edit any content'],
            email: 'admin@example.com',
            tenantId: 'org-1',
            tenantName: 'Test Org',
            features: ['ai_assist' => true],
        );

        $result = $session->toArray();

        self::assertSame('42', $result['account']['id']);
        self::assertSame('Admin User', $result['account']['name']);
        self::assertSame('admin@example.com', $result['account']['email']);
        self::assertSame(['admin', 'editor'], $result['account']['roles']);
        self::assertSame('org-1', $result['tenant']['id']);
        self::assertSame('Test Org', $result['tenant']['name']);
        self::assertSame(['administer content', 'edit any content'], $result['policies']);
        self::assertSame(['ai_assist' => true], $result['features']);
    }

    #[Test]
    public function toArrayUsesDefaultsForOptionalFields(): void
    {
        $session = new AdminSurfaceSessionData(
            accountId: '1',
            accountName: 'User',
            roles: [],
            policies: [],
        );

        $result = $session->toArray();

        self::assertNull($result['account']['email']);
        self::assertSame('default', $result['tenant']['id']);
        self::assertSame('Default', $result['tenant']['name']);
        // Empty features array becomes stdClass for clean JSON serialization
        self::assertInstanceOf(\stdClass::class, $result['features']);
    }
}
