<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\SSR\ViewMode;

#[CoversClass(ViewMode::class)]
final class ViewModeTest extends TestCase
{
    #[Test]
    public function predefinedModesHaveExpectedNames(): void
    {
        $this->assertSame('full', ViewMode::full()->name);
        $this->assertSame('teaser', ViewMode::teaser()->name);
        $this->assertSame('embed', ViewMode::embed()->name);
    }

    #[Test]
    public function modeIsExtensibleViaConstructor(): void
    {
        $mode = new ViewMode('card');
        $this->assertSame('card', $mode->name);
        $this->assertSame('card', (string) $mode);
    }
}
