<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\NorthCloud\Sync\NcSyncResult;
use Waaseyaa\NorthCloud\Sync\NcSyncService;

#[AsCommand(name: 'northcloud:sync', description: 'Pull content from the NorthCloud Search API and persist entities via registered mappers')]
final class NcSyncCommand extends Command
{
    public function __construct(
        private readonly NcSyncService $syncService,
        private readonly ?string $statusPath = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum hits to fetch', '20');
        $this->addOption('since', 's', InputOption::VALUE_REQUIRED, 'Fetch content from this date (YYYY-MM-DD)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be created without persisting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $since = $input->getOption('since');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $output->writeln('<info>Dry run — no entities will be created.</info>');
        }

        $output->writeln(sprintf('Fetching up to %d hits from NorthCloud...', $limit));

        $result = $this->syncService->sync($limit, $since, $dryRun);

        if ($result->fetchFailed) {
            $output->writeln('<error>Failed to fetch content from NorthCloud. Check NORTHCLOUD_BASE_URL and network connectivity.</error>');
            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Done.</info> Created: %d | Skipped: %d | Failed: %d',
            $result->created,
            $result->skipped,
            $result->failed,
        ));

        $this->writeStatusFile($result);

        return $result->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function writeStatusFile(NcSyncResult $result): void
    {
        if ($this->statusPath === null) {
            return;
        }

        try {
            $data = json_encode([
                'last_sync' => date('c'),
                'created' => $result->created,
                'skipped' => $result->skipped,
                'failed' => $result->failed,
                'fetch_failed' => $result->fetchFailed,
                'cycles' => 1,
                'last_manual_run' => date('c'),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (\JsonException) {
            return;
        }

        $tmp = $this->statusPath . '.tmp';
        if (file_put_contents($tmp, $data) === false) {
            return;
        }
        rename($tmp, $this->statusPath);
    }
}
