<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Dsl\TaskDefinition;
use Waaseyaa\Bimaaji\Dsl\TaskParser;
use Waaseyaa\Bimaaji\Dsl\TaskPipeline;
use Waaseyaa\Bimaaji\Graph\ApplicationGraphGenerator;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Graph\GraphSectionProviderInterface;
use Waaseyaa\Bimaaji\Mutation\MutationValidator;
use Waaseyaa\Bimaaji\Patch\PatchGenerator;
use Waaseyaa\Bimaaji\Policy\SovereigntyGuardrails;
use Waaseyaa\Foundation\Sovereignty\SovereigntyProfile;

/**
 * Full pipeline integration test: graph generation → DSL parse → mutation validation
 * → sovereignty guardrails → patch generation.
 */
#[CoversNothing]
final class FullPipelineTest extends TestCase
{
    #[Test]
    public function full_pipeline_add_field_to_known_entity(): void
    {
        // 1. Build application graph from providers
        $entityProvider = $this->createEntityProvider([
            'article' => [
                'label' => 'Article',
                'class' => 'App\\Entity\\Article',
                'keys' => ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
                'fields' => ['title' => ['type' => 'string'], 'body' => ['type' => 'text']],
                'group' => 'content',
                'description' => 'Articles',
                'revisionable' => false,
                'translatable' => false,
            ],
        ]);

        $generator = new ApplicationGraphGenerator([$entityProvider]);
        $graph = $generator->generate();

        $this->assertSame('1.0', $graph->version);
        $this->assertNotNull($graph->getSection('entities'));

        // 2. Parse a task from JSON DSL
        $parser = new TaskParser();
        $task = $parser->parseJson(json_encode([
            'operation' => 'add_field',
            'entity_type' => 'article',
            'field' => 'subtitle',
            'parameters' => ['type' => 'string', 'label' => 'Subtitle'],
        ], JSON_THROW_ON_ERROR));

        $this->assertInstanceOf(TaskDefinition::class, $task);

        // 3. Check sovereignty guardrails (Local profile — all allowed)
        $guardrails = SovereigntyGuardrails::withDefaultRules(SovereigntyProfile::Local);
        $guardrailResult = $guardrails->validate(
            new \Waaseyaa\Bimaaji\Mutation\MutationRequest(
                $task->operation,
                $task->entityType,
                $task->field,
                $task->parameters,
            ),
        );
        $this->assertTrue($guardrailResult->isSuccess());

        // 4. Execute full pipeline: validate mutation → generate patches
        $pipeline = new TaskPipeline(
            new MutationValidator($graph),
            new PatchGenerator(),
        );

        $result = $pipeline->execute($task);

        $this->assertTrue($result->mutationResult->isSuccess());
        $this->assertNotNull($result->patchSet);
        $this->assertCount(1, $result->patchSet->patches);
        $this->assertFalse($result->patchSet->hasUnsafePatches());

        // 5. Verify patch content is valid PHP
        $patch = $result->patchSet->patches[0];
        $this->assertStringStartsWith('<?php', $patch->content);
        $this->assertStringContainsString('subtitle', $patch->content);
        $this->assertSame(hash('sha256', $patch->content), $patch->contentHash);

        // 6. Verify the full result serializes cleanly
        $array = $result->toArray();
        $this->assertSame('success', $array['mutation_result']['status']);
        $this->assertNotNull($array['patch_set']);
    }

    #[Test]
    public function pipeline_rejects_unknown_entity_type(): void
    {
        $generator = new ApplicationGraphGenerator([
            $this->createEntityProvider(['node' => ['fields' => []]]),
        ]);
        $graph = $generator->generate();

        $parser = new TaskParser();
        $task = $parser->parseJson(json_encode([
            'operation' => 'add_field',
            'entity_type' => 'nonexistent',
            'field' => 'title',
        ], JSON_THROW_ON_ERROR));

        $pipeline = new TaskPipeline(
            new MutationValidator($graph),
            new PatchGenerator(),
        );

        $result = $pipeline->execute($task);

        $this->assertFalse($result->mutationResult->isSuccess());
        $this->assertContains('UNKNOWN_ENTITY_TYPE', $result->mutationResult->errors);
        $this->assertNull($result->patchSet);
    }

    #[Test]
    public function northops_guardrails_block_deletion(): void
    {
        $guardrails = SovereigntyGuardrails::withDefaultRules(SovereigntyProfile::NorthOps);

        $request = new \Waaseyaa\Bimaaji\Mutation\MutationRequest(
            'delete_entity_type',
            'article',
        );

        $result = $guardrails->validate($request);

        $this->assertFalse($result->isSuccess());
        $this->assertContains('SOVEREIGNTY_VIOLATION', $result->errors);
    }

    private function createEntityProvider(array $entities): GraphSectionProviderInterface
    {
        return new class ($entities) implements GraphSectionProviderInterface {
            public function __construct(private readonly array $entities) {}

            public function getKey(): string
            {
                return 'entities';
            }

            public function provide(): GraphSection
            {
                return new GraphSection('entities', '1.0', $this->entities);
            }
        };
    }
}
