<?php

declare(strict_types=1);

namespace Waaseyaa\Mcp\Tests\Unit;

use Waaseyaa\Mcp\McpServerCard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpServerCard::class)]
final class McpServerCardTest extends TestCase
{
    #[Test]
    public function toArrayProducesValidServerCardStructure(): void
    {
        $card = new McpServerCard();
        $result = $card->toArray();

        $this->assertSame('Waaseyaa', $result['name']);
        $this->assertSame('0.1.0', $result['version']);
        $this->assertSame('/mcp', $result['endpoint']);
        $this->assertSame('streamable-http', $result['transport']);
        $this->assertTrue($result['capabilities']['tools']);
        $this->assertFalse($result['capabilities']['resources']);
        $this->assertFalse($result['capabilities']['prompts']);
        $this->assertSame('bearer', $result['authentication']['type']);
    }

    #[Test]
    public function constructorAcceptsCustomValues(): void
    {
        $card = new McpServerCard(
            name: 'My CMS',
            version: '2.0.0',
            endpoint: '/api/mcp',
        );

        $result = $card->toArray();

        $this->assertSame('My CMS', $result['name']);
        $this->assertSame('2.0.0', $result['version']);
        $this->assertSame('/api/mcp', $result['endpoint']);
    }

    #[Test]
    public function toJsonReturnsValidJson(): void
    {
        $card = new McpServerCard();
        $json = $card->toJson();

        $decoded = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('Waaseyaa', $decoded['name']);
    }
}
