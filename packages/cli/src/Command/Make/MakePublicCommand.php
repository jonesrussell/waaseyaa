<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Make;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'make:public',
    description: 'Scaffold the canonical public/index.php front controller',
)]
final class MakePublicCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing public/index.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $publicDir = $this->projectRoot . '/public';
        $target = $publicDir . '/index.php';
        $stub = __DIR__ . '/../../../templates/public/index.php.stub';

        if (! is_dir($publicDir) && ! @mkdir($publicDir, 0755, true) && ! is_dir($publicDir)) {
            $output->writeln(sprintf('<error>Failed to create directory %s</error>', $publicDir));

            return Command::FAILURE;
        }

        if (is_file($target) && ! $input->getOption('force')) {
            $output->writeln(sprintf('<comment>%s already exists. Use --force to overwrite.</comment>', $target));

            return Command::SUCCESS;
        }

        if (! is_file($stub)) {
            $output->writeln(sprintf('<error>Template not found: %s</error>', $stub));

            return Command::FAILURE;
        }

        $contents = @file_get_contents($stub);
        if ($contents === false) {
            $output->writeln(sprintf('<error>Failed to read template: %s</error>', $stub));

            return Command::FAILURE;
        }

        if (@file_put_contents($target, $contents) === false) {
            $output->writeln(sprintf('<error>Failed to write %s</error>', $target));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Created %s</info>', $target));

        return Command::SUCCESS;
    }
}
