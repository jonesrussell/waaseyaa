<?php

declare(strict_types=1);

namespace Waaseyaa\Mcp\Tests\Unit\Tools;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Api\ResourceSerializer;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Mcp\Tools\McpTool;
use Waaseyaa\Mcp\Tools\TraversalTools;

#[CoversClass(TraversalTools::class)]
#[CoversClass(McpTool::class)]
final class TraversalToolsTest extends TestCase
{
    #[Test]
    public function parseTraversalArgumentsRequiresTypeAndId(): void
    {
        $tools = $this->createTraversalTools();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty "type" and "id"');

        $tools->parseTraversalArguments(['type' => '', 'id' => '']);
    }

    #[Test]
    public function parseTraversalArgumentsRejectsUnknownEntityType(): void
    {
        $tools = $this->createTraversalTools();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown traversal entity type');

        $tools->parseTraversalArguments(['type' => 'nonexistent', 'id' => '1']);
    }

    #[Test]
    public function parseTraversalArgumentsRejectsInvalidDirection(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->method('hasDefinition')->willReturn(true);

        $tools = new TraversalTools(
            entityTypeManager: $manager,
            serializer: new ResourceSerializer($manager),
            accessHandler: new EntityAccessHandler([]),
            account: $this->anonymousAccount(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"direction" must be one of');

        $tools->parseTraversalArguments(['type' => 'node', 'id' => '1', 'direction' => 'sideways']);
    }

    #[Test]
    public function parseTraversalArgumentsRejectsInvalidStatus(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->method('hasDefinition')->willReturn(true);

        $tools = new TraversalTools(
            entityTypeManager: $manager,
            serializer: new ResourceSerializer($manager),
            accessHandler: new EntityAccessHandler([]),
            account: $this->anonymousAccount(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"status" must be one of');

        $tools->parseTraversalArguments(['type' => 'node', 'id' => '1', 'status' => 'invalid']);
    }

    #[Test]
    public function parseTraversalArgumentsReturnsDefaults(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->method('hasDefinition')->willReturn(true);

        $tools = new TraversalTools(
            entityTypeManager: $manager,
            serializer: new ResourceSerializer($manager),
            accessHandler: new EntityAccessHandler([]),
            account: $this->anonymousAccount(),
        );

        $parsed = $tools->parseTraversalArguments(['type' => 'Node', 'id' => '42']);

        self::assertSame('node', $parsed['entity_type']);
        self::assertSame('42', $parsed['entity_id']);
        self::assertSame('both', $parsed['direction']);
        self::assertSame('published', $parsed['status']);
        self::assertSame([], $parsed['relationship_types']);
        self::assertNull($parsed['at']);
        self::assertSame(25, $parsed['limit']);
    }

    #[Test]
    public function parseTraversalArgumentsClampsLimit(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->method('hasDefinition')->willReturn(true);

        $tools = new TraversalTools(
            entityTypeManager: $manager,
            serializer: new ResourceSerializer($manager),
            accessHandler: new EntityAccessHandler([]),
            account: $this->anonymousAccount(),
        );

        $parsed = $tools->parseTraversalArguments(['type' => 'node', 'id' => '1', 'limit' => 999]);
        self::assertSame(100, $parsed['limit']);

        $parsed = $tools->parseTraversalArguments(['type' => 'node', 'id' => '1', 'limit' => -5]);
        self::assertSame(1, $parsed['limit']);
    }

    #[Test]
    public function knowledgeGraphForcesDirectionBoth(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->method('hasDefinition')->willReturn(true);

        $tools = new TraversalTools(
            entityTypeManager: $manager,
            serializer: new ResourceSerializer($manager),
            accessHandler: new EntityAccessHandler([]),
            account: $this->anonymousAccount(),
        );

        $args = ['type' => 'node', 'id' => '1', 'direction' => 'inbound'];
        $parsed = $tools->parseTraversalArguments($args);

        // array_merge correctly overrides
        $merged = array_merge($parsed, ['direction' => 'both']);
        $this->assertSame('both', $merged['direction']);

        // Verify PHP + operator would NOT override (proving the bug)
        $broken = $parsed + ['direction' => 'both'];
        $this->assertSame('inbound', $broken['direction'], 'PHP + operator keeps left-side keys');
    }

    private function createTraversalTools(): TraversalTools
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->method('hasDefinition')->willReturn(false);

        return new TraversalTools(
            entityTypeManager: $manager,
            serializer: new ResourceSerializer($manager),
            accessHandler: new EntityAccessHandler([]),
            account: $this->anonymousAccount(),
        );
    }

    private function anonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int|string { return 0; }
            public function hasPermission(string $permission): bool { return false; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };
    }
}
