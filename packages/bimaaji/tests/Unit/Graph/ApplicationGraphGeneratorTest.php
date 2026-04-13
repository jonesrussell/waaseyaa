<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Graph;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Graph\ApplicationGraph;
use Waaseyaa\Bimaaji\Graph\ApplicationGraphGenerator;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Graph\GraphSectionProviderInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;

#[CoversClass(ApplicationGraphGenerator::class)]
final class ApplicationGraphGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_graph_from_providers(): void
    {
        $provider1 = $this->createProvider('entities', ['node' => ['label' => 'Content']]);
        $provider2 = $this->createProvider('routing', ['/api' => ['methods' => ['GET']]]);

        $generator = new ApplicationGraphGenerator([$provider1, $provider2]);
        $graph = $generator->generate();

        $this->assertInstanceOf(ApplicationGraph::class, $graph);
        $this->assertSame('1.0', $graph->version);
        $this->assertCount(2, $graph->sections);
        $this->assertNotNull($graph->getSection('entities'));
        $this->assertNotNull($graph->getSection('routing'));
    }

    #[Test]
    public function it_generates_empty_graph_with_no_providers(): void
    {
        $generator = new ApplicationGraphGenerator([]);
        $graph = $generator->generate();

        $this->assertSame('1.0', $graph->version);
        $this->assertCount(0, $graph->sections);
    }

    #[Test]
    public function it_skips_failed_provider_in_lenient_mode(): void
    {
        $good = $this->createProvider('entities', ['node' => []]);
        $bad = $this->createThrowingProvider('routing', new \RuntimeException('broken'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('routing'),
                $this->arrayHasKey('exception'),
            );

        $generator = new ApplicationGraphGenerator([$good, $bad], $logger);
        $graph = $generator->generate();

        $this->assertCount(1, $graph->sections);
        $this->assertNotNull($graph->getSection('entities'));
        $this->assertNull($graph->getSection('routing'));
    }

    #[Test]
    public function it_throws_on_failed_provider_in_strict_mode(): void
    {
        $bad = $this->createThrowingProvider('routing', new \RuntimeException('broken'));

        $generator = new ApplicationGraphGenerator([$bad], strict: true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('broken');
        $generator->generate();
    }

    private function createProvider(string $key, array $data): GraphSectionProviderInterface
    {
        return new class ($key, $data) implements GraphSectionProviderInterface {
            public function __construct(
                private readonly string $key,
                private readonly array $data,
            ) {}

            public function getKey(): string
            {
                return $this->key;
            }

            public function provide(): GraphSection
            {
                return new GraphSection($this->key, '1.0', $this->data);
            }
        };
    }

    private function createThrowingProvider(string $key, \Throwable $exception): GraphSectionProviderInterface
    {
        return new class ($key, $exception) implements GraphSectionProviderInterface {
            public function __construct(
                private readonly string $key,
                private readonly \Throwable $exception,
            ) {}

            public function getKey(): string
            {
                return $this->key;
            }

            public function provide(): GraphSection
            {
                throw $this->exception;
            }
        };
    }
}
