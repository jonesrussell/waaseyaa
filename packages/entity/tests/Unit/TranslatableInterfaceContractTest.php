<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\TranslatableEntityTrait;
use Waaseyaa\Entity\TranslatableInterface;

#[CoversNothing]
final class TranslatableInterfaceContractTest extends TestCase
{
    #[Test]
    public function interfaceDeclaresFieldLangcodeMethod(): void
    {
        $rc = new \ReflectionClass(TranslatableInterface::class);

        $this->assertTrue($rc->hasMethod('fieldLangcode'), 'Interface must declare fieldLangcode()');

        $method = $rc->getMethod('fieldLangcode');
        $this->assertTrue($method->isPublic(), 'fieldLangcode() must be public');
        $this->assertTrue($method->isAbstract(), 'Interface method must be abstract');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'fieldLangcode() must have exactly one parameter');
        $this->assertSame('fieldName', $params[0]->getName());
        $this->assertSame('string', (string) $params[0]->getType());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'Return type must be declared');
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType, 'Return type must be a named type (?string)');
        $this->assertTrue($returnType->allowsNull(), 'Return type must be nullable');
        $this->assertSame('string', $returnType->getName());
    }

    #[Test]
    public function traitSatisfiesFieldLangcodeViaAnonymousClass(): void
    {
        $impl = new class implements TranslatableInterface {
            use TranslatableEntityTrait;

            // Satisfy the abstract contract required by TranslatableEntityTrait.
            public function getEntityType(): \Waaseyaa\Entity\EntityTypeInterface
            {
                throw new \LogicException('getEntityType() not needed for fieldLangcode() contract test');
            }

            // Minimal stubs for the rest of TranslatableInterface.
            // TranslatableEntityTrait provides fieldLangcode() — that is the point of this test.
            public function defaultLangcode(): string
            {
                return 'en';
            }

            public function activeLangcode(): string
            {
                return 'en';
            }

            public function language(): string
            {
                return 'en';
            }

            public function hasTranslation(string $langcode): bool
            {
                return false;
            }

            public function getTranslation(string $langcode): static
            {
                return $this;
            }

            public function addTranslation(string $langcode): static
            {
                return $this;
            }

            public function removeTranslation(string $langcode): void {}

            public function translations(): iterable
            {
                return [];
            }

            public function getTranslationLanguages(): array
            {
                return ['en'];
            }
        };

        $this->assertInstanceOf(TranslatableInterface::class, $impl);

        // fieldLangcode() must be callable without error.
        // The return type (?string) is enforced by the interface declaration itself;
        // calling the method is sufficient to confirm the trait satisfies the contract.
        $impl->fieldLangcode('title');
    }
}
