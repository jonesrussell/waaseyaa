<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Waaseyaa\Entity\Audit\EntityAuditLogger;
use Waaseyaa\Entity\EntityTypeIdNormalizer;
use Waaseyaa\Entity\EntityTypeLifecycleManager;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[AsCommand(
    name: 'migrate:defaults',
    description: 'Migrate default content type enablement for tenants',
)]
final class MigrateDefaultsCommand extends Command
{
    private const MIGRATION_LOG = '/storage/framework/migrate-defaults.jsonl';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityTypeLifecycleManager $lifecycleManager,
        private readonly ?EntityAuditLogger $entityAuditLogger,
        private readonly string $projectRoot,
        private readonly ?EntityTypeIdNormalizer $typeIdNormalizer = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Tenant IDs to migrate (repeatable)')
            ->addOption('enable', null, InputOption::VALUE_REQUIRED, 'Type ID to enable for all tenants (e.g. note)', '')
            ->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Actor ID for audit log entries', 'cli')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompts')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report actions without making changes')
            ->addOption('rollback', null, InputOption::VALUE_NONE, 'Rollback previous migrate:defaults actions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenants = array_map('trim', $input->getOption('tenant') ?? []);
        $tenants = array_values(array_filter($tenants, static fn(string $id): bool => $id !== ''));
        $actor = (string) ($input->getOption('actor') ?? 'cli');
        $skipConfirm = (bool) $input->getOption('yes');
        $dryRun = (bool) $input->getOption('dry-run');
        $rollback = (bool) $input->getOption('rollback');

        if ($tenants === []) {
            $tenants = $this->discoverTenants();
        }

        if ($tenants === []) {
            $output->writeln('<comment>No tenants discovered. Pass --tenant to migrate specific tenants.</comment>');

            return Command::SUCCESS;
        }

        if ($rollback) {
            return $this->rollback($tenants, $actor, $skipConfirm, $dryRun, $output, $input);
        }

        $rawEnableType = (string) ($input->getOption('enable') ?? '');
        $enableType = $rawEnableType !== '' && $this->typeIdNormalizer !== null
            ? $this->typeIdNormalizer->normalize($rawEnableType)
            : $rawEnableType;
        if ($enableType !== '' && !$this->entityTypeManager->hasDefinition($enableType)) {
            $output->writeln(sprintf('<error>Unknown entity type: "%s"</error>', $rawEnableType));

            return Command::FAILURE;
        }

        $definitions = array_keys($this->entityTypeManager->getDefinitions());
        if ($definitions === []) {
            $output->writeln('<error>No registered entity types available for migration.</error>');

            return Command::FAILURE;
        }

        $missing = $this->tenantsWithNoEnabledTypes($tenants, $definitions);
        if ($missing === []) {
            $output->writeln('<info>All tenants already have at least one enabled type.</info>');

            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        if (!$helper instanceof QuestionHelper) {
            $output->writeln('<error>Interactive prompt helper not available.</error>');

            return Command::FAILURE;
        }

        foreach ($missing as $tenantId) {
            $selected = $enableType;

            if ($selected === '') {
                if (!$skipConfirm && $input->isInteractive()) {
                    $choices = $definitions;
                    $choices[] = 'skip';
                    $default = in_array('note', $definitions, true) ? 'note' : $choices[0];
                    $question = new ChoiceQuestion(
                        sprintf('Tenant "%s" has no enabled types. Enable which type?', $tenantId),
                        $choices,
                        $default,
                    );
                    $question->setErrorMessage('Type "%s" is invalid.');
                    $selected = (string) $helper->ask($input, $output, $question);
                } else {
                    $selected = in_array('note', $definitions, true) ? 'note' : '';
                }
            }

            if ($selected === '' || $selected === 'skip') {
                $output->writeln(sprintf('<comment>Skipped tenant "%s".</comment>', $tenantId));
                continue;
            }

            if (!$skipConfirm && $input->isInteractive()) {
                $question = new ConfirmationQuestion(
                    sprintf('Enable "%s" for tenant "%s"? (y/N) ', $selected, $tenantId),
                    false,
                );
                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln(sprintf('<comment>Skipped tenant "%s".</comment>', $tenantId));
                    continue;
                }
            }

            if ($dryRun) {
                $output->writeln(sprintf('<info>[dry-run] Would enable "%s" for tenant "%s".</info>', $selected, $tenantId));
                continue;
            }

            $this->lifecycleManager->enable($selected, $actor, $tenantId);
            $this->appendMigrationLog($tenantId, $selected, $actor, 'enabled');
            $output->writeln(sprintf('<info>Enabled "%s" for tenant "%s".</info>', $selected, $tenantId));
        }

        return Command::SUCCESS;
    }

    /**
     * @param string[] $tenants
     */
    private function rollback(
        array $tenants,
        string $actor,
        bool $skipConfirm,
        bool $dryRun,
        OutputInterface $output,
        InputInterface $input,
    ): int {
        $entries = $this->readMigrationLog();
        if ($entries === []) {
            $output->writeln('<comment>No migrate:defaults log entries found.</comment>');

            return Command::SUCCESS;
        }

        $targets = [];
        foreach ($entries as $entry) {
            if (($entry['action'] ?? '') !== 'enabled') {
                continue;
            }
            $tenantId = (string) ($entry['tenant_id'] ?? '');
            $typeId = (string) ($entry['type_id'] ?? '');
            if ($tenantId === '' || $typeId === '') {
                continue;
            }
            if (!in_array($tenantId, $tenants, true)) {
                continue;
            }
            $targets[] = ['tenant' => $tenantId, 'type' => $typeId];
        }

        if ($targets === []) {
            $output->writeln('<comment>No matching migration entries found for rollback.</comment>');

            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        if (!$helper instanceof QuestionHelper) {
            $output->writeln('<error>Interactive prompt helper not available.</error>');

            return Command::FAILURE;
        }

        if (!$skipConfirm && $input->isInteractive()) {
            $question = new ConfirmationQuestion('Rollback migrate:defaults changes for selected tenants? (y/N) ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Rollback aborted.</comment>');

                return Command::SUCCESS;
            }
        }

        foreach ($targets as $target) {
            $tenantId = $target['tenant'];
            $typeId = $target['type'];
            if ($dryRun) {
                $output->writeln(sprintf('<info>[dry-run] Would disable "%s" for tenant "%s".</info>', $typeId, $tenantId));
                continue;
            }

            $this->lifecycleManager->disable($typeId, $actor, $tenantId);
            $this->appendMigrationLog($tenantId, $typeId, $actor, 'rollback');
            $output->writeln(sprintf('<info>Disabled "%s" for tenant "%s".</info>', $typeId, $tenantId));
        }

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function discoverTenants(): array
    {
        $tenants = $this->lifecycleManager->getTenantIds();

        if ($this->entityAuditLogger !== null) {
            foreach ($this->entityAuditLogger->read() as $entry) {
                $tenantId = trim((string) ($entry['tenant_id'] ?? ''));
                if ($tenantId !== '') {
                    $tenants[] = $tenantId;
                }
            }
        }

        $tenants = array_values(array_unique($tenants));
        sort($tenants);

        return $tenants;
    }

    /**
     * @param string[] $tenants
     * @param string[] $definitions
     * @return string[]
     */
    private function tenantsWithNoEnabledTypes(array $tenants, array $definitions): array
    {
        $missing = [];

        foreach ($tenants as $tenantId) {
            $disabled = $this->lifecycleManager->getDisabledTypeIds($tenantId);
            $enabled = array_filter(
                $definitions,
                static fn(string $id): bool => !in_array($id, $disabled, true),
            );

            if ($enabled === []) {
                $missing[] = $tenantId;
            }
        }

        return $missing;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readMigrationLog(): array
    {
        $file = $this->migrationLogFile();
        if (!file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $entries = [];
        foreach ($lines as $line) {
            try {
                /** @var array<string, mixed> $entry */
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $entries[] = $entry;
            } catch (\JsonException) {
                // skip malformed lines
            }
        }

        return $entries;
    }

    private function appendMigrationLog(string $tenantId, string $typeId, string $actor, string $action): void
    {
        $file = $this->migrationLogFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = json_encode([
            'tenant_id' => $tenantId,
            'type_id' => $typeId,
            'actor_id' => $actor,
            'action' => $action,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        file_put_contents($file, $payload . "\n", FILE_APPEND | LOCK_EX);
    }

    private function migrationLogFile(): string
    {
        return $this->projectRoot . self::MIGRATION_LOG;
    }

}
