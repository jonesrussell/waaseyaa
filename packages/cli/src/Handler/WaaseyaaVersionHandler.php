<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\Provenance\ComposerProvenanceReporter;

final class WaaseyaaVersionHandler
{
    public function __construct(
        private readonly string $projectRoot,
    ) {}

    public function execute(CliIO $io): int
    {
        $report = new ComposerProvenanceReporter($this->projectRoot)->analyze();

        if ($io->option('json')) {
            $io->writeln(json_encode($report->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            ComposerProvenanceReporter::printHuman($report, $io);
        }

        if ($io->option('report-only')) {
            return 0;
        }

        return $report->hasDrift() ? 1 : 0;
    }
}
