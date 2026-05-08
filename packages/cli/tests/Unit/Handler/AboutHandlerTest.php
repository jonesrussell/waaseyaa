<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Waaseyaa\CLI\Handler\AboutHandler;
use Waaseyaa\CLI\Provider\MiscAServiceProvider;
use Waaseyaa\CLI\Testing\CliTester;

#[CoversClass(AboutHandler::class)]
final class AboutHandlerTest extends TestCase
{
    private function makeDefinition(): \Waaseyaa\CLI\CommandDefinition
    {
        $provider = new MiscAServiceProvider();
        foreach ($provider->nativeCommands() as $cmd) {
            if ($cmd->name === 'about') {
                return $cmd;
            }
        }

        throw new \RuntimeException('about command definition not found');
    }

    private function makeContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \RuntimeException(sprintf('Container::get(%s) called unexpectedly', $id));
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }

    #[Test]
    public function displaysSystemInformation(): void
    {
        $tester = CliTester::for($this->makeDefinition(), $this->makeContainer());
        $tester->execute([]);

        self::assertSame(0, $tester->getExitCode());
        self::assertStringContainsString('Waaseyaa', $tester->getStdout());
        self::assertStringContainsString('PHP Version', $tester->getStdout());
        self::assertStringContainsString(PHP_VERSION, $tester->getStdout());
    }
}
