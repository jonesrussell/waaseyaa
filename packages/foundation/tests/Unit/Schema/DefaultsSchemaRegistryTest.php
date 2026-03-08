<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\DefaultsSchemaRegistry;
use Waaseyaa\Foundation\Schema\SchemaEntry;

#[CoversClass(DefaultsSchemaRegistry::class)]
#[CoversClass(SchemaEntry::class)]
final class DefaultsSchemaRegistryTest extends TestCase
{
    private string $defaultsDir;

    protected function setUp(): void
    {
        $this->defaultsDir = sys_get_temp_dir() . '/waaseyaa_schema_registry_test_' . uniqid();
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
    public function listReturnsEmptyArrayWhenNoSchemasExist(): void
    {
        $registry = new DefaultsSchemaRegistry($this->defaultsDir);

        $this->assertSame([], $registry->list());
    }

    #[Test]
    public function listReturnsSchemaEntryForEachSchemaFile(): void
    {
        $this->writeSchema('core.note', [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title'   => 'core.note',
            'x-waaseyaa' => [
                'entity_type'   => 'core.note',
                'version'       => '0.1.0',
                'compatibility' => 'liberal',
            ],
        ]);

        $registry = new DefaultsSchemaRegistry($this->defaultsDir);
        $entries  = $registry->list();

        $this->assertCount(1, $entries);
        $this->assertInstanceOf(SchemaEntry::class, $entries[0]);
    }

    #[Test]
    public function listPopulatesEntryFieldsFromSchemaFile(): void
    {
        $this->writeSchema('core.note', [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title'   => 'core.note',
            'x-waaseyaa' => [
                'entity_type'   => 'core.note',
                'version'       => '0.1.0',
                'compatibility' => 'liberal',
            ],
        ]);

        $entry = (new DefaultsSchemaRegistry($this->defaultsDir))->list()[0];

        $this->assertSame('core.note', $entry->id);
        $this->assertSame('0.1.0', $entry->version);
        $this->assertSame('liberal', $entry->compatibility);
        $this->assertStringEndsWith('core.note.schema.json', $entry->schemaPath);
    }

    #[Test]
    public function getReturnsNullWhenIdNotFound(): void
    {
        $registry = new DefaultsSchemaRegistry($this->defaultsDir);

        $this->assertNull($registry->get('nonexistent'));
    }

    #[Test]
    public function getReturnsEntryByEntityTypeId(): void
    {
        $this->writeSchema('core.note', [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title'   => 'core.note',
            'x-waaseyaa' => [
                'entity_type'   => 'core.note',
                'version'       => '0.1.0',
                'compatibility' => 'liberal',
            ],
        ]);

        $entry = (new DefaultsSchemaRegistry($this->defaultsDir))->get('core.note');

        $this->assertNotNull($entry);
        $this->assertSame('core.note', $entry->id);
    }

    #[Test]
    public function listIgnoresNonSchemaJsonFiles(): void
    {
        file_put_contents($this->defaultsDir . '/README.md', '# readme');
        file_put_contents($this->defaultsDir . '/core.note.yaml', 'id: core.note');
        $this->writeSchema('core.note', [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title'   => 'core.note',
            'x-waaseyaa' => [
                'entity_type'   => 'core.note',
                'version'       => '0.1.0',
                'compatibility' => 'liberal',
            ],
        ]);

        $entries = (new DefaultsSchemaRegistry($this->defaultsDir))->list();

        $this->assertCount(1, $entries);
    }

    #[Test]
    public function listIgnoresMalformedJsonFiles(): void
    {
        file_put_contents($this->defaultsDir . '/broken.schema.json', '{not-valid-json');

        $entries = (new DefaultsSchemaRegistry($this->defaultsDir))->list();

        $this->assertSame([], $entries);
    }

    #[Test]
    public function listIgnoresSchemasWithMissingXWaaseyaaExtension(): void
    {
        file_put_contents(
            $this->defaultsDir . '/noext.schema.json',
            json_encode(['$schema' => 'http://json-schema.org/draft-07/schema#', 'title' => 'noext']),
        );

        $entries = (new DefaultsSchemaRegistry($this->defaultsDir))->list();

        $this->assertSame([], $entries);
    }

    #[Test]
    public function listMultipleSchemasInAlphabeticalOrder(): void
    {
        $this->writeSchema('core.article', [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title'   => 'core.article',
            'x-waaseyaa' => ['entity_type' => 'core.article', 'version' => '0.2.0', 'compatibility' => 'strict'],
        ]);
        $this->writeSchema('core.note', [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title'   => 'core.note',
            'x-waaseyaa' => ['entity_type' => 'core.note', 'version' => '0.1.0', 'compatibility' => 'liberal'],
        ]);

        $entries = (new DefaultsSchemaRegistry($this->defaultsDir))->list();

        $this->assertCount(2, $entries);
        $this->assertSame('core.article', $entries[0]->id);
        $this->assertSame('core.note', $entries[1]->id);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function writeSchema(string $name, array $data): void
    {
        file_put_contents(
            $this->defaultsDir . '/' . $name . '.schema.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }
}
