<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\SchemaListCommand;
use Waaseyaa\Foundation\Schema\DefaultsSchemaRegistry;

#[CoversClass(SchemaListCommand::class)]
final class SchemaListCommandTest extends TestCase
{
    private string $defaultsDir;

    protected function setUp(): void
    {
        $this->defaultsDir = sys_get_temp_dir() . '/waaseyaa_schema_list_cmd_test_' . uniqid();
        mkdir($this->defaultsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->defaultsDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->defaultsDir);
    }

    #[Test]
    public function outputsNoSchemasFoundWhenRegistryIsEmpty(): void
    {
        $tester = $this->execute();

        $this->assertStringContainsString('No schemas found', $tester->getDisplay());
        $this->assertSame(0, $tester->getStatusCode());
    }

    #[Test]
    public function outputsTableWithSchemaDetails(): void
    {
        $this->writeSchema('core.note', 'core.note', '0.1.0', 'liberal');

        $output = $this->execute()->getDisplay();

        $this->assertStringContainsString('core.note', $output);
        $this->assertStringContainsString('0.1.0', $output);
        $this->assertStringContainsString('liberal', $output);
    }

    #[Test]
    public function outputsMultipleSchemasInTable(): void
    {
        $this->writeSchema('core.note', 'core.note', '0.1.0', 'liberal');
        $this->writeSchema('core.article', 'core.article', '0.2.0', 'strict');

        $output = $this->execute()->getDisplay();

        $this->assertStringContainsString('core.note', $output);
        $this->assertStringContainsString('core.article', $output);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function execute(): CommandTester
    {
        $registry = new DefaultsSchemaRegistry($this->defaultsDir);

        $app = new Application();
        $app->add(new SchemaListCommand($registry));

        $command = $app->find('schema:list');
        $tester  = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }

    private function writeSchema(string $filename, string $entityType, string $version, string $compatibility): void
    {
        file_put_contents(
            $this->defaultsDir . '/' . $filename . '.schema.json',
            json_encode([
                '$schema'    => 'http://json-schema.org/draft-07/schema#',
                'title'      => $entityType,
                'x-waaseyaa' => [
                    'entity_type'   => $entityType,
                    'version'       => $version,
                    'compatibility' => $compatibility,
                ],
            ], JSON_THROW_ON_ERROR),
        );
    }
}
