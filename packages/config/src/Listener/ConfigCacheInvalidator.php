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
        } catch (\Throwable $e) {
            error_log(sprintf('ConfigCacheInvalidator: failed to delete %s: %s', $this->cachePath, $e->getMessage()));
        }
    }
}
