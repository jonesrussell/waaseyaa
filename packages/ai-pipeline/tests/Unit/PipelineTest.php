<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Pipeline\Tests\Unit;

use Waaseyaa\AI\Pipeline\Pipeline;
use Waaseyaa\AI\Pipeline\PipelineStepConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Waaseyaa\AI\Pipeline\Pipeline
 */
final class PipelineTest extends TestCase
{
    public function testEntityTypeId(): void
    {
        $pipeline = new Pipeline(['id' => 'content_pipeline', 'label' => 'Content Pipeline']);

        $this->assertSame('pipeline', $pipeline->getEntityTypeId());
    }

    public function testIdAndLabel(): void
    {
        $pipeline = new Pipeline(['id' => 'my_pipeline', 'label' => 'My Pipeline']);

        $this->assertSame('my_pipeline', $pipeline->id());
        $this->assertSame('My Pipeline', $pipeline->label());
    }

    public function testConstructionFromValuesArray(): void
    {
        $pipeline = new Pipeline([
            'id' => 'test',
            'label' => 'Test Pipeline',
            'description' => 'A test pipeline',
            'steps' => [
                [
                    'id' => 'step_1',
                    'plugin_id' => 'uppercase',
                    'label' => 'Uppercase',
                    'weight' => 0,
                    'configuration' => ['field' => 'text'],
                ],
                [
                    'id' => 'step_2',
                    'plugin_id' => 'append',
                    'label' => 'Append',
                    'weight' => 10,
                    'configuration' => [],
                ],
            ],
        ]);

        $this->assertSame('A test pipeline', $pipeline->getDescription());
        $this->assertCount(2, $pipeline->getSteps());
    }

