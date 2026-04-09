<?php

declare(strict_types=1);

namespace Waaseyaa\Workflows\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Workflows\WorkflowVisibility;

#[CoversClass(WorkflowVisibility::class)]
final class WorkflowVisibilityTest extends TestCase
{
    #[Test]
    public function nodeVisibilityRespectsWorkflowStateFirst(): void
    {
        $visibility = new WorkflowVisibility();

        $this->assertTrue($visibility->isNodePublic([
            'workflow_state' => 'published',
            'status' => 0,
        ]));

        $this->assertFalse($visibility->isNodePublic([
            'workflow_state' => 'review',
            'status' => 1,
        ]));
    }

    #[Test]
    public function nodeVisibilityFallsBackToStatusWhenWorkflowStateMissing(): void
    {
        $visibility = new WorkflowVisibility();

        $this->assertTrue($visibility->isNodePublic(['status' => 1]));
        $this->assertFalse($visibility->isNodePublic(['status' => 0]));
        $this->assertTrue($visibility->isNodePublic(['status' => 'published']));
        $this->assertFalse($visibility->isNodePublic(['status' => 'draft']));
    }

    #[Test]
    public function nonNodeEntitiesUseStatusFlagSemantics(): void
    {
        $visibility = new WorkflowVisibility();

        $this->assertTrue($visibility->isEntityPublic('relationship', ['status' => 1]));
        $this->assertFalse($visibility->isEntityPublic('relationship', ['status' => 0]));
        $this->assertTrue($visibility->isEntityPublic('relationship', ['status' => 'yes']));
        $this->assertTrue($visibility->isEntityPublic('taxonomy_term', []));
    }

    #[Test]
    public function isNodePublicForEntityUsesCastAwareStatus(): void
    {
        $entity = new class (['type' => 'article', 'status' => 1]) extends ContentEntityBase {
            /** @var array<string, string> */
            protected array $casts = ['status' => 'bool'];

            public function __construct(array $values = [])
            {
                parent::__construct($values, 'node', [
                    'id' => 'nid',
                    'uuid' => 'uuid',
                    'label' => 'title',
                    'bundle' => 'type',
                ]);
            }
        };

        $visibility = new WorkflowVisibility();

        $this->assertTrue($visibility->isNodePublicForEntity($entity));
    }
}
