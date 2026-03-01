<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit;

use Waaseyaa\CLI\WaaseyaaApplication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

#[CoversClass(WaaseyaaApplication::class)]
class WaaseyaaApplicationTest extends TestCase
{
    #[Test]
    public function it_creates_application_with_correct_name_and_version(): void
    {
        $app = new WaaseyaaApplication();

        $this->assertSame('aurora', $app->getName());
        $this->assertSame('0.1.0', $app->getVersion());
    }

    #[Test]
    public function it_registers_multiple_commands(): void
    {
        $app = new WaaseyaaApplication();

        $command1 = new Command('test:one');
        $command2 = new Command('test:two');

        $app->registerCommands([$command1, $command2]);

        $this->assertTrue($app->has('test:one'));
        $this->assertTrue($app->has('test:two'));
    }
}
