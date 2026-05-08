<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Exception\InvalidCommandDefinitionException;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;

#[CoversClass(CommandDefinition::class)]
final class CommandDefinitionTest extends TestCase
{
    private function makeIo(): CliIO
    {
        return $this->createStub(CliIO::class);
    }

    private function closureHandler(): \Closure
    {
        return static function (CliIO $io): int {
            return 0;
        };
    }

    // --- Valid construction ---

    #[Test]
    public function itConstructsMinimalCommand(): void
    {
        $cmd = new CommandDefinition(
            name: 'list',
            description: 'List all commands.',
            handler: $this->closureHandler(),
        );

        self::assertSame('list', $cmd->name);
        self::assertSame('List all commands.', $cmd->description);
        self::assertSame([], $cmd->arguments);
        self::assertSame([], $cmd->options);
        self::assertInstanceOf(\Closure::class, $cmd->handler);
        self::assertNull($cmd->handlerReference);
    }

    #[Test]
    public function itConstructsWithArguments(): void
    {
        $arg = new ArgumentDefinition(name: 'entity_type', mode: ArgumentMode::Required);

        $cmd = new CommandDefinition(
            name: 'entity:create',
            description: 'Create an entity.',
            arguments: [$arg],
            handler: $this->closureHandler(),
        );

        self::assertCount(1, $cmd->arguments);
        self::assertSame('entity_type', $cmd->arguments[0]->name);
    }

    #[Test]
    public function itConstructsWithOptions(): void
    {
        $opt = new OptionDefinition(name: 'json', mode: OptionMode::None);

        $cmd = new CommandDefinition(
            name: 'health:check',
            description: 'Run health checks.',
            options: [$opt],
            handler: $this->closureHandler(),
        );

        self::assertCount(1, $cmd->options);
        self::assertSame('json', $cmd->options[0]->name);
    }

    #[Test]
    public function itStoresHandlerReference(): void
    {
        $cmd = new CommandDefinition(
            name: 'health:check',
            description: 'Run health checks.',
            handler: ['Some\Handler', 'execute'],
        );

        self::assertSame(['Some\Handler', 'execute'], $cmd->handlerReference);
        self::assertInstanceOf(\Closure::class, $cmd->handler);
    }

    #[Test]
    public function itNormalisesArrayHandlerToClosureThatThrowsWithoutContainer(): void
    {
        $cmd = new CommandDefinition(
            name: 'health:check',
            description: 'Run health checks.',
            handler: ['Some\Handler', 'execute'],
        );

        $this->expectException(\LogicException::class);
        ($cmd->handler)($this->makeIo());
    }

    #[Test]
    public function itConstructsWithColonSeparatedName(): void
    {
        $cmd = new CommandDefinition(
            name: 'make:migration',
            description: 'Make migration.',
            handler: $this->closureHandler(),
        );

        self::assertSame('make:migration', $cmd->name);
    }

    // --- Invalid name ---

    #[Test]
    public function itThrowsOnInvalidName(): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);

        new CommandDefinition(
            name: 'My_Command',
            description: 'Bad name.',
            handler: $this->closureHandler(),
        );
    }

    #[Test]
    public function itThrowsOnNameWithUnderscore(): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);

        new CommandDefinition(
            name: 'do_thing',
            description: 'Bad name.',
            handler: $this->closureHandler(),
        );
    }

    // --- Missing handler ---

    #[Test]
    public function itThrowsWhenHandlerIsNull(): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);
        $this->expectExceptionMessage('handler');

        new CommandDefinition(
            name: 'list',
            description: 'Needs handler.',
            handler: null,
        );
    }

    // --- Argument invariants ---

    #[Test]
    public function itThrowsOnDuplicateArgumentName(): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);
        $this->expectExceptionMessage('Duplicate argument');

        new CommandDefinition(
            name: 'test',
            description: 'Dup arg.',
            arguments: [
                new ArgumentDefinition(name: 'name', mode: ArgumentMode::Required),
                new ArgumentDefinition(name: 'name', mode: ArgumentMode::Optional),
            ],
            handler: $this->closureHandler(),
        );
    }

    #[Test]
    public function itThrowsWhenRequiredFollowsOptionalArgument(): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);
        $this->expectExceptionMessage('may not follow');

        new CommandDefinition(
            name: 'test',
            description: 'Bad order.',
            arguments: [
                new ArgumentDefinition(name: 'first', mode: ArgumentMode::Optional),
                new ArgumentDefinition(name: 'second', mode: ArgumentMode::Required),
            ],
            handler: $this->closureHandler(),
        );
    }

    #[Test]
    public function itThrowsOnMultipleArrayArguments(): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);
        $this->expectExceptionMessage('array-mode');

        new CommandDefinition(
            name: 'test',
            description: 'Too many arrays.',
            arguments: [
                new ArgumentDefinition(name: 'files', mode: ArgumentMode::Optional, isArray: true),
                new ArgumentDefinition(name: 'more', mode: ArgumentMode::Optional, isArray: true),
            ],
            handler: $this->closureHandler(),
        );
    }

    #[Test]
    public function itThrowsWhenArrayArgumentIsNotLast(): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);
        $this->expectExceptionMessage('last');

        new CommandDefinition(
            name: 'test',
            description: 'Array not last.',
            arguments: [
                new ArgumentDefinition(name: 'files', mode: ArgumentMode::Optional, isArray: true),
                new ArgumentDefinition(name: 'extra', mode: ArgumentMode::Optional),
            ],
            handler: $this->closureHandler(),
        );
    }

    // --- Option invariants ---

    #[Test]
    public function itThrowsOnDuplicateOptionName(): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);
        $this->expectExceptionMessage('Duplicate option name');

        new CommandDefinition(
            name: 'test',
            description: 'Dup opt.',
            options: [
                new OptionDefinition(name: 'json', mode: OptionMode::None),
                new OptionDefinition(name: 'json', mode: OptionMode::None),
            ],
            handler: $this->closureHandler(),
        );
    }

    #[Test]
    public function itThrowsOnDuplicateOptionShortcut(): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);
        $this->expectExceptionMessage('Duplicate option shortcut');

        new CommandDefinition(
            name: 'test',
            description: 'Dup shortcut.',
            options: [
                new OptionDefinition(name: 'json', shortcut: 'j', mode: OptionMode::None),
                new OptionDefinition(name: 'junit', shortcut: 'j', mode: OptionMode::None),
            ],
            handler: $this->closureHandler(),
        );
    }
}
