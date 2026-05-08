<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\Exception\InvalidArgumentDefinitionException;

#[CoversClass(ArgumentDefinition::class)]
final class ArgumentDefinitionTest extends TestCase
{
    // --- Valid construction ---

    #[Test]
    public function itConstructsWithRequiredMode(): void
    {
        $arg = new ArgumentDefinition(name: 'entity_type', mode: ArgumentMode::Required);

        self::assertSame('entity_type', $arg->name);
        self::assertSame(ArgumentMode::Required, $arg->mode);
        self::assertNull($arg->default);
        self::assertFalse($arg->isArray);
    }

    #[Test]
    public function itConstructsWithOptionalModeAndDefault(): void
    {
        $arg = new ArgumentDefinition(
            name: 'check_id',
            mode: ArgumentMode::Optional,
            description: 'Single check id.',
            default: 'all',
        );

        self::assertSame('check_id', $arg->name);
        self::assertSame(ArgumentMode::Optional, $arg->mode);
        self::assertSame('all', $arg->default);
        self::assertSame('Single check id.', $arg->description);
    }

    #[Test]
    public function itConstructsWithOptionalModeAndNullDefault(): void
    {
        $arg = new ArgumentDefinition(name: 'filter', mode: ArgumentMode::Optional);

        self::assertNull($arg->default);
    }

    #[Test]
    public function itConstructsArrayArgument(): void
    {
        $arg = new ArgumentDefinition(name: 'files', mode: ArgumentMode::Optional, isArray: true);

        self::assertTrue($arg->isArray);
        self::assertSame([], $arg->default); // normalised to []
    }

    #[Test]
    public function itNormalisesArrayDefaultToEmptyArrayWhenNull(): void
    {
        $arg = new ArgumentDefinition(
            name: 'tags',
            mode: ArgumentMode::Optional,
            default: null,
            isArray: true,
        );

        self::assertSame([], $arg->default);
    }

    #[Test]
    public function itPreservesExplicitArrayDefault(): void
    {
        $arg = new ArgumentDefinition(
            name: 'tags',
            mode: ArgumentMode::Optional,
            default: ['a', 'b'],
            isArray: true,
        );

        self::assertSame(['a', 'b'], $arg->default);
    }

    #[Test]
    public function itAllowsRequiredArrayArgument(): void
    {
        // Required + isArray is allowed; default is normalised to []
        $arg = new ArgumentDefinition(name: 'paths', mode: ArgumentMode::Required, isArray: true);

        self::assertSame([], $arg->default);
    }

    // --- Invalid name patterns ---

    #[Test]
    public function itThrowsOnNameStartingWithDigit(): void
    {
        $this->expectException(InvalidArgumentDefinitionException::class);

        new ArgumentDefinition(name: '1name');
    }

    #[Test]
    public function itThrowsOnNameWithUppercase(): void
    {
        $this->expectException(InvalidArgumentDefinitionException::class);

        new ArgumentDefinition(name: 'MyArg');
    }

    #[Test]
    public function itThrowsOnNameWithDashes(): void
    {
        $this->expectException(InvalidArgumentDefinitionException::class);

        new ArgumentDefinition(name: 'my-arg');
    }

    #[Test]
    public function itThrowsOnEmptyName(): void
    {
        $this->expectException(InvalidArgumentDefinitionException::class);

        new ArgumentDefinition(name: '');
    }

    #[Test]
    public function itThrowsOnNameWithSpecialChars(): void
    {
        $this->expectException(InvalidArgumentDefinitionException::class);

        new ArgumentDefinition(name: 'arg!');
    }

    // --- Default / mode invariant ---

    #[Test]
    public function itThrowsWhenRequiredNonArrayHasNonNullDefault(): void
    {
        $this->expectException(InvalidArgumentDefinitionException::class);
        $this->expectExceptionMessage('default must be null');

        new ArgumentDefinition(
            name: 'name',
            mode: ArgumentMode::Required,
            default: 'some_value',
            isArray: false,
        );
    }

    #[Test]
    public function itThrowsWhenRequiredNonArrayHasIntDefault(): void
    {
        $this->expectException(InvalidArgumentDefinitionException::class);

        new ArgumentDefinition(name: 'count', mode: ArgumentMode::Required, default: 0);
    }

    #[Test]
    public function itAllowsNullDefaultForRequired(): void
    {
        $arg = new ArgumentDefinition(name: 'name', mode: ArgumentMode::Required, default: null);

        self::assertNull($arg->default);
    }

    // --- Valid name edge cases ---

    #[Test]
    public function itAllowsSingleLetterName(): void
    {
        $arg = new ArgumentDefinition(name: 'a');

        self::assertSame('a', $arg->name);
    }

    #[Test]
    public function itAllowsNameWithUnderscoresAndDigits(): void
    {
        $arg = new ArgumentDefinition(name: 'my_arg_1');

        self::assertSame('my_arg_1', $arg->name);
    }
}
