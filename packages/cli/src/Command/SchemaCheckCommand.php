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
    name: 'schema:check',
    description: 'Detect schema drift between entity type definitions and database tables',
)]
final class SchemaCheckCommand extends Command
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
        $results = $this->checker->checkSchemaDrift();

        if ($input->getOption('json')) {
            $output->writeln(json_encode(
                array_map(static fn(HealthCheckResult $r) => $r->toArray(), $results),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return $this->hasDrift($results) ? 1 : self::SUCCESS;
        }

        $hasDrift = false;

        foreach ($results as $result) {
            if ($result->status === 'fail') {
                $hasDrift = true;
                $output->writeln(sprintf('<error>DRIFT</error> %s: %s', $result->name, $result->message));

                $drift = $result->context['drift'] ?? [];
                if ($drift !== []) {
                    $table = new Table($output);
                    $table->setHeaders(['Column', 'Issue']);
                    foreach ($drift as $entry) {
                        $table->addRow([$entry['column'], $entry['issue']]);
                    }
                    $table->render();
                }

                if ($result->remediation !== '') {
                    $output->writeln(sprintf('  Remediation: %s', $result->remediation));
                }
                $output->writeln('');
            } else {
                $output->writeln(sprintf('<info>OK</info> %s', $result->message));
            }
        }

        if ($hasDrift) {
            return 1;
        }

        return self::SUCCESS;
    }

    /** @param list<HealthCheckResult> $results */
    private function hasDrift(array $results): bool
    {
        foreach ($results as $r) {
            if ($r->status === 'fail') {
                return true;
            }
        }
        return false;
    }
}
