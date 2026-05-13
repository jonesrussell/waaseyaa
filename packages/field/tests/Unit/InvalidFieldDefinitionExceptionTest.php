<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Field\Exception\InvalidFieldDefinitionException;

#[CoversClass(InvalidFieldDefinitionException::class)]
final class InvalidFieldDefinitionExceptionTest extends TestCase
{
    #[Test]
    public function translatable_on_non_translatable_entity_type_is_invalid_argument_exception(): void
    {
        $exception = InvalidFieldDefinitionException::translatableOnNonTranslatableEntityType('body', 'article');

        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    #[Test]
    public function translatable_on_non_translatable_entity_type_contains_field_name(): void
    {
        $exception = InvalidFieldDefinitionException::translatableOnNonTranslatableEntityType('body', 'article');

        self::assertStringContainsString('body', $exception->getMessage());
    }

    #[Test]
    public function translatable_on_non_translatable_entity_type_contains_entity_type_id(): void
    {
        $exception = InvalidFieldDefinitionException::translatableOnNonTranslatableEntityType('body', 'article');

        self::assertStringContainsString('article', $exception->getMessage());
    }

    #[Test]
    public function system_key_marked_translatable_is_invalid_argument_exception(): void
    {
        $exception = InvalidFieldDefinitionException::systemKeyMarkedTranslatable('id');

        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    #[Test]
    public function system_key_marked_translatable_contains_field_name(): void
    {
        $exception = InvalidFieldDefinitionException::systemKeyMarkedTranslatable('langcode');

        self::assertStringContainsString('langcode', $exception->getMessage());
    }

    /** @return array<string, array{string}> */
    public static function systemKeyProvider(): array
    {
        return [
            'id' => ['id'],
            'uuid' => ['uuid'],
            'langcode' => ['langcode'],
            'default_langcode' => ['default_langcode'],
            'revision' => ['revision'],
        ];
    }

    #[Test]
    #[DataProvider('systemKeyProvider')]
    public function system_key_marked_translatable_mentions_the_key(string $fieldName): void
    {
        $exception = InvalidFieldDefinitionException::systemKeyMarkedTranslatable($fieldName);

        self::assertStringContainsString($fieldName, $exception->getMessage());
    }
}
