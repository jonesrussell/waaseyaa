<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\Command\Make\AbstractMakeHandler;

/**
 * @api
 */
final class MakeTestHandler extends AbstractMakeHandler
{
    public function execute(CliIO $io): int
    {
        $name = (string) $io->argument('name');
        $className = $this->toPascalCase($name);
        $isUnit = (bool) $io->option('unit');

        $namespace = $isUnit ? 'App\\Tests\\Unit' : 'App\\Tests\\Integration';

        $rendered = $this->renderStub('test', [
            'class' => $className,
            'namespace' => $namespace,
        ]);

        $io->write($rendered);

        return 0;
    }
}
