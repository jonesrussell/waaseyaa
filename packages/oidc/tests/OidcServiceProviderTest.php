<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests;

use PHPUnit\Framework\TestCase;
use Waaseyaa\Oidc\OidcServiceProvider;

final class OidcServiceProviderTest extends TestCase
{
    public function testClassIsAutoloadable(): void
    {
        self::assertTrue(class_exists(OidcServiceProvider::class));
    }
}
