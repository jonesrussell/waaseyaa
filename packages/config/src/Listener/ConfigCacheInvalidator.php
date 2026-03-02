<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Listener;

use Waaseyaa\Config\Event\ConfigEvent;

final class ConfigCacheInvalidator
{
    public function __construct(
        private readonly string $cachePath,
    ) {}

    public function __invoke(ConfigEvent $event): void
    {
        try {
            if (is_file($this->cachePath)) {
                unlink($this->cachePath);
            }
        } catch (\Throwable) {
            // Best-effort: cache invalidation failure should not crash the primary request
        }
    }
}
