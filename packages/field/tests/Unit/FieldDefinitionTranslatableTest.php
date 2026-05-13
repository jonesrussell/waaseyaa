<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Field\FieldDefinition;

#[CoversClass(FieldDefinition::class)]
final class FieldDefinitionTranslatableTest extends TestCase
{
    private function makeDefinition(string $name = 'body', string $type = 'text'): FieldDefinition
    {
        return new FieldDefinition(name: $name, type: $type);
    }

    #[Test]
    public function is_translatable_returns_false_by_default(): void
    {
        $definition = $this->makeDefinition();

        self::assertFalse($definition->isTranslatable());
    }

    #[Test]
    public function translatable_returns_new_instance(): void
    {
        $original = $this->makeDefinition();
        $modified = $original->translatable();

        self::assertNotSame($original, $modified);
    }

    #[Test]
    public function translatable_sets_flag_to_true_by_default(): void
    {
        $definition = $this->makeDefinition()->translatable();

        self::assertTrue($definition->isTranslatable());
    }

    #[Test]
    public function translatable_accepts_explicit_true(): void
    {
        $definition = $this->makeDefinition()->translatable(true);

        self::assertTrue($definition->isTranslatable());
    }

    #[Test]
    public function translatable_accepts_explicit_false(): void
    {
        $definition = $this->makeDefinition()->translatable(false);

        self::assertFalse($definition->isTranslatable());
    }

    #[Test]
    public function translatable_does_not_mutate_original(): void
    {
        $original = $this->makeDefinition();
        $original->translatable();

        self::assertFalse($original->isTranslatable());
    }

    #[Test]
    public function translatable_preserves_all_other_properties(): void
    {
        $original = new FieldDefinition(
            name: 'title',
            type: 'string',
            cardinality: 3,
            label: 'Title',
            required: true,
            description: 'The title field',
            readOnly: false,
        );

        $modified = $original->translatable();

        self::assertSame('title', $modified->getName());
        self::assertSame('string', $modified->getType());
        self::assertSame(3, $modified->getCardinality());
        self::assertSame('Title', $modified->getLabel());
        self::assertTrue($modified->isRequired());
        self::assertSame('The title field', $modified->getDescription());
        self::assertTrue($modified->isTranslatable());
    }

    #[Test]
    public function translatable_composes_with_stored_in(): void
    {
        $definition = $this->makeDefinition()
            ->translatable()
            ->storedIn('sql-column');

        self::assertTrue($definition->isTranslatable());
        self::assertSame('sql-column', $definition->getBackendId());
    }

    #[Test]
    public function stored_in_preserves_translatable_flag(): void
    {
        $definition = $this->makeDefinition()
            ->storedIn('sql-column')
            ->translatable();

        self::assertSame('sql-column', $definition->getBackendId());
        self::assertTrue($definition->isTranslatable());
    }

    #[Test]
    public function translatable_composes_with_indexed(): void
    {
        $definition = $this->makeDefinition()
            ->translatable()
            ->indexed();

        self::assertTrue($definition->isTranslatable());
        self::assertTrue($definition->isIndexed());
    }

    #[Test]
    public function indexed_preserves_translatable_flag(): void
    {
        $definition = $this->makeDefinition()
            ->indexed()
            ->translatable();

        self::assertTrue($definition->isIndexed());
        self::assertTrue($definition->isTranslatable());
    }

    #[Test]
    public function translatable_can_be_toggled_off(): void
    {
        $definition = $this->makeDefinition()
            ->translatable(true)
            ->translatable(false);

        self::assertFalse($definition->isTranslatable());
    }
}
