<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeIdNormalizer;
use Waaseyaa\Entity\EntityTypeLifecycleManager;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[AsCommand(
    name: 'type:enable',
    description: 'Re-enable a previously disabled content type',
)]
final class TypeEnableCommand extends Command
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
            ->addArgument('type', InputArgument::REQUIRED, 'The entity type ID to enable (e.g. note)')
            ->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Actor ID for the audit log', 'cli')
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant ID (optional, for per-tenant enable)', '');
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

        if (!$this->entityTypeManager->hasDefinition($typeId)) {
            $output->writeln(sprintf('<error>Unknown entity type: "%s"</error>', $rawTypeId));

            return self::FAILURE;
        }

        if (!$this->lifecycleManager->isDisabled($typeId, $tenantId)) {
            $output->writeln(sprintf('<comment>Entity type "%s" is already enabled.</comment>', $rawTypeId));

            return self::SUCCESS;
        }

        $this->lifecycleManager->enable($typeId, $actor, $tenantId);

        $output->writeln(sprintf('<info>Enabled entity type "%s". Audit entry recorded.</info>', $rawTypeId));

        return self::SUCCESS;
    }

}
