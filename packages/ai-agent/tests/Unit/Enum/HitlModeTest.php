<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Enum\HitlMode;

#[CoversClass(HitlMode::class)]
final class HitlModeTest extends TestCase
{
    #[Test]
    public function caseValuesMatchDataModel(): void
    {
        self::assertSame('none', HitlMode::None->value);
        self::assertSame('all', HitlMode::All->value);
        self::assertSame('interactive', HitlMode::Interactive->value);
    }
}
