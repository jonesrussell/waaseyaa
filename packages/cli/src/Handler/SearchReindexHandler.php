<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Search\SearchIndexableInterface;
use Waaseyaa\Search\SearchIndexerInterface;

final class SearchReindexHandler
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly SearchIndexerInterface $indexer,
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function execute(CliIO $io): int
    {
        $batchSize = (int) ($io->option('batch-size') ?? self::BATCH_SIZE);
        if ($batchSize < 1) {
            $batchSize = self::BATCH_SIZE;
        }

        $io->writeln('Clearing search index...');
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
                    $io->writeln("  [{$entityType->id()}] Indexed $typeIndexed entities...");
                    $batchCount = 0;
                }
            }

            if ($typeIndexed > 0) {
                $io->writeln("{$entityType->id()}: indexed $typeIndexed entities");
            }
        }

        $io->writeln("Reindex complete. $totalIndexed documents indexed.");
        $io->writeln('Schema version: ' . $this->indexer->getSchemaVersion());

        return 0;
    }
}
