<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\CLI\Support\AdminPackagePathResolver;

#[AsCommand(
    name: 'admin:dev',
    description: 'Run the Nuxt admin SPA in development (npm run dev)',
)]
final class AdminDevCommand extends Command
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

        $host = getenv('APP_HOST') !== false && getenv('APP_HOST') !== ''
            ? (string) getenv('APP_HOST')
            : '127.0.0.1';
        $port = getenv('APP_PORT') !== false && getenv('APP_PORT') !== ''
            ? (string) getenv('APP_PORT')
            : '8080';
        $backendUrl = getenv('NUXT_BACKEND_URL') !== false && getenv('NUXT_BACKEND_URL') !== ''
            ? (string) getenv('NUXT_BACKEND_URL')
            : 'http://' . $host . ':' . $port;

        $output->writeln(sprintf('<info>Admin package:</info> %s', $adminPath));
        $output->writeln(sprintf('<info>NUXT_BACKEND_URL=%s</info>', $backendUrl));
        $output->writeln('<comment>Press Ctrl+C to stop.</comment>');

        $env = getenv();
        $env['NUXT_BACKEND_URL'] = $backendUrl;

        $process = proc_open(
            [self::npmBinary(), 'run', 'dev'],
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
