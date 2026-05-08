<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Exception\ParseException;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\CLI\Parser\ArgvParser;
use Waaseyaa\CLI\Parser\ParseErrorKind;

#[CoversClass(ArgvParser::class)]
final class ArgvParserTest extends TestCase
{
    private ArgvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ArgvParser();
    }

    // -------------------------------------------------------------------------
    // Helper factories
    // -------------------------------------------------------------------------

    private function cmd(
        array $arguments = [],
        array $options = [],
    ): CommandDefinition {
        return new CommandDefinition(
            name: 'test',
            description: 'Test command.',
            arguments: $arguments,
            options: $options,
            handler: static function (CliIO $io): int {
                return 0;
            },
        );
    }

    private function requiredArg(string $name): ArgumentDefinition
    {
        return new ArgumentDefinition(name: $name, mode: ArgumentMode::Required);
    }

    private function optionalArg(string $name, mixed $default = null): ArgumentDefinition
    {
        return new ArgumentDefinition(name: $name, mode: ArgumentMode::Optional, default: $default);
    }

    private function arrayArg(string $name): ArgumentDefinition
    {
        return new ArgumentDefinition(name: $name, mode: ArgumentMode::Optional, isArray: true);
    }

    private function noneOpt(string $name, ?string $shortcut = null): OptionDefinition
    {
        return new OptionDefinition(name: $name, shortcut: $shortcut, mode: OptionMode::None);
    }

    private function requiredOpt(string $name, ?string $shortcut = null, mixed $default = null): OptionDefinition
    {
        return new OptionDefinition(name: $name, shortcut: $shortcut, mode: OptionMode::Required, default: $default);
    }

    private function optionalOpt(string $name, mixed $default = null): OptionDefinition
    {
        return new OptionDefinition(name: $name, mode: OptionMode::Optional, default: $default);
    }

    private function arrayOpt(string $name): OptionDefinition
    {
        return new OptionDefinition(name: $name, mode: OptionMode::Array_);
    }

    private function negatableOpt(string $name): OptionDefinition
    {
        return new OptionDefinition(name: $name, mode: OptionMode::Negatable);
    }

    // -------------------------------------------------------------------------
    // Positional arguments
    // -------------------------------------------------------------------------

    #[Test]
    public function itParsesNoArguments(): void
    {
        $cmd = $this->cmd();
        $result = $this->parser->parse([], $cmd);

        self::assertSame([], $result->arguments);
        self::assertSame([], $result->rawArgv);
    }

    #[Test]
    public function itParsesRequiredPositional(): void
    {
        $cmd = $this->cmd([$this->requiredArg('name')]);
        $result = $this->parser->parse(['john'], $cmd);

        self::assertSame('john', $result->arguments['name']);
    }

    #[Test]
    public function itThrowsOnMissingRequiredPositional(): void
    {
        $cmd = $this->cmd([$this->requiredArg('name')]);

        $this->expectException(ParseException::class);

        try {
            $this->parser->parse([], $cmd);
        } catch (ParseException $e) {
            self::assertSame(ParseErrorKind::MissingRequiredArgument, $e->parseError->kind);
            self::assertSame('name', $e->parseError->offendingToken);
            throw $e;
        }
    }

    #[Test]
    public function itParsesOptionalPositionalWithValue(): void
    {
        $cmd = $this->cmd([$this->optionalArg('filter', 'all')]);
        $result = $this->parser->parse(['active'], $cmd);

        self::assertSame('active', $result->arguments['filter']);
    }

    #[Test]
    public function itUsesDefaultForAbsentOptionalPositional(): void
    {
        $cmd = $this->cmd([$this->optionalArg('filter', 'all')]);
        $result = $this->parser->parse([], $cmd);

        self::assertSame('all', $result->arguments['filter']);
    }

    #[Test]
    public function itParsesMultiplePositionals(): void
    {
        $cmd = $this->cmd([
            $this->requiredArg('src'),
            $this->requiredArg('dest'),
        ]);
        $result = $this->parser->parse(['from', 'to'], $cmd);

        self::assertSame('from', $result->arguments['src']);
        self::assertSame('to', $result->arguments['dest']);
    }

    #[Test]
    public function itThrowsOnTooManyPositionals(): void
    {
        $cmd = $this->cmd([$this->requiredArg('name')]);

        $this->expectException(ParseException::class);

        try {
            $this->parser->parse(['one', 'two'], $cmd);
        } catch (ParseException $e) {
            self::assertSame(ParseErrorKind::TooManyArguments, $e->parseError->kind);
            throw $e;
        }
    }

    #[Test]
    public function itParsesArrayArgument(): void
    {
        $cmd = $this->cmd([$this->arrayArg('files')]);
        $result = $this->parser->parse(['a.php', 'b.php', 'c.php'], $cmd);

        self::assertSame(['a.php', 'b.php', 'c.php'], $result->arguments['files']);
    }

    #[Test]
    public function itParsesArrayArgumentWithNoTokensToDefaultEmpty(): void
    {
        $cmd = $this->cmd([$this->arrayArg('files')]);
        $result = $this->parser->parse([], $cmd);

        self::assertSame([], $result->arguments['files']);
    }

    #[Test]
    public function itParsesRequiredPlusArrayArgument(): void
    {
        $cmd = $this->cmd([
            $this->requiredArg('name'),
            $this->arrayArg('tags'),
        ]);
        $result = $this->parser->parse(['myname', 'x', 'y'], $cmd);

        self::assertSame('myname', $result->arguments['name']);
        self::assertSame(['x', 'y'], $result->arguments['tags']);
    }

    // -------------------------------------------------------------------------
    // Boolean (None-mode) options
    // -------------------------------------------------------------------------

    #[Test]
    public function itParsesNoneOptionLongForm(): void
    {
        $cmd = $this->cmd(options: [$this->noneOpt('json')]);
        $result = $this->parser->parse(['--json'], $cmd);

        self::assertTrue($result->options['json']);
    }

    #[Test]
    public function itDefaultsNoneOptionToFalseWhenAbsent(): void
    {
        $cmd = $this->cmd(options: [$this->noneOpt('json')]);
        $result = $this->parser->parse([], $cmd);

        self::assertFalse($result->options['json']);
    }

    #[Test]
    public function itParsesNoneOptionShortForm(): void
    {
        $cmd = $this->cmd(options: [$this->noneOpt('json', 'j')]);
        $result = $this->parser->parse(['-j'], $cmd);

        self::assertTrue($result->options['json']);
    }

    // -------------------------------------------------------------------------
    // Required-mode options
    // -------------------------------------------------------------------------

    #[Test]
    public function itParsesRequiredOptionWithEqSyntax(): void
    {
        $cmd = $this->cmd(options: [$this->requiredOpt('timeout')]);
        $result = $this->parser->parse(['--timeout=30'], $cmd);

        self::assertSame('30', $result->options['timeout']);
    }

    #[Test]
    public function itParsesRequiredOptionWithSpaceSyntax(): void
    {
        $cmd = $this->cmd(options: [$this->requiredOpt('timeout')]);
        $result = $this->parser->parse(['--timeout', '30'], $cmd);

        self::assertSame('30', $result->options['timeout']);
    }

    #[Test]
    public function itParsesRequiredShortOptionWithSpaceSyntax(): void
    {
        $cmd = $this->cmd(options: [$this->requiredOpt('timeout', 't')]);
        $result = $this->parser->parse(['-t', '30'], $cmd);

        self::assertSame('30', $result->options['timeout']);
    }

    #[Test]
    public function itThrowsWhenRequiredOptionValueMissing(): void
    {
        $cmd = $this->cmd(options: [$this->requiredOpt('timeout')]);

        $this->expectException(ParseException::class);

        try {
            $this->parser->parse(['--timeout'], $cmd);
        } catch (ParseException $e) {
            self::assertSame(ParseErrorKind::MissingRequiredOptionValue, $e->parseError->kind);
            throw $e;
        }
    }

    #[Test]
    public function itThrowsWhenRequiredOptionFollowedByAnotherFlag(): void
    {
        $cmd = $this->cmd(options: [
            $this->requiredOpt('timeout'),
            $this->noneOpt('json'),
        ]);

        $this->expectException(ParseException::class);

        try {
            $this->parser->parse(['--timeout', '--json'], $cmd);
        } catch (ParseException $e) {
            self::assertSame(ParseErrorKind::MissingRequiredOptionValue, $e->parseError->kind);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Optional-mode options
    // -------------------------------------------------------------------------

    #[Test]
    public function itParsesOptionalOptionWithEqSyntax(): void
    {
        $cmd = $this->cmd(options: [$this->optionalOpt('format', 'text')]);
        $result = $this->parser->parse(['--format=json'], $cmd);

        self::assertSame('json', $result->options['format']);
    }

    #[Test]
    public function itParsesOptionalOptionBarePresenceYieldsNull(): void
    {
        $cmd = $this->cmd(options: [$this->optionalOpt('format', 'text')]);
        $result = $this->parser->parse(['--format'], $cmd);

        self::assertNull($result->options['format']);
    }

    #[Test]
    public function itUsesDefaultForAbsentOptionalOption(): void
    {
        $cmd = $this->cmd(options: [$this->optionalOpt('format', 'text')]);
        $result = $this->parser->parse([], $cmd);

        self::assertSame('text', $result->options['format']);
    }

    // -------------------------------------------------------------------------
    // Array-mode options
    // -------------------------------------------------------------------------

    #[Test]
    public function itParsesArrayOptionSingleValue(): void
    {
        $cmd = $this->cmd(options: [$this->arrayOpt('tag')]);
        $result = $this->parser->parse(['--tag=foo'], $cmd);

        self::assertSame(['foo'], $result->options['tag']);
    }

    #[Test]
    public function itAccumulatesArrayOptionValues(): void
    {
        $cmd = $this->cmd(options: [$this->arrayOpt('tag')]);
        $result = $this->parser->parse(['--tag=a', '--tag=b', '--tag', 'c'], $cmd);

        self::assertSame(['a', 'b', 'c'], $result->options['tag']);
    }

    #[Test]
    public function itDefaultsArrayOptionToEmptyArrayWhenAbsent(): void
    {
        $cmd = $this->cmd(options: [$this->arrayOpt('tag')]);
        $result = $this->parser->parse([], $cmd);

        self::assertSame([], $result->options['tag']);
    }

    // -------------------------------------------------------------------------
    // Negatable options
    // -------------------------------------------------------------------------

    #[Test]
    public function itParsesNegatableOptionPositive(): void
    {
        $cmd = $this->cmd(options: [$this->negatableOpt('ansi')]);
        $result = $this->parser->parse(['--ansi'], $cmd);

        self::assertTrue($result->options['ansi']);
    }

    #[Test]
    public function itParsesNegatableOptionNegated(): void
    {
        $cmd = $this->cmd(options: [$this->negatableOpt('ansi')]);
        $result = $this->parser->parse(['--no-ansi'], $cmd);

        self::assertFalse($result->options['ansi']);
    }

    #[Test]
    public function itDefaultsNegatableOptionToNull(): void
    {
        $cmd = $this->cmd(options: [$this->negatableOpt('ansi')]);
        $result = $this->parser->parse([], $cmd);

        self::assertNull($result->options['ansi']);
    }

    // -------------------------------------------------------------------------
    // Stacked short flags (None-mode only)
    // -------------------------------------------------------------------------

    #[Test]
    public function itParsesStackedShortFlags(): void
    {
        $cmd = $this->cmd(options: [
            $this->noneOpt('all', 'a'),
            $this->noneOpt('long', 'l'),
            $this->noneOpt('size', 's'),
        ]);
        $result = $this->parser->parse(['-als'], $cmd);

        self::assertTrue($result->options['all']);
        self::assertTrue($result->options['long']);
        self::assertTrue($result->options['size']);
    }

    #[Test]
    public function itThrowsOnGluedShortOptionForNonNoneMode(): void
    {
        $cmd = $this->cmd(options: [$this->requiredOpt('format', 'f')]);

        $this->expectException(ParseException::class);

        $this->parser->parse(['-ftext'], $cmd);
    }

    #[Test]
    public function itThrowsOnStackedFlagsContainingNonNoneMode(): void
    {
        $cmd = $this->cmd(options: [
            $this->noneOpt('all', 'a'),
            $this->requiredOpt('format', 'f'),
        ]);

        $this->expectException(ParseException::class);

        $this->parser->parse(['-af'], $cmd);
    }

    // -------------------------------------------------------------------------
    // End-of-options sentinel (--)
    // -------------------------------------------------------------------------

    #[Test]
    public function itTreatsTokensAfterDoubleDashAsPositionals(): void
    {
        $cmd = $this->cmd(
            arguments: [$this->arrayArg('files')],
            options: [$this->noneOpt('json')],
        );
        $result = $this->parser->parse(['--', '--not-an-option', 'file.php'], $cmd);

        self::assertSame(['--not-an-option', 'file.php'], $result->arguments['files']);
        self::assertFalse($result->options['json']);
    }

    #[Test]
    public function itParsesOptionsBeforeAndPositionalsAfterSentinel(): void
    {
        $cmd = $this->cmd(
            arguments: [$this->requiredArg('name')],
            options: [$this->noneOpt('json')],
        );
        $result = $this->parser->parse(['--json', '--', 'alice'], $cmd);

        self::assertTrue($result->options['json']);
        self::assertSame('alice', $result->arguments['name']);
    }

    // -------------------------------------------------------------------------
    // Unknown options
    // -------------------------------------------------------------------------

    #[Test]
    public function itThrowsOnUnknownLongOption(): void
    {
        $cmd = $this->cmd();

        $this->expectException(ParseException::class);

        try {
            $this->parser->parse(['--unknown'], $cmd);
        } catch (ParseException $e) {
            self::assertSame(ParseErrorKind::UnknownOption, $e->parseError->kind);
            self::assertSame('--unknown', $e->parseError->offendingToken);
            throw $e;
        }
    }

    #[Test]
    public function itThrowsOnUnknownShortOption(): void
    {
        $cmd = $this->cmd();

        $this->expectException(ParseException::class);

        try {
            $this->parser->parse(['-x'], $cmd);
        } catch (ParseException $e) {
            self::assertSame(ParseErrorKind::UnknownOption, $e->parseError->kind);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Mixed options and positionals
    // -------------------------------------------------------------------------

    #[Test]
    public function itParsesOptionsInterleavedWithPositionals(): void
    {
        $cmd = $this->cmd(
            arguments: [$this->requiredArg('name')],
            options: [$this->noneOpt('json')],
        );
        $result = $this->parser->parse(['--json', 'alice'], $cmd);

        self::assertTrue($result->options['json']);
        self::assertSame('alice', $result->arguments['name']);
    }

    #[Test]
    public function itStoresRawArgv(): void
    {
        $cmd = $this->cmd(options: [$this->noneOpt('json')]);
        $tokens = ['--json'];
        $result = $this->parser->parse($tokens, $cmd);

        self::assertSame($tokens, $result->rawArgv);
    }

    // -------------------------------------------------------------------------
    // ParsedInput structure
    // -------------------------------------------------------------------------

    #[Test]
    public function itReturnsImmutableParsedInput(): void
    {
        $cmd = $this->cmd(
            arguments: [$this->optionalArg('name', 'default')],
            options: [$this->noneOpt('json')],
        );
        $result = $this->parser->parse(['--json', 'hello'], $cmd);

        self::assertSame('hello', $result->arguments['name']);
        self::assertTrue($result->options['json']);
    }
}
