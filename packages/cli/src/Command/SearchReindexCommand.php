<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Search\SearchIndexableInterface;
use Waaseyaa\Search\SearchIndexerInterface;

#[AsCommand(name: 'search:reindex', description: 'Rebuild the search index from all indexable entities')]
final class SearchReindexCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly SearchIndexerInterface $indexer,
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Entities per batch', (string) self::BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = (int) $input->getOption('batch-size');
        if ($batchSize < 1) {
            $batchSize = self::BATCH_SIZE;
        }

        $output->writeln('<info>Clearing search index...</info>');
        $this->indexer->removeAll();

        $totalIndexed = 0;

        foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
            $storage = $this->entityTypeManager->getStorage($entityType->id());
            // TODO: paginated loading — loadMultiple() loads all entities into memory at once
            $entities = $storage->loadMultiple();
            $batchCount = 0;
            $typeIndexed = 0;

            foreach ($entities as $entity) {
                if (!$entity instanceof SearchIndexableInterface) {
                    continue;
                }

                $this->indexer->index($entity);
                $typeIndexed++;
                $totalIndexed++;
                $batchCount++;

                if ($batchCount >= $batchSize) {
                    $output->writeln("  [{$entityType->id()}] Indexed $typeIndexed entities...");
                    $batchCount = 0;
                }
            }

            if ($typeIndexed > 0) {
                $output->writeln("<comment>{$entityType->id()}</comment>: indexed $typeIndexed entities");
            }
        }

        $output->writeln("<info>Reindex complete. $totalIndexed documents indexed.</info>");
        $output->writeln('<info>Schema version: ' . $this->indexer->getSchemaVersion() . '</info>');

        return self::SUCCESS;
    }
}
