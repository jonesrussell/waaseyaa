<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Exception\EntityTranslationException;

#[CoversClass(EntityTranslationException::class)]
final class EntityTranslationExceptionTest extends TestCase
{
    #[Test]
    public function translationNotFoundContainsLangcode(): void
    {
        $ex = EntityTranslationException::translationNotFound('fr');

        self::assertInstanceOf(EntityTranslationException::class, $ex);
        self::assertInstanceOf(\DomainException::class, $ex);
        self::assertStringContainsString('fr', $ex->getMessage());
    }

    #[Test]
    public function cannotRemoveDefaultContainsLangcode(): void
    {
        $ex = EntityTranslationException::cannotRemoveDefault('en');

        self::assertInstanceOf(EntityTranslationException::class, $ex);
        self::assertStringContainsString('en', $ex->getMessage());
    }

    #[Test]
    public function langcodeRequiredProducesMessage(): void
    {
        $ex = EntityTranslationException::langcodeRequired();

        self::assertInstanceOf(EntityTranslationException::class, $ex);
        self::assertNotEmpty($ex->getMessage());
    }

    #[Test]
    public function notTranslatableContainsEntityTypeId(): void
    {
        $ex = EntityTranslationException::notTranslatable('article');

        self::assertInstanceOf(EntityTranslationException::class, $ex);
        self::assertStringContainsString('article', $ex->getMessage());
    }

    #[Test]
    public function translationAlreadyExistsContainsLangcode(): void
    {
        $ex = EntityTranslationException::translationAlreadyExists('de');

        self::assertInstanceOf(EntityTranslationException::class, $ex);
        self::assertStringContainsString('de', $ex->getMessage());
    }
}
