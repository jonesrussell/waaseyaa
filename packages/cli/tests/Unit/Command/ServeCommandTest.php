<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Command\ServeCommand;

#[CoversClass(ServeCommand::class)]
final class ServeCommandTest extends TestCase
{
    #[Test]
    public function it_defaults_app_env_to_development(): void
    {
        $command = new ServeCommand('/tmp');

        $env = $command->resolveChildEnv([]);

        self::assertSame('development', $env['APP_ENV']);
        self::assertSame('1', $env['APP_DEBUG']);
    }

    #[Test]
    public function it_respects_caller_set_app_env(): void
    {
        $command = new ServeCommand('/tmp');

        $env = $command->resolveChildEnv(['APP_ENV' => 'staging']);

        self::assertSame('staging', $env['APP_ENV']);
        // APP_DEBUG is not forced when caller sets a non-default env.
        self::assertArrayNotHasKey('APP_DEBUG', $env);
    }

    #[Test]
    public function it_passes_through_other_env_vars(): void
    {
        $command = new ServeCommand('/tmp');

        $env = $command->resolveChildEnv(['PATH' => '/usr/bin', 'HOME' => '/home/test']);

        self::assertSame('/usr/bin', $env['PATH']);
        self::assertSame('/home/test', $env['HOME']);
        self::assertSame('development', $env['APP_ENV']);
    }
}
