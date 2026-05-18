<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\AgentDefinition;
use Waaseyaa\AI\Agent\AgentDefinitionRegistry;
use Waaseyaa\AI\Agent\Enum\HitlMode;
use Waaseyaa\Foundation\Discovery\PackageManifest;

#[CoversClass(AgentDefinitionRegistry::class)]
#[CoversClass(AgentDefinition::class)]
final class AgentDefinitionRegistryTest extends TestCase
{
    private function manifest(array $defs): PackageManifest
    {
        return new PackageManifest(
            providers: [],
            migrations: [],
            fieldTypes: [],
            middleware: [],
            agentDefinitions: $defs,
        );
    }

    #[Test]
    public function emptyManifestProducesEmptyRegistry(): void
    {
        $registry = new AgentDefinitionRegistry($this->manifest([]));

        self::assertSame([], $registry->all());
        self::assertFalse($registry->has('anything'));
    }

    #[Test]
    public function definitionsAreHydratedFromManifest(): void
    {
        $registry = new AgentDefinitionRegistry($this->manifest([
            [
                'class' => 'Fixture\\Intake',
                'id' => 'intake.classifier',
                'label' => 'Intake classifier',
                'description' => 'Classifies inbound notes.',
                'prompt' => 'Classify the following note.',
                'system' => 'You are a classifier.',
                'tools' => ['entity.read', 'entity.update'],
                'model' => 'claude-sonnet-4',
                'max_iterations' => 8,
                'destructive_default' => 'all',
                'requires_capability' => 'note.write',
            ],
        ]));

        self::assertTrue($registry->has('intake.classifier'));

        $def = $registry->get('intake.classifier');
        self::assertSame('intake.classifier', $def->id);
        self::assertSame('Intake classifier', $def->label);
        self::assertSame(['entity.read', 'entity.update'], $def->tools);
        self::assertSame(8, $def->maxIterations);
        self::assertSame(HitlMode::All, $def->destructiveDefault);
        self::assertSame('note.write', $def->requiresCapability);
    }

    #[Test]
    public function getThrowsForUnknownId(): void
    {
        $registry = new AgentDefinitionRegistry($this->manifest([]));

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('nope');
    }

    #[Test]
    public function malformedEntriesAreSkipped(): void
    {
        $registry = new AgentDefinitionRegistry($this->manifest([
            [
                'class' => 'Fixture\\Empty',
                'id' => '',
                'label' => '',
                'description' => '',
                'prompt' => '',
                'system' => '',
                'tools' => [],
                'model' => '',
                'max_iterations' => 0,
                'destructive_default' => null,
                'requires_capability' => null,
            ],
        ]));

        self::assertSame([], $registry->all());
    }
}
