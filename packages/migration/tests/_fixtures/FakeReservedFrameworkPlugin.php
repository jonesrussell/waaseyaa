<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\PluginFixtures;

use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;

/**
 * Test fixture: process plugin whose FQCN sits inside the framework's
 * `Waaseyaa\Migration\` namespace tree (but NOT under
 * `Waaseyaa\Migration\Tests\`, which the `PluginRegistry` guard explicitly
 * excludes). Mirrors the shape of a future built-in plugin from WP03.
 */
final class FakeReservedFrameworkPlugin implements ProcessPluginInterface
{
    public function id(): string
    {
        return ReservedPluginIds::PASS_THROUGH;
    }

    public function stability(): string
    {
        return 'stable';
    }

    public function transform(mixed $value, ProcessContext $context): mixed
    {
        return $value;
    }
}
