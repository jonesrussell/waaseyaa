<?php

declare(strict_types=1);

namespace Waaseyaa\Plugin\Tests\Fixtures;

use Waaseyaa\Plugin\Attribute\WaaseyaaPlugin;
use Waaseyaa\Plugin\PluginBase;

#[WaaseyaaPlugin(id: 'another_plugin', label: 'Another Plugin')]
final class AnotherPlugin extends PluginBase
{
}
