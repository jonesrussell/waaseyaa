<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;

#[CoversClass(PlanPolicy::class)]
final class PlanPolicyTest extends TestCase
{
    #[Test]
    public function defaultPolicyBlocksDestruction(): void
    {
        self::assertFalse((new PlanPolicy())->allowDestructive);
    }

    #[Test]
    public function destructionOptInIsExplicit(): void
    {
        self::assertTrue((new PlanPolicy(allowDestructive: true))->allowDestructive);
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(PlanPolicy::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }
}
