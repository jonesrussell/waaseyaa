<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'serve',
    description: 'Start the PHP development server',
)]
final class ServeCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Specify which IP address the server should listen on. Set to 127.0.0.1 to restrict to localhost only. Can also be set via APP_HOST.', (string) (getenv('APP_HOST') ?: '0.0.0.0'))
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Specify which port the server should listen on. Can also be set via APP_PORT.', (string) (getenv('APP_PORT') ?: '8080'));
    }

    /**
     * Build the environment the PHP child server will run under.
     *
     * If the caller hasn't set APP_ENV, default to development and force
     * APP_DEBUG=1 so dev-mode database auto-creation and boot-error
     * visibility kick in. All other parent env vars pass through.
     *
     * @param array<string, string> $parentEnv
     * @return array<string, string>
     */
    public function resolveChildEnv(array $parentEnv): array
    {
        $env = $parentEnv;

        if (!isset($env['APP_ENV']) || $env['APP_ENV'] === '') {
            $env['APP_ENV'] = 'development';
            $env['APP_DEBUG'] = '1';
        }

        return $env;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');

        $publicIndex = $this->projectRoot . '/public/index.php';
        if (!file_exists($publicIndex) || filesize($publicIndex) === 0) {
            $output->writeln('<error>public/index.php is missing or empty.</error>');
            $output->writeln('<comment>Run: vendor/bin/waaseyaa make:public</comment>');
            return self::FAILURE;
        }

        $env = $this->resolveChildEnv(getenv());

        if (($env['APP_ENV'] ?? '') === 'development') {
            $output->writeln(
                '<info>Starting in development mode (APP_ENV=development).</info> '
                . 'Use <comment>APP_ENV=production vendor/bin/waaseyaa serve</comment> to override.',
            );
        }

        $displayHost = $host === '0.0.0.0' ? 'localhost' : $host;
        $output->writeln(sprintf('<info>Waaseyaa development server started:</info> http://%s:%s', $displayHost, $port));
        $output->writeln('<comment>Press Ctrl+C to stop.</comment>');

        $process = proc_open(
            [PHP_BINARY, '-S', "{$host}:{$port}", '-t', $this->projectRoot . '/public'],
            [STDIN, STDOUT, STDERR],
            $pipes,
            null,
            $env,
        );

        if ($process === false) {
            $output->writeln('<error>Failed to start the development server.</error>');
            return self::FAILURE;
        }

        $exitCode = proc_close($process);
        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
