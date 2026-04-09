<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Bimaaji;

#[CoversClass(Bimaaji::class)]
final class SmokeTest extends TestCase
{
    #[Test]
    public function bimaaji_class_autoloads(): void
    {
        $this->assertTrue(class_exists(Bimaaji::class));
    }
}
