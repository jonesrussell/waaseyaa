<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'make:plugin',
    description: 'Generate a plugin class with #[WaaseyaaPlugin] attribute',
)]
class MakePluginCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The plugin name (e.g. "my_formatter")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $className = str_replace('_', '', ucwords($name, '_'));
        $pluginId = strtolower($name);

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Plugin;

use Waaseyaa\Plugin\Attribute\WaaseyaaPlugin;

#[WaaseyaaPlugin(id: '{$pluginId}', label: '{$className}')]
class {$className}
{
}

PHP;

        $output->write($template);

        return Command::SUCCESS;
    }
}
