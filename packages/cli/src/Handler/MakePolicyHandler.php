<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\Command\Make\AbstractMakeHandler;

/**
 * @api
 */
final class MakePolicyHandler extends AbstractMakeHandler
{
    public function execute(CliIO $io): int
    {
        $name = (string) $io->argument('name');
        $className = $this->toPascalCase($name);

        $rendered = $this->renderStub('policy', [
            'class' => $className,
        ]);

        $io->write($rendered);

        return 0;
    }
}
