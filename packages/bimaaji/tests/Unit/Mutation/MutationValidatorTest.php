<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Graph\ApplicationGraph;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Mutation\MutationRequest;
use Waaseyaa\Bimaaji\Mutation\MutationValidator;

#[CoversClass(MutationValidator::class)]
final class MutationValidatorTest extends TestCase
{
    #[Test]
    public function it_accepts_valid_entity_type_and_field(): void
    {
        $graph = $this->buildGraph(['node' => ['fields' => ['title' => [], 'body' => []]]]);
        $validator = new MutationValidator($graph);

        $request = new MutationRequest('add_field', 'node', 'body', ['type' => 'text']);
        $result = $validator->validate($request);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_rejects_unknown_entity_type(): void
    {
        $graph = $this->buildGraph(['node' => ['fields' => ['title' => []]]]);
        $validator = new MutationValidator($graph);

        $request = new MutationRequest('add_field', 'unknown_type', 'title');
        $result = $validator->validate($request);

        $this->assertFalse($result->isSuccess());
        $this->assertContains('UNKNOWN_ENTITY_TYPE', $result->errors);
    }

    #[Test]
    public function it_accepts_new_field_on_known_entity(): void
    {
        $graph = $this->buildGraph(['node' => ['fields' => ['title' => []]]]);
        $validator = new MutationValidator($graph);

        $request = new MutationRequest('add_field', 'node', 'new_field', ['type' => 'string']);
        $result = $validator->validate($request);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_accepts_entity_level_operations_without_field(): void
    {
        $graph = $this->buildGraph(['node' => ['fields' => []]]);
        $validator = new MutationValidator($graph);

        $request = new MutationRequest('add_entity_type', 'article', parameters: ['label' => 'Article']);
        $result = $validator->validate($request);

        // add_entity_type targets a new type — entity type check is skipped for creation ops
        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_validates_without_entities_section(): void
    {
        $graph = new ApplicationGraph('1.0', []);
        $validator = new MutationValidator($graph);

        $request = new MutationRequest('add_field', 'node', 'title');
        $result = $validator->validate($request);

        $this->assertFalse($result->isSuccess());
        $this->assertContains('UNKNOWN_ENTITY_TYPE', $result->errors);
    }

    private function buildGraph(array $entities): ApplicationGraph
    {
        return new ApplicationGraph('1.0', [
            new GraphSection('entities', '1.0', $entities),
        ]);
    }
}
