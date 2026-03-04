<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\FixtureScaffoldCommand;

#[CoversClass(FixtureScaffoldCommand::class)]
final class FixtureScaffoldCommandTest extends TestCase
{
    #[Test]
    public function itBuildsDeterministicScenarioJsonWithRelationship(): void
    {
        $app = new Application();
        $app->add(new FixtureScaffoldCommand());
        $command = $app->find('fixture:scaffold');

        $tester = new CommandTester($command);
        $tester->execute([
            '--key' => 'water_anchor',
            '--title' => 'Water Anchor',
            '--bundle' => 'teaching',
            '--workflow-state' => 'published',
            '--relationship-type' => 'related',
            '--to-key' => 'river_memory',
            '--timestamp' => '1735689600',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $json = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Water Anchor', $json['nodes']['water_anchor']['title']);
        $this->assertSame('published', $json['nodes']['water_anchor']['workflow_state']);
        $this->assertSame(1, $json['nodes']['water_anchor']['status']);
        $this->assertSame('water_anchor_to_river_memory_related', $json['relationships'][0]['key']);
    }

    #[Test]
    public function itReturnsInvalidWhenRelationshipPairIsIncomplete(): void
    {
        $app = new Application();
        $app->add(new FixtureScaffoldCommand());
        $command = $app->find('fixture:scaffold');

        $tester = new CommandTester($command);
        $tester->execute([
            '--key' => 'water_anchor',
            '--title' => 'Water Anchor',
            '--relationship-type' => 'related',
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('--relationship-type and --to-key must be provided together', $tester->getDisplay());
    }
}
