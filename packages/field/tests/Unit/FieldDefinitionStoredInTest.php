<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Field\FieldDefinition;

#[CoversClass(FieldDefinition::class)]
final class FieldDefinitionStoredInTest extends TestCase
{
    private function makeDefinition(string $name = 'body', string $type = 'text'): FieldDefinition
    {
        return new FieldDefinition(name: $name, type: $type);
    }

    // ---------------------------------------------------------------------------
    // storedIn()
    // ---------------------------------------------------------------------------

    #[Test]
    public function stored_in_returns_new_instance(): void
    {
        $original = $this->makeDefinition();
        $modified = $original->storedIn('sql-column');

        self::assertNotSame($original, $modified);
    }

    #[Test]
    public function stored_in_sets_backend_id(): void
    {
        $definition = $this->makeDefinition()->storedIn('sql-column');

        self::assertSame('sql-column', $definition->getBackendId());
    }

    #[Test]
    public function stored_in_does_not_mutate_original(): void
    {
        $original = $this->makeDefinition();
        $original->storedIn('sql-column');

        self::assertNull($original->getBackendId());
    }

    #[Test]
    public function stored_in_preserves_all_other_properties(): void
    {
        $original = new FieldDefinition(
            name: 'title',
            type: 'string',
            cardinality: 3,
            label: 'Title',
            required: true,
        );

        $modified = $original->storedIn('my-custom-backend');

        self::assertSame('title', $modified->getName());
        self::assertSame('string', $modified->getType());
        self::assertSame(3, $modified->getCardinality());
        self::assertSame('Title', $modified->getLabel());
        self::assertTrue($modified->isRequired());
        self::assertSame('my-custom-backend', $modified->getBackendId());
    }

    #[Test]
    public function stored_in_can_be_chained(): void
    {
        // Later storedIn() call wins.
        $definition = $this->makeDefinition()
            ->storedIn('first-backend')
            ->storedIn('second-backend');

        self::assertSame('second-backend', $definition->getBackendId());
    }

    #[Test]
    public function get_backend_id_returns_null_by_default(): void
    {
        $definition = $this->makeDefinition();

        self::assertNull($definition->getBackendId());
    }

    // ---------------------------------------------------------------------------
    // indexed()
    // ---------------------------------------------------------------------------

    #[Test]
    public function indexed_returns_new_instance(): void
    {
        $original = $this->makeDefinition();
        $modified = $original->indexed();

        self::assertNotSame($original, $modified);
    }

    #[Test]
    public function indexed_sets_field_indexed_flag(): void
    {
        $definition = $this->makeDefinition()->indexed();

        self::assertTrue($definition->isIndexed());
    }

    #[Test]
    public function indexed_does_not_mutate_original(): void
    {
        $original = $this->makeDefinition();
        $original->indexed();

        self::assertFalse($original->isIndexed());
    }

    #[Test]
    public function is_indexed_returns_false_by_default(): void
    {
        $definition = $this->makeDefinition();

        self::assertFalse($definition->isIndexed());
    }

    #[Test]
    public function indexed_preserves_backend_id(): void
    {
        $definition = $this->makeDefinition()
            ->storedIn('sql-column')
            ->indexed();

        self::assertSame('sql-column', $definition->getBackendId());
        self::assertTrue($definition->isIndexed());
    }

    #[Test]
    public function stored_in_preserves_indexed_flag(): void
    {
        $definition = $this->makeDefinition()
            ->indexed()
            ->storedIn('sql-column');

        self::assertTrue($definition->isIndexed());
        self::assertSame('sql-column', $definition->getBackendId());
    }
}
