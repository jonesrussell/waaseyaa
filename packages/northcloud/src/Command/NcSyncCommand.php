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
        $this->addOption('explain', null, InputOption::VALUE_NONE, 'Show skip reason breakdown and sampled hit diagnostics');
        $this->addOption('sample', null, InputOption::VALUE_REQUIRED, 'Capture up to N created/skipped samples in output', '10');
        $this->addOption('report-json', null, InputOption::VALUE_REQUIRED, 'Write sync report JSON to this path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $since = $input->getOption('since');
        $dryRun = (bool) $input->getOption('dry-run');
        $explain = (bool) $input->getOption('explain');
        $sample = max(0, (int) $input->getOption('sample'));
        $reportJsonPath = $input->getOption('report-json');

        if ($dryRun) {
            $output->writeln('<info>Dry run — no entities will be created.</info>');
        }

        $output->writeln(sprintf('Fetching up to %d hits from NorthCloud...', $limit));

        $result = $this->syncService->sync(
            limit: $limit,
            since: is_string($since) ? $since : null,
            dryRun: $dryRun,
            explain: $explain,
            sampleLimit: $sample,
        );

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

        if ($explain && $result->skipReasons !== []) {
            $output->writeln('Skip reasons:');
            $skipReasons = $result->skipReasons;
            arsort($skipReasons);
            foreach ($skipReasons as $reason => $count) {
                $output->writeln(sprintf('  - %s: %d', $reason, $count));
            }
        }

        if ($result->createdSamples !== []) {
            $output->writeln('Created sample:');
            foreach ($result->createdSamples as $sampleRow) {
                $output->writeln('  - ' . $this->formatSampleLine($sampleRow));
            }
        }

        if ($result->skippedSamples !== []) {
            $output->writeln('Skipped sample:');
            foreach ($result->skippedSamples as $sampleRow) {
                $output->writeln('  - ' . $this->formatSampleLine($sampleRow));
            }
        }

        if (is_string($reportJsonPath) && $reportJsonPath !== '') {
            $this->writeJsonReport($reportJsonPath, $result, $limit, is_string($since) ? $since : null, $dryRun, $explain, $sample);
            $output->writeln(sprintf('<info>Wrote report:</info> %s', $reportJsonPath));
        }

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

    /**
     * @param array<string, mixed> $sample
     */
    private function formatSampleLine(array $sample): string
    {
        $title = (string) ($sample['title'] ?? '(untitled)');
        $url = (string) ($sample['url'] ?? '(no-url)');
        $reason = isset($sample['reason']) ? ' | reason=' . (string) $sample['reason'] : '';
        $quality = isset($sample['quality_score']) ? ' | quality=' . (string) $sample['quality_score'] : '';
        return sprintf('%s | %s%s%s', $title, $url, $quality, $reason);
    }

    private function writeJsonReport(
        string $path,
        NcSyncResult $result,
        int $limit,
        ?string $since,
        bool $dryRun,
        bool $explain,
        int $sample,
    ): void {
        try {
            $payload = [
                'generated_at' => date('c'),
                'options' => [
                    'limit' => $limit,
                    'since' => $since,
                    'dry_run' => $dryRun,
                    'explain' => $explain,
                    'sample' => $sample,
                ],
                'result' => $result->toArray(),
            ];

            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

            $directory = dirname($path);
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            $tmp = $path . '.tmp';
            if (file_put_contents($tmp, $json) === false) {
                return;
            }

            rename($tmp, $path);
        } catch (\Throwable) {
            // Report writing is best-effort and should not fail sync execution.
        }
    }
}
