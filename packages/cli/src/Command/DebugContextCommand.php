<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'debug:context',
    description: 'Render deterministic debug panels for workflow, traversal, and SSR context',
)]
final class DebugContextCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('entity-type', null, InputOption::VALUE_REQUIRED, 'Source entity type', 'node')
            ->addOption('entity-id', null, InputOption::VALUE_REQUIRED, 'Source entity ID', '1')
            ->addOption('workflow-state', null, InputOption::VALUE_REQUIRED, 'Workflow state', 'draft')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Status flag (0/1)', '0')
            ->addOption('relationship-counts', null, InputOption::VALUE_REQUIRED, 'Traversal counts in outbound:inbound form', '0:0')
            ->addOption('view-mode', null, InputOption::VALUE_REQUIRED, 'SSR view mode', 'full')
            ->addOption('preview', null, InputOption::VALUE_REQUIRED, 'SSR preview mode (0/1)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entityType = strtolower(trim((string) $input->getOption('entity-type')));
        $entityId = trim((string) $input->getOption('entity-id'));
        $workflowState = strtolower(trim((string) $input->getOption('workflow-state')));
        $status = (int) $input->getOption('status') === 1 ? 1 : 0;
        $viewMode = trim((string) $input->getOption('view-mode'));
        $preview = (int) $input->getOption('preview') === 1;

        if ($entityType === '' || $entityId === '' || $workflowState === '' || $viewMode === '') {
            $output->writeln('<error>Required options are empty.</error>');
            return Command::INVALID;
        }

        $counts = $this->parseRelationshipCounts((string) $input->getOption('relationship-counts'));
        if ($counts === null) {
            $output->writeln('<error>Invalid --relationship-counts. Expected outbound:inbound (e.g. 4:2).</error>');
            return Command::INVALID;
        }

        $normalizedState = $workflowState !== '' ? $workflowState : ($status === 1 ? 'published' : 'draft');
        $isPublic = ($normalizedState === 'published') && $status === 1;

        $payload = [
            'debug_panel' => [
                'workflow' => [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'workflow_state' => $normalizedState,
                    'status' => $status,
                    'is_public' => $isPublic,
                ],
                'traversal' => [
                    'source' => ['type' => $entityType, 'id' => $entityId],
                    'counts' => [
                        'outbound' => $counts['outbound'],
                        'inbound' => $counts['inbound'],
                        'total' => $counts['outbound'] + $counts['inbound'],
                    ],
                ],
                'ssr' => [
                    'view_mode' => $viewMode,
                    'preview' => $preview,
                    'cache_scope' => $preview ? 'preview' : 'public',
                    'variant_seed' => [
                        'workflow_state' => $normalizedState,
                        'traversal_total' => $counts['outbound'] + $counts['inbound'],
                    ],
                ],
            ],
        ];

        $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    /**
     * @return array{outbound: int, inbound: int}|null
     */
    private function parseRelationshipCounts(string $raw): ?array
    {
        $parts = explode(':', trim($raw));
        if (count($parts) !== 2) {
            return null;
        }

        $outbound = trim($parts[0]);
        $inbound = trim($parts[1]);
        if (!ctype_digit($outbound) || !ctype_digit($inbound)) {
            return null;
        }

        return [
            'outbound' => (int) $outbound,
            'inbound' => (int) $inbound,
        ];
    }
}
