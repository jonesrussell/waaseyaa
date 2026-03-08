<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase21;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\SchemaListCommand;
use Waaseyaa\Foundation\Schema\DefaultsSchemaRegistry;

/**
 * End-to-end schema registry (#207): defaults/ → registry → CLI schema:list.
 */
#[CoversNothing]
final class SchemaRegistryIntegrationTest extends TestCase
{
    private DefaultsSchemaRegistry $registry;

    protected function setUp(): void
    {
        $defaultsDir    = dirname(__DIR__, 3) . '/defaults';
        $this->registry = new DefaultsSchemaRegistry($defaultsDir);
    }

    #[Test]
    public function registryLoadsCoreNoteSchema(): void
    {
        $entry = $this->registry->get('core.note');

        $this->assertNotNull($entry);
        $this->assertSame('core.note', $entry->id);
        $this->assertNotEmpty($entry->version);
        $this->assertSame('liberal', $entry->compatibility);
        $this->assertFileExists($entry->schemaPath);
    }

    #[Test]
    public function registryListContainsCoreNote(): void
    {
        $entries = $this->registry->list();

        $ids = array_map(static fn($e) => $e->id, $entries);
        $this->assertContains('core.note', $ids);
    }

    #[Test]
    public function registryListIsSortedAlphabetically(): void
    {
        $entries = $this->registry->list();
        $ids     = array_map(static fn($e) => $e->id, $entries);
        $sorted  = $ids;
        sort($sorted);

        $this->assertSame($sorted, $ids);
    }

    #[Test]
    public function cliSchemaListOutputsCoreNote(): void
    {
        $app = new Application();
        $app->add(new SchemaListCommand($this->registry));

        $command = $app->find('schema:list');
        $tester  = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('core.note', $output);
        $this->assertStringContainsString('liberal', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }
}
