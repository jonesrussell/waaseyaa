<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\DebugContextCommand;

#[CoversClass(DebugContextCommand::class)]
final class DebugContextCommandTest extends TestCase
{
    #[Test]
    public function itRendersDeterministicDebugPanels(): void
    {
        $app = new Application();
        $app->add(new DebugContextCommand());
        $command = $app->find('debug:context');

        $tester = new CommandTester($command);
        $tester->execute([
            '--entity-type' => 'node',
            '--entity-id' => '42',
            '--workflow-state' => 'published',
            '--status' => '1',
            '--relationship-counts' => '4:2',
            '--view-mode' => 'teaser',
            '--preview' => '0',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($decoded['debug_panel']['workflow']['is_public']);
        $this->assertSame(6, $decoded['debug_panel']['traversal']['counts']['total']);
        $this->assertSame('public', $decoded['debug_panel']['ssr']['cache_scope']);
    }

    #[Test]
    public function itRejectsMalformedRelationshipCounts(): void
    {
        $app = new Application();
        $app->add(new DebugContextCommand());
        $command = $app->find('debug:context');

        $tester = new CommandTester($command);
        $tester->execute([
            '--relationship-counts' => 'bad',
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid --relationship-counts', $tester->getDisplay());
    }
}
