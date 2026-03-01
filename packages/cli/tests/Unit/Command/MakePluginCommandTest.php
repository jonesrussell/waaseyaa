<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use Waaseyaa\CLI\Command\MakePluginCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(MakePluginCommand::class)]
class MakePluginCommandTest extends TestCase
{
    #[Test]
    public function it_generates_a_plugin_class(): void
    {
        $app = new Application();
        $app->add(new MakePluginCommand());
        $command = $app->find('make:plugin');
        $tester = new CommandTester($command);
        $tester->execute(['name' => 'my_formatter']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('#[WaaseyaaPlugin(', $output);
        $this->assertStringContainsString("id: 'my_formatter'", $output);
        $this->assertStringContainsString('class MyFormatter', $output);
        $this->assertStringContainsString('use Waaseyaa\\Plugin\\Attribute\\WaaseyaaPlugin;', $output);
        $this->assertStringContainsString('declare(strict_types=1);', $output);
    }
}
