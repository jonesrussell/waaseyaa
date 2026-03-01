<?php

declare(strict_types=1);

namespace Waaseyaa\Plugin\Tests\Fixtures;

use Waaseyaa\Plugin\Attribute\WaaseyaaPlugin;
use Waaseyaa\Plugin\PluginBase;

#[WaaseyaaPlugin(id: 'test_plugin', label: 'Test Plugin', description: 'A test plugin')]
final class TestPlugin extends PluginBase
{
}
