<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Waaseyaa\Entity\EntityTypeIdNormalizer;
use Waaseyaa\Entity\EntityTypeLifecycleManager;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[AsCommand(
    name: 'type:disable',
    description: 'Disable a registered content type (does not delete it)',
)]
final class TypeDisableCommand extends Command
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityTypeLifecycleManager $lifecycleManager,
        private readonly ?EntityTypeIdNormalizer $typeIdNormalizer = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'The entity type ID to disable (e.g. note)')
            ->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Actor ID for the audit log', 'cli')
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant ID (optional, for per-tenant disable)', '')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Allow disabling the last enabled type for the tenant')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $rawTypeId */
        $rawTypeId = $input->getArgument('type');
        $typeId = $this->typeIdNormalizer !== null ? $this->typeIdNormalizer->normalize($rawTypeId) : $rawTypeId;
        /** @var string $actor */
        $actor = $input->getOption('actor') ?? 'cli';
        /** @var string $tenantId */
        $tenantId = $input->getOption('tenant') ?? '';
        $tenantId = trim($tenantId) !== '' ? trim($tenantId) : null;
        $force = (bool) $input->getOption('force');
        $skipConfirm = (bool) $input->getOption('yes');

        if (!$this->entityTypeManager->hasDefinition($typeId)) {
            $output->writeln(sprintf('<error>Unknown entity type: "%s"</error>', $rawTypeId));

            return self::FAILURE;
        }

        if ($this->lifecycleManager->isDisabled($typeId, $tenantId)) {
            $output->writeln(sprintf('<comment>Entity type "%s" is already disabled.</comment>', $rawTypeId));

            return self::SUCCESS;
        }

        // Guard: refuse to disable the last enabled type.
        $definitions = $this->entityTypeManager->getDefinitions();
        $disabledIds = $this->lifecycleManager->getDisabledTypeIds($tenantId);
        $enabledCount = count(array_filter(
            array_keys($definitions),
            static fn(string $id): bool => $id !== $typeId && !in_array($id, $disabledIds, true),
        ));

        if ($enabledCount === 0) {
            if (!$force) {
                $output->writeln(
                    '<error>[DEFAULT_TYPE_DISABLED] Cannot disable the last enabled content type. '
                    . 'Register or enable another type first, or re-run with --force.</error>',
                );

                return self::FAILURE;
            }

            $output->writeln(
                '<comment>[DEFAULT_TYPE_DISABLED] Disabling the last enabled type for this tenant.</comment>',
            );
        }

        if (!$skipConfirm && $input->isInteractive()) {
            $question = new ConfirmationQuestion(
                sprintf(
                    'Disable "%s"%s? (y/N) ',
                    $rawTypeId,
                    $tenantId !== null ? ' for tenant "' . $tenantId . '"' : '',
                ),
                false,
            );
            $helper = $this->getHelper('question');
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');

                return self::SUCCESS;
            }
        }

        $this->lifecycleManager->disable($typeId, $actor, $tenantId);

        $output->writeln(sprintf('<info>Disabled entity type "%s". Audit entry recorded.</info>', $rawTypeId));

        return self::SUCCESS;
    }

}
