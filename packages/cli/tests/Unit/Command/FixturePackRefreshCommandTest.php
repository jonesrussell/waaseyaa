<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\FixturePackRefreshCommand;

#[CoversClass(FixturePackRefreshCommand::class)]
final class FixturePackRefreshCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/waaseyaa_fixture_pack_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        @rmdir($this->tempDir);
    }

    #[Test]
    public function itBuildsDeterministicAggregateAndHash(): void
    {
        file_put_contents($this->tempDir . '/b.json', json_encode([
            'nodes' => [
                'river' => ['title' => 'River', 'workflow_state' => 'published'],
            ],
            'relationships' => [
                ['key' => 'river_to_water_related', 'from' => 'river', 'to' => 'water', 'relationship_type' => 'related'],
            ],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($this->tempDir . '/a.json', json_encode([
            'nodes' => [
                'water' => ['title' => 'Water', 'workflow_state' => 'published'],
            ],
            'relationships' => [],
        ], JSON_THROW_ON_ERROR));

        $app = new Application();
        $app->add(new FixturePackRefreshCommand());
        $command = $app->find('fixture:pack:refresh');

        $tester = new CommandTester($command);
        $tester->execute([
            '--input-dir' => $this->tempDir,
            '--json' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $first = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $first['scenario_count']);
        $this->assertSame(2, $first['node_count']);
        $this->assertSame('a', array_keys($first['scenarios'])[0]);
        $this->assertSame('water', array_keys($first['nodes'])[1]);

        $tester->execute([
            '--input-dir' => $this->tempDir,
            '--json' => true,
        ]);
        $second = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($first['hash'], $second['hash']);
    }

    #[Test]
    public function itCanFailOnEmptyInputDirectory(): void
    {
        $app = new Application();
        $app->add(new FixturePackRefreshCommand());
        $command = $app->find('fixture:pack:refresh');

        $tester = new CommandTester($command);
        $tester->execute([
            '--input-dir' => $this->tempDir,
            '--fail-on-empty' => true,
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No fixture scenario files found', $tester->getDisplay());
    }
}
