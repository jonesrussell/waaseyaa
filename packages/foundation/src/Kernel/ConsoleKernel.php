<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel;

final class ConsoleKernel extends AbstractKernel
{
    public function handle(): int
    {
        return \Waaseyaa\CLI\CliApplication::run(
            argv: array_slice($_SERVER['argv'] ?? [], 1),
            projectRoot: $this->projectRoot,
            logger: $this->logger,
        );
    }
}
