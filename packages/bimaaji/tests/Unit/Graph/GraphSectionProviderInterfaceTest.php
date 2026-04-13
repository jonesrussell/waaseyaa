<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Graph;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Graph\GraphSectionProviderInterface;

#[CoversNothing]
final class GraphSectionProviderInterfaceTest extends TestCase
{
    #[Test]
    public function provider_returns_keyed_section(): void
    {
        $provider = new class implements GraphSectionProviderInterface {
            public function getKey(): string
            {
                return 'test_section';
            }

            public function provide(): GraphSection
            {
                return new GraphSection('test_section', '1.0', ['foo' => 'bar']);
            }
        };

        $this->assertSame('test_section', $provider->getKey());

        $section = $provider->provide();
        $this->assertSame('test_section', $section->key);
        $this->assertSame('1.0', $section->version);
        $this->assertSame(['foo' => 'bar'], $section->data);
    }
}
