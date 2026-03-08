<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\SchemaEntry;

#[CoversClass(SchemaEntry::class)]
final class SchemaEntryTest extends TestCase
{
    #[Test]
    public function toArrayReturnsExpectedShape(): void
    {
        $entry = new SchemaEntry(
            id:            'core.note',
            version:       '0.1.0',
            compatibility: 'liberal',
            schemaPath:    '/defaults/core.note.schema.json',
        );

        $this->assertSame([
            'id'            => 'core.note',
            'version'       => '0.1.0',
            'compatibility' => 'liberal',
            'schema_path'   => '/defaults/core.note.schema.json',
        ], $entry->toArray());
    }
}
