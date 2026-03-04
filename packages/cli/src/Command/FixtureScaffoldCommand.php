<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fixture:scaffold',
    description: 'Generate deterministic workflow + relationship fixture scenario JSON',
)]
final class FixtureScaffoldCommand extends Command
{
    private const array VALID_STATES = ['draft', 'review', 'published', 'archived'];

    protected function configure(): void
    {
        $this
            ->addOption('key', null, InputOption::VALUE_REQUIRED, 'Scenario node key (machine name)')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Scenario node title')
            ->addOption('bundle', null, InputOption::VALUE_REQUIRED, 'Node bundle/type', 'article')
            ->addOption('workflow-state', null, InputOption::VALUE_REQUIRED, 'Workflow state', 'draft')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Publication status override (0/1)')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'Fixture author UID', '1')
            ->addOption('timestamp', null, InputOption::VALUE_REQUIRED, 'Deterministic fixture timestamp', '1735689600')
            ->addOption('relationship-type', null, InputOption::VALUE_REQUIRED, 'Optional relationship type')
            ->addOption('to-key', null, InputOption::VALUE_REQUIRED, 'Optional target fixture key for relationship')
            ->addOption('relationship-status', null, InputOption::VALUE_REQUIRED, 'Relationship status (0/1)', '1')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Optional output file path (.json)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = trim((string) $input->getOption('key'));
        $title = trim((string) $input->getOption('title'));
        $bundle = trim((string) $input->getOption('bundle'));
        $workflowState = strtolower(trim((string) $input->getOption('workflow-state')));
        $relationshipType = trim((string) $input->getOption('relationship-type'));
        $toKey = trim((string) $input->getOption('to-key'));

        if ($key === '' || $title === '' || $bundle === '') {
            $output->writeln('<error>--key, --title, and --bundle are required.</error>');
            return Command::INVALID;
        }
        if (!in_array($workflowState, self::VALID_STATES, true)) {
            $output->writeln(sprintf(
                '<error>Invalid --workflow-state "%s". Allowed: %s</error>',
                $workflowState,
                implode(', ', self::VALID_STATES),
            ));
            return Command::INVALID;
        }

        if (($relationshipType === '') !== ($toKey === '')) {
            $output->writeln('<error>--relationship-type and --to-key must be provided together.</error>');
            return Command::INVALID;
        }

        $timestamp = max(0, (int) $input->getOption('timestamp'));
        $uid = max(0, (int) $input->getOption('uid'));
        $statusOption = $input->getOption('status');
        $status = $statusOption === null
            ? ($workflowState === 'published' ? 1 : 0)
            : ((int) $statusOption === 1 ? 1 : 0);

        $node = [
            'title' => $title,
            'type' => $bundle,
            'uid' => $uid,
            'created' => $timestamp,
            'changed' => $timestamp,
            'status' => $status,
            'workflow_state' => $workflowState,
        ];

        $scenario = [
            'nodes' => [
                $key => $node,
            ],
            'relationships' => [],
        ];

        if ($relationshipType !== '') {
            $relationshipStatus = (int) $input->getOption('relationship-status') === 1 ? 1 : 0;
            $scenario['relationships'][] = [
                'key' => sprintf('%s_to_%s_%s', $key, $toKey, $relationshipType),
                'relationship_type' => $relationshipType,
                'from' => $key,
                'to' => $toKey,
                'status' => $relationshipStatus,
                'start_date' => $timestamp,
                'end_date' => null,
            ];
        }

        ksort($scenario['nodes']);
        usort($scenario['relationships'], static fn(array $a, array $b): int => strcmp((string) $a['key'], (string) $b['key']));

        $json = json_encode($scenario, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $outputPath = $input->getOption('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            $dir = dirname($outputPath);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $output->writeln(sprintf('<error>Unable to create output directory: %s</error>', $dir));
                return Command::FAILURE;
            }

            file_put_contents($outputPath, $json . PHP_EOL);
            $output->writeln(sprintf('Fixture scenario written: %s', $outputPath));
            return Command::SUCCESS;
        }

        $output->writeln($json);

        return Command::SUCCESS;
    }
}
