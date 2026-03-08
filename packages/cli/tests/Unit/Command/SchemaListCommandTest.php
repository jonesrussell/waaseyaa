<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\SchemaListCommand;
use Waaseyaa\Foundation\Schema\SchemaEntry;
use Waaseyaa\Foundation\Schema\SchemaRegistryInterface;

#[CoversClass(SchemaListCommand::class)]
final class SchemaListCommandTest extends TestCase
{
    #[Test]
    public function outputsNoSchemasFoundWhenRegistryIsEmpty(): void
    {
        $tester = $this->execute([]);

        $this->assertStringContainsString('No schemas found', $tester->getDisplay());
        $this->assertSame(0, $tester->getStatusCode());
    }

    #[Test]
    public function outputsTableWithSchemaDetails(): void
    {
        $output = $this->execute([
            new SchemaEntry('core.note', '0.1.0', 'liberal', '/defaults/core.note.schema.json'),
        ])->getDisplay();

        $this->assertStringContainsString('core.note', $output);
        $this->assertStringContainsString('0.1.0', $output);
        $this->assertStringContainsString('liberal', $output);
    }

    #[Test]
    public function outputsMultipleSchemasInTable(): void
    {
        $output = $this->execute([
            new SchemaEntry('core.note', '0.1.0', 'liberal', '/defaults/core.note.schema.json'),
            new SchemaEntry('core.article', '0.2.0', 'strict', '/defaults/core.article.schema.json'),
        ])->getDisplay();

        $this->assertStringContainsString('core.note', $output);
        $this->assertStringContainsString('core.article', $output);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** @param list<SchemaEntry> $entries */
    private function execute(array $entries): CommandTester
    {
        $registry = new class ($entries) implements SchemaRegistryInterface {
            /** @param list<SchemaEntry> $entries */
            public function __construct(private readonly array $entries) {}

            public function list(): array
            {
                return $this->entries;
            }

            public function get(string $id): ?SchemaEntry
            {
                foreach ($this->entries as $entry) {
                    if ($entry->id === $id) {
                        return $entry;
                    }
                }

                return null;
            }
        };

        $app = new Application();
        $app->add(new SchemaListCommand($registry));

        $command = $app->find('schema:list');
        $tester  = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }
}
