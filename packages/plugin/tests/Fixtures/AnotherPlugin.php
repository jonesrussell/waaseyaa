<?php

declare(strict_types=1);

namespace Aurora\Plugin\Tests\Fixtures;

use Aurora\Plugin\Attribute\AuroraPlugin;
use Aurora\Plugin\PluginBase;

#[AuroraPlugin(id: 'another_plugin', label: 'Another Plugin')]
final class AnotherPlugin extends PluginBase
{
}
