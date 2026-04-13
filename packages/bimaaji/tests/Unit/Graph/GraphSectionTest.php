<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Graph;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Graph\GraphSection;

#[CoversClass(GraphSection::class)]
final class GraphSectionTest extends TestCase
{
    #[Test]
    public function it_holds_key_version_and_data(): void
    {
        $section = new GraphSection(
            key: 'entities',
            version: '1.0',
            data: ['node' => ['label' => 'Content']],
        );

        $this->assertSame('entities', $section->key);
        $this->assertSame('1.0', $section->version);
        $this->assertSame(['node' => ['label' => 'Content']], $section->data);
    }

    #[Test]
    public function to_array_returns_structured_output(): void
    {
        $section = new GraphSection(
            key: 'routing',
            version: '1.0',
            data: ['/api/nodes' => ['methods' => ['GET']]],
        );

        $this->assertSame([
            'key' => 'routing',
            'version' => '1.0',
            'data' => ['/api/nodes' => ['methods' => ['GET']]],
        ], $section->toArray());
    }
}
