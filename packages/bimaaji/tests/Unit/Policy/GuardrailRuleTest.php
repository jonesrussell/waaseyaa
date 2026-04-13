<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Policy\GuardrailRule;
use Waaseyaa\Foundation\Sovereignty\SovereigntyProfile;

#[CoversClass(GuardrailRule::class)]
final class GuardrailRuleTest extends TestCase
{
    #[Test]
    public function holdsData(): void
    {
        $rule = new GuardrailRule(
            operation: 'delete_entity_type',
            deniedProfile: SovereigntyProfile::NorthOps,
            reason: 'Managed hosting does not allow entity type deletion',
        );

        self::assertSame('delete_entity_type', $rule->operation);
        self::assertSame(SovereigntyProfile::NorthOps, $rule->deniedProfile);
        self::assertSame('Managed hosting does not allow entity type deletion', $rule->reason);
    }

    #[Test]
    public function toArrayReturnsExpectedStructure(): void
    {
        $rule = new GuardrailRule(
            operation: 'modify_sovereignty',
            deniedProfile: SovereigntyProfile::NorthOps,
            reason: 'Sovereignty profile cannot be changed on managed hosting',
        );

        $array = $rule->toArray();

        self::assertSame('modify_sovereignty', $array['operation']);
        self::assertSame('northops', $array['denied_profile']);
        self::assertSame('Sovereignty profile cannot be changed on managed hosting', $array['reason']);
    }
}
