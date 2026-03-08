<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Foundation\Diagnostic\HealthCheckerInterface;
use Waaseyaa\Foundation\Diagnostic\HealthCheckResult;

#[AsCommand(
    name: 'health:check',
    description: 'Run all diagnostic health checks and report results',
)]
final class HealthCheckCommand extends Command
{
    public function __construct(
        private readonly HealthCheckerInterface $checker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $results = $this->checker->runAll();

        if ($input->getOption('json')) {
            $output->writeln(json_encode(
                array_map(static fn(HealthCheckResult $r) => $r->toArray(), $results),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return $this->exitCode($results);
        }

        $hasFailures = false;
        $hasWarnings = false;

        $table = new Table($output);
        $table->setHeaders(['Status', 'Check', 'Message']);

        foreach ($results as $result) {
            $statusLabel = match ($result->status) {
                'pass' => '<info>PASS</info>',
                'warn' => '<comment>WARN</comment>',
                'fail' => '<error>FAIL</error>',
                default => $result->status,
            };

            $table->addRow([
                $statusLabel,
                $result->name,
                $result->message,
            ]);

            if ($result->status === 'fail') {
                $hasFailures = true;
            }
            if ($result->status === 'warn') {
                $hasWarnings = true;
            }
        }

        $table->render();

        // Print remediations for non-passing checks.
        $nonPassing = array_filter($results, static fn(HealthCheckResult $r) => $r->status !== 'pass');
        if ($nonPassing !== []) {
            $output->writeln('');
            $output->writeln('<comment>Remediations:</comment>');
            foreach ($nonPassing as $result) {
                if ($result->remediation !== '') {
                    $output->writeln(sprintf('  %s: %s', $result->name, $result->remediation));
                }
            }
        }

        $output->writeln('');
        if ($hasFailures) {
            $output->writeln('<error>Health check failed.</error>');
        } elseif ($hasWarnings) {
            $output->writeln('<comment>Health check passed with warnings.</comment>');
        } else {
            $output->writeln('<info>All health checks passed.</info>');
        }

        return $this->exitCode($results);
    }

    /** @param list<HealthCheckResult> $results */
    private function exitCode(array $results): int
    {
        $hasFailures = false;
        $hasWarnings = false;

        foreach ($results as $r) {
            if ($r->status === 'fail') {
                $hasFailures = true;
            }
            if ($r->status === 'warn') {
                $hasWarnings = true;
            }
        }

        if ($hasFailures) {
            return 2;
        }

        if ($hasWarnings) {
            return 1;
        }

        return self::SUCCESS;
    }
}
