<?php

declare(strict_types=1);

namespace Aurora\Plugin\Tests\Fixtures;

use Aurora\Plugin\Attribute\AuroraPlugin;
use Aurora\Plugin\PluginBase;

#[AuroraPlugin(id: 'test_plugin', label: 'Test Plugin', description: 'A test plugin')]
final class TestPlugin extends PluginBase
{
}
