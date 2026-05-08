<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Exception\InvalidOptionDefinitionException;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;

#[CoversClass(OptionDefinition::class)]
final class OptionDefinitionTest extends TestCase
{
    // --- Valid construction ---

    #[Test]
    public function itConstructsNoneMode(): void
    {
        $opt = new OptionDefinition(name: 'json', mode: OptionMode::None);

        self::assertSame('json', $opt->name);
        self::assertNull($opt->shortcut);
        self::assertSame(OptionMode::None, $opt->mode);
        self::assertFalse($opt->default); // normalised
    }

    #[Test]
    public function itConstructsRequiredModeWithShortcut(): void
    {
        $opt = new OptionDefinition(
            name: 'timeout',
            shortcut: 't',
            mode: OptionMode::Required,
            description: 'Timeout in seconds.',
            default: 30,
        );

        self::assertSame('timeout', $opt->name);
        self::assertSame('t', $opt->shortcut);
        self::assertSame(OptionMode::Required, $opt->mode);
        self::assertSame(30, $opt->default);
    }

    #[Test]
    public function itConstructsOptionalMode(): void
    {
        $opt = new OptionDefinition(name: 'format', mode: OptionMode::Optional, default: 'text');

        self::assertSame('text', $opt->default);
    }

    #[Test]
    public function itConstructsArrayMode(): void
    {
        $opt = new OptionDefinition(name: 'tag', mode: OptionMode::Array_);

        self::assertSame([], $opt->default); // normalised to []
    }

    #[Test]
    public function itConstructsArrayModePreservesExplicitDefault(): void
    {
        $opt = new OptionDefinition(name: 'tag', mode: OptionMode::Array_, default: ['a']);

        self::assertSame(['a'], $opt->default);
    }

    #[Test]
    public function itConstructsNegatableMode(): void
    {
        $opt = new OptionDefinition(name: 'ansi', mode: OptionMode::Negatable);

        self::assertNull($opt->default); // normalised to null
    }

    #[Test]
    public function itNormalisesNoneModeDefaultToFalse(): void
    {
        $opt = new OptionDefinition(name: 'debug', mode: OptionMode::None, default: null);

        self::assertFalse($opt->default);
    }

    #[Test]
    public function itNormalisesNegatableDefaultToNull(): void
    {
        $opt = new OptionDefinition(name: 'ansi', mode: OptionMode::Negatable, default: false);

        self::assertNull($opt->default);
    }

    // --- Invalid name ---

    #[Test]
    public function itThrowsOnNameStartingWithDigit(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: '2opt');
    }

    #[Test]
    public function itThrowsOnNameWithUnderscore(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: 'my_opt');
    }

    #[Test]
    public function itThrowsOnNameWithUppercase(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: 'MyOpt');
    }

    #[Test]
    public function itThrowsOnEmptyName(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: '');
    }

    // --- Reserved names ---

    #[Test]
    public function itThrowsOnReservedNameHelp(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('reserved');

        new OptionDefinition(name: 'help');
    }

    #[Test]
    public function itThrowsOnReservedNameVerbose(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: 'verbose');
    }

    #[Test]
    public function itThrowsOnReservedNameQuiet(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: 'quiet');
    }

    #[Test]
    public function itThrowsOnReservedNameNoInteraction(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: 'no-interaction');
    }

    #[Test]
    public function itThrowsOnReservedNameVersion(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: 'version');
    }

    // --- Reserved shortcuts ---

    #[Test]
    public function itThrowsOnReservedShortcutH(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('reserved');

        new OptionDefinition(name: 'json', shortcut: 'h');
    }

    #[Test]
    public function itThrowsOnReservedShortcutV(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: 'json', shortcut: 'v');
    }

    #[Test]
    public function itThrowsOnReservedShortcutQ(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: 'json', shortcut: 'q');
    }

    #[Test]
    public function itThrowsOnShortcutLongerThanOneLetter(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('exactly one ASCII letter');

        new OptionDefinition(name: 'format', shortcut: 'fo');
    }

    #[Test]
    public function itThrowsOnShortcutDigit(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);

        new OptionDefinition(name: 'format', shortcut: '1');
    }

    // --- Negatable constraint ---

    #[Test]
    public function itThrowsWhenNegatableNameStartsWithNo(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('no-');

        new OptionDefinition(name: 'no-ansi', mode: OptionMode::Negatable);
    }
}
