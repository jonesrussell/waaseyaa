<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Graph;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Graph\ApplicationGraph;
use Waaseyaa\Bimaaji\Graph\GraphSection;

#[CoversClass(ApplicationGraph::class)]
final class ApplicationGraphTest extends TestCase
{
    #[Test]
    public function it_holds_version_and_sections(): void
    {
        $section = new GraphSection('entities', '1.0', ['node' => []]);
        $graph = new ApplicationGraph('1.0', [$section]);

        $this->assertSame('1.0', $graph->version);
        $this->assertCount(1, $graph->sections);
        $this->assertSame($section, $graph->sections['entities']);
    }

    #[Test]
    public function get_section_returns_section_by_key(): void
    {
        $entities = new GraphSection('entities', '1.0', ['node' => []]);
        $routing = new GraphSection('routing', '1.0', ['/api' => []]);
        $graph = new ApplicationGraph('1.0', [$entities, $routing]);

        $this->assertSame($entities, $graph->getSection('entities'));
        $this->assertSame($routing, $graph->getSection('routing'));
        $this->assertNull($graph->getSection('nonexistent'));
    }

    #[Test]
    public function to_array_returns_versioned_structure(): void
    {
        $section = new GraphSection('entities', '1.0', ['node' => ['label' => 'Content']]);
        $graph = new ApplicationGraph('1.0', [$section]);

        $result = $graph->toArray();

        $this->assertSame('1.0', $result['version']);
        $this->assertArrayHasKey('sections', $result);
        $this->assertArrayHasKey('entities', $result['sections']);
        $this->assertSame([
            'key' => 'entities',
            'version' => '1.0',
            'data' => ['node' => ['label' => 'Content']],
        ], $result['sections']['entities']);
    }

    #[Test]
    public function empty_graph_serializes_cleanly(): void
    {
        $graph = new ApplicationGraph('1.0', []);

        $this->assertSame([
            'version' => '1.0',
            'sections' => [],
        ], $graph->toArray());
    }
}
