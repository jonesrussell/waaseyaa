<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Mutation\MutationRequest;

#[CoversClass(MutationRequest::class)]
final class MutationRequestTest extends TestCase
{
    #[Test]
    public function it_holds_operation_and_target(): void
    {
        $request = new MutationRequest(
            operation: 'add_field',
            entityType: 'node',
            field: 'subtitle',
            parameters: ['type' => 'string', 'label' => 'Subtitle'],
        );

        $this->assertSame('add_field', $request->operation);
        $this->assertSame('node', $request->entityType);
        $this->assertSame('subtitle', $request->field);
        $this->assertSame(['type' => 'string', 'label' => 'Subtitle'], $request->parameters);
    }

    #[Test]
    public function field_is_nullable_for_entity_level_operations(): void
    {
        $request = new MutationRequest(
            operation: 'add_entity_type',
            entityType: 'article',
            parameters: ['label' => 'Article'],
        );

        $this->assertSame('add_entity_type', $request->operation);
        $this->assertSame('article', $request->entityType);
        $this->assertNull($request->field);
    }

    #[Test]
    public function to_array_returns_full_structure(): void
    {
        $request = new MutationRequest(
            operation: 'add_field',
            entityType: 'node',
            field: 'body',
            parameters: ['type' => 'text'],
        );

        $this->assertSame([
            'operation' => 'add_field',
            'entity_type' => 'node',
            'field' => 'body',
            'parameters' => ['type' => 'text'],
        ], $request->toArray());
    }
}