    public function testConstructionWithStepConfigObjects(): void
    {
        $stepConfig = new PipelineStepConfig(id: 'step_1', pluginId: 'uppercase');
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [$stepConfig],
        ]);

        $steps = $pipeline->getSteps();
        $this->assertCount(1, $steps);
        $this->assertSame('step_1', $steps[0]->id);
        $this->assertSame('uppercase', $steps[0]->pluginId);
    }

    public function testDescriptionDefaultsToEmpty(): void
    {
        $pipeline = new Pipeline(['id' => 'test']);

        $this->assertSame('', $pipeline->getDescription());
    }

    public function testGetSetDescription(): void
    {
        $pipeline = new Pipeline(['id' => 'test', 'description' => 'Original']);

        $this->assertSame('Original', $pipeline->getDescription());

        $pipeline->setDescription('Updated');
        $this->assertSame('Updated', $pipeline->getDescription());
    }

    public function testSetDescriptionSyncsValues(): void
    {
        $pipeline = new Pipeline(['id' => 'test']);

        $pipeline->setDescription('Synced description');

        $array = $pipeline->toArray();
        $this->assertSame('Synced description', $array['description']);
    }

    public function testAddStep(): void
    {
        $pipeline = new Pipeline(['id' => 'test']);

        $this->assertCount(0, $pipeline->getSteps());

        $step = new PipelineStepConfig(id: 'new_step', pluginId: 'my_plugin', weight: 5);
        $result = $pipeline->addStep($step);

        $this->assertSame($pipeline, $result); // fluent
        $this->assertCount(1, $pipeline->getSteps());
        $this->assertSame('new_step', $pipeline->getSteps()[0]->id);
    }

    public function testAddStepSyncsValues(): void
    {
        $pipeline = new Pipeline(['id' => 'test']);

        $pipeline->addStep(new PipelineStepConfig(id: 's1', pluginId: 'p1'));

        $array = $pipeline->toArray();
        $this->assertIsArray($array['steps']);
        $this->assertCount(1, $array['steps']);
        $this->assertSame('s1', $array['steps'][0]['id']);
    }

    public function testRemoveStep(): void
    {
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_a', 'plugin_id' => 'p1'],
                ['id' => 'step_b', 'plugin_id' => 'p2'],
                ['id' => 'step_c', 'plugin_id' => 'p3'],
            ],
        ]);

        $result = $pipeline->removeStep('step_b');

        $this->assertSame($pipeline, $result); // fluent
        $steps = $pipeline->getSteps();
        $this->assertCount(2, $steps);

        $stepIds = array_map(fn (PipelineStepConfig $s) => $s->id, $steps);
        $this->assertContains('step_a', $stepIds);
        $this->assertContains('step_c', $stepIds);
        $this->assertNotContains('step_b', $stepIds);
    }

    public function testRemoveStepSyncsValues(): void
    {
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_a', 'plugin_id' => 'p1'],
                ['id' => 'step_b', 'plugin_id' => 'p2'],
            ],
        ]);

        $pipeline->removeStep('step_a');

        $array = $pipeline->toArray();
        $this->assertCount(1, $array['steps']);
        $this->assertSame('step_b', $array['steps'][0]['id']);
    }

    public function testRemoveNonexistentStepIsNoOp(): void
    {
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_a', 'plugin_id' => 'p1'],
            ],
        ]);

        $pipeline->removeStep('nonexistent');

        $this->assertCount(1, $pipeline->getSteps());
    }

    public function testStepsOrderedByWeight(): void
    {
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'heavy', 'plugin_id' => 'p1', 'weight' => 100],
                ['id' => 'light', 'plugin_id' => 'p2', 'weight' => -5],
                ['id' => 'medium', 'plugin_id' => 'p3', 'weight' => 10],
            ],
        ]);

        $steps = $pipeline->getSteps();

        $this->assertSame('light', $steps[0]->id);
        $this->assertSame('medium', $steps[1]->id);
        $this->assertSame('heavy', $steps[2]->id);
    }

    public function testToConfig(): void
    {
        $pipeline = new Pipeline([
            'id' => 'content_pipeline',
            'label' => 'Content Pipeline',
            'description' => 'Processes content',
            'steps' => [
                [
                    'id' => 'step_1',
                    'plugin_id' => 'uppercase',
                    'label' => 'Uppercase',
                    'weight' => 0,
                    'configuration' => ['field' => 'title'],
                ],
            ],
        ]);

        $config = $pipeline->toConfig();

        $this->assertSame('content_pipeline', $config['id']);
        $this->assertSame('Content Pipeline', $config['label']);
        $this->assertSame('Processes content', $config['description']);
        $this->assertTrue($config['status']);
        $this->assertCount(1, $config['steps']);
        $this->assertSame('step_1', $config['steps'][0]['id']);
        $this->assertSame('uppercase', $config['steps'][0]['plugin_id']);
        $this->assertSame('Uppercase', $config['steps'][0]['label']);
        $this->assertSame(0, $config['steps'][0]['weight']);
        $this->assertSame(['field' => 'title'], $config['steps'][0]['configuration']);
    }

    public function testToConfigWithNoSteps(): void
    {
        $pipeline = new Pipeline(['id' => 'empty', 'label' => 'Empty Pipeline']);

        $config = $pipeline->toConfig();

        $this->assertSame([], $config['steps']);
        $this->assertSame('', $config['description']);
    }

    public function testConfigEntityHasNoUuid(): void
    {
        $pipeline = new Pipeline(['id' => 'test']);

        // Config entities do not have UUIDs.
        $this->assertSame('', $pipeline->uuid());
    }

    public function testFluentInterface(): void
    {
        $pipeline = new Pipeline(['id' => 'test']);
        $step = new PipelineStepConfig(id: 's1', pluginId: 'p1');

        $result = $pipeline
            ->setDescription('desc')
            ->addStep($step)
            ->removeStep('nonexistent');

        $this->assertSame($pipeline, $result);
    }

    public function testStatusDefaults(): void
    {
        $pipeline = new Pipeline(['id' => 'test']);

        $this->assertTrue($pipeline->status());
    }

    public function testEnableDisable(): void
    {
        $pipeline = new Pipeline(['id' => 'test']);

        $pipeline->disable();
        $this->assertFalse($pipeline->status());

        $pipeline->enable();
        $this->assertTrue($pipeline->status());
    }
}
