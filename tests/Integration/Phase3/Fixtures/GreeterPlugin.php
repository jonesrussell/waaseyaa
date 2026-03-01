<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase3\Fixtures;

use Waaseyaa\Plugin\Attribute\WaaseyaaPlugin;
use Waaseyaa\Plugin\PluginBase;

#[WaaseyaaPlugin(id: 'greeter', label: 'Greeter', description: 'A greeting plugin for integration testing')]
final class GreeterPlugin extends PluginBase
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}
