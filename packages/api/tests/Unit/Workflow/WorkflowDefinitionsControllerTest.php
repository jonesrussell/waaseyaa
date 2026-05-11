<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Unit\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Api\Workflow\WorkflowDefinitionsController;
use Waaseyaa\Workflows\EditorialWorkflowPreset;
use Waaseyaa\Workflows\Workflow;

#[CoversClass(WorkflowDefinitionsController::class)]
final class WorkflowDefinitionsControllerTest extends TestCase
{
    #[Test]
    public function listReturnsEditorialPresetByDefault(): void
    {
        $payload = (new WorkflowDefinitionsController())->list();

        self::assertArrayHasKey('data', $payload);
        self::assertCount(1, $payload['data']);

        $editorial = $payload['data'][0];
        self::assertSame('editorial', $editorial['id']);
        self::assertSame('Editorial', $editorial['label']);
        self::assertCount(4, $editorial['states'], 'editorial preset has 4 states');
        self::assertCount(6, $editorial['transitions'], 'editorial preset has 6 transitions');
    }

    #[Test]
    public function listSerializesStateShape(): void
    {
        $payload = (new WorkflowDefinitionsController())->list();
        $stateIds = array_column($payload['data'][0]['states'], 'id');

        self::assertSame(['draft', 'review', 'published', 'archived'], $stateIds);

        $draft = $payload['data'][0]['states'][0];
        self::assertSame('draft', $draft['id']);
        self::assertSame('Draft', $draft['label']);
        self::assertSame(0, $draft['weight']);
        self::assertSame(['legacy_status' => 0], $draft['metadata']);
    }

    #[Test]
    public function listSerializesTransitionShape(): void
    {
        $payload = (new WorkflowDefinitionsController())->list();
        $submit = $payload['data'][0]['transitions'][0];

        self::assertSame('submit_for_review', $submit['id']);
        self::assertSame('Submit for Review', $submit['label']);
        self::assertSame(['draft'], $submit['from']);
        self::assertSame('review', $submit['to']);
    }

    #[Test]
    public function listRespectsInjectedWorkflowsProvider(): void
    {
        $custom = new Workflow([
            'id' => 'custom',
            'label' => 'Custom',
            'states' => ['open' => ['label' => 'Open', 'weight' => 0]],
            'transitions' => [],
        ]);

        $controller = new WorkflowDefinitionsController(
            static fn(): array => [EditorialWorkflowPreset::create(), $custom],
        );
        $payload = $controller->list();

        self::assertCount(2, $payload['data']);
        self::assertSame('custom', $payload['data'][1]['id']);
        self::assertSame([['id' => 'open', 'label' => 'Open', 'weight' => 0, 'metadata' => []]], $payload['data'][1]['states']);
        self::assertSame([], $payload['data'][1]['transitions']);
    }

    #[Test]
    public function listProducesEmptyDataForEmptyProvider(): void
    {
        $controller = new WorkflowDefinitionsController(static fn(): array => []);
        $payload = $controller->list();

        self::assertSame(['data' => []], $payload);
    }
}
