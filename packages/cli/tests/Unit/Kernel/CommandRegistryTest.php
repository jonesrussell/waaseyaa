<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Exception\DuplicateCommandException;

#[CoversClass(CommandRegistry::class)]
final class CommandRegistryTest extends TestCase
{
    private function makeCommand(string $name): CommandDefinition
    {
        return new CommandDefinition(
            name: $name,
            description: 'Test command.',
            handler: static function (CliIO $io): int {
                return 0;
            },
        );
    }

    // --- Empty state ---

    #[Test]
    public function itStartsEmpty(): void
    {
        $registry = new CommandRegistry();

        self::assertSame([], $registry->all());
        self::assertSame([], $registry->names());
        self::assertNull($registry->get('any'));
    }

    // --- Populated state ---

    #[Test]
    public function itRegistersAndRetrievesCommand(): void
    {
        $registry = new CommandRegistry();
        $cmd = $this->makeCommand('health:check');

        $registry->register($cmd);

        self::assertSame($cmd, $registry->get('health:check'));
    }

    #[Test]
    public function itReturnsNullForMissingCommand(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeCommand('list'));

        self::assertNull($registry->get('not-registered'));
    }

    #[Test]
    public function itReturnsSortedNames(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeCommand('zebra'));
        $registry->register($this->makeCommand('apple'));
        $registry->register($this->makeCommand('migrate'));

        self::assertSame(['apple', 'migrate', 'zebra'], $registry->names());
    }

    #[Test]
    public function itReturnsSortedAllMap(): void
    {
        $registry = new CommandRegistry();
        $c = $this->makeCommand('cache:clear');
        $b = $this->makeCommand('about');

        $registry->register($c);
        $registry->register($b);

        $all = $registry->all();
        self::assertSame(['about', 'cache:clear'], array_keys($all));
        self::assertSame($b, $all['about']);
        self::assertSame($c, $all['cache:clear']);
    }

    #[Test]
    public function itReturnsDeterministicOrderAcrossDifferentInsertionOrders(): void
    {
        $r1 = new CommandRegistry();
        $r2 = new CommandRegistry();

        $r1->register($this->makeCommand('z'));
        $r1->register($this->makeCommand('a'));
        $r1->register($this->makeCommand('m'));

        $r2->register($this->makeCommand('m'));
        $r2->register($this->makeCommand('z'));
        $r2->register($this->makeCommand('a'));

        self::assertSame($r1->names(), $r2->names());
    }

    // --- Duplicate state ---

    #[Test]
    public function itThrowsOnDuplicateRegistration(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeCommand('health:check'));

        $this->expectException(DuplicateCommandException::class);
        $this->expectExceptionMessage('health:check');

        $registry->register($this->makeCommand('health:check'));
    }

    #[Test]
    public function itThrowsWithCommandNameInMessage(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeCommand('migrate'));

        try {
            $registry->register($this->makeCommand('migrate'));
            self::fail('Expected DuplicateCommandException');
        } catch (DuplicateCommandException $e) {
            self::assertStringContainsString('migrate', $e->getMessage());
        }
    }
}
