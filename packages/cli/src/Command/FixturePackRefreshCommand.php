<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fixture:pack:refresh',
    description: 'Refresh deterministic fixture-pack aggregate from scenario JSON files',
)]
final class FixturePackRefreshCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('input-dir', null, InputOption::VALUE_REQUIRED, 'Directory containing scenario .json files', 'tests/fixtures/scenarios')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output aggregate JSON path')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print aggregate JSON to stdout')
            ->addOption('fail-on-empty', null, InputOption::VALUE_NONE, 'Return non-zero if no scenarios are found');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputDir = trim((string) $input->getOption('input-dir'));
        if ($inputDir === '' || !is_dir($inputDir)) {
            $output->writeln(sprintf('<error>Input directory does not exist: %s</error>', $inputDir));
            return Command::FAILURE;
        }

        $files = glob(rtrim($inputDir, '/') . '/*.json') ?: [];
        sort($files);

        if ($files === [] && (bool) $input->getOption('fail-on-empty')) {
            $output->writeln('<error>No fixture scenario files found.</error>');
            return Command::FAILURE;
        }

        $scenarios = [];
        $allNodes = [];
        $allRelationships = [];

        foreach ($files as $file) {
            if (!is_string($file)) {
                continue;
            }

            $raw = file_get_contents($file);
            if (!is_string($raw)) {
                $output->writeln(sprintf('<error>Unable to read fixture file: %s</error>', $file));
                return Command::FAILURE;
            }

            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>Invalid JSON in %s: %s</error>', $file, $e->getMessage()));
                return Command::FAILURE;
            }

            if (!is_array($decoded)) {
                $output->writeln(sprintf('<error>Invalid scenario shape in %s: expected object</error>', $file));
                return Command::FAILURE;
            }

            $scenarioName = pathinfo($file, PATHINFO_FILENAME);
            $nodes = is_array($decoded['nodes'] ?? null) ? $decoded['nodes'] : [];
            $relationships = is_array($decoded['relationships'] ?? null) ? $decoded['relationships'] : [];

            ksort($nodes);
            usort($relationships, static fn(array $a, array $b): int => strcmp((string) ($a['key'] ?? ''), (string) ($b['key'] ?? '')));

            $scenarios[$scenarioName] = [
                'nodes' => $nodes,
                'relationships' => $relationships,
            ];

            foreach ($nodes as $key => $node) {
                $allNodes[(string) $key] = $node;
            }
            foreach ($relationships as $relationship) {
                if (is_array($relationship)) {
                    $allRelationships[] = $relationship;
                }
            }
        }

        ksort($scenarios);
        ksort($allNodes);
        usort($allRelationships, static fn(array $a, array $b): int => strcmp((string) ($a['key'] ?? ''), (string) ($b['key'] ?? '')));

        $aggregate = [
            'contract_version' => 'v1.0',
            'surface' => 'fixture_pack_refresh',
            'scenario_count' => count($scenarios),
            'node_count' => count($allNodes),
            'relationship_count' => count($allRelationships),
            'scenarios' => $scenarios,
            'nodes' => $allNodes,
            'relationships' => $allRelationships,
        ];

        $aggregate['hash'] = sha1((string) json_encode($aggregate, JSON_THROW_ON_ERROR));
        $json = json_encode($aggregate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $outputPath = $input->getOption('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            $dir = dirname($outputPath);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $output->writeln(sprintf('<error>Unable to create output directory: %s</error>', $dir));
                return Command::FAILURE;
            }

            file_put_contents($outputPath, $json . PHP_EOL);
            $output->writeln(sprintf('Fixture pack written: %s', $outputPath));
        }

        if ((bool) $input->getOption('json') || !is_string($outputPath) || trim($outputPath) === '') {
            $output->writeln($json);
        } else {
            $output->writeln(sprintf(
                'Fixture pack refreshed (%d scenarios, hash %s).',
                (int) $aggregate['scenario_count'],
                (string) $aggregate['hash'],
            ));
        }

        return Command::SUCCESS;
    }
}
