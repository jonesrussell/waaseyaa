<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\CLI\Support\AdminPackagePathResolver;

#[AsCommand(
    name: 'admin:build',
    description: 'Build the Nuxt admin SPA for static hosting (npm run generate)',
)]
final class AdminBuildCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $adminPath = (new AdminPackagePathResolver($this->projectRoot))->resolve();
        } catch (\RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }

        $env = getenv();
        $output->writeln(sprintf('<info>Admin package:</info> %s', $adminPath));

        $process = proc_open(
            [self::npmBinary(), 'run', 'generate'],
            [STDIN, STDOUT, STDERR],
            $pipes,
            $adminPath,
            $env,
        );

        if (!is_resource($process)) {
            $output->writeln('<error>Could not start npm.</error>');

            return self::FAILURE;
        }

        $exitCode = proc_close($process);

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }

    private static function npmBinary(): string
    {
        $npm = getenv('NPM_BINARY');
        if (is_string($npm) && $npm !== '') {
            return $npm;
        }

        return 'npm';
    }
}
