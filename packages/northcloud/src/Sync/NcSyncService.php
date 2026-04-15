<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Sync;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\NorthCloud\Client\NorthCloudClient;

/**
 * Generic sync orchestrator: fetch NC hits, dedup, delegate mapping, persist.
 *
 * Mappers are resolved from MapperRegistry. Multiple mappers may fire on the same
 * hit — every mapper whose supports() returns true produces an entity.
 */
final class NcSyncService
{
    /**
     * @param list<string> $topics NC topic filters
     */
    public function __construct(
        private readonly NorthCloudClient $client,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly MapperRegistry $mappers,
        private readonly array $topics = ['indigenous'],
        private readonly int $minQuality = 60,
    ) {}

    /**
     * Fetch recent NC content and persist matching entities.
     *
     * @param int $limit Max hits to fetch
     * @param string|null $since ISO date lower bound (YYYY-MM-DD)
     * @param bool $dryRun When true, count what would be created without persisting
     */
    public function sync(int $limit = 20, ?string $since = null, bool $dryRun = false): NcSyncResult
    {
        $response = $this->client->getRecentContent(
            limit: $limit,
            since: $since,
            topics: $this->topics,
            minQuality: $this->minQuality,
        );

        if ($response === null) {
            error_log('NcSyncService: failed to fetch content from NorthCloud');
            return (new NcSyncResult())->withFetchFailed();
        }

        if (!is_array($response['hits'] ?? null)) {
            error_log('NcSyncService: malformed NorthCloud response, missing hits array');
            return (new NcSyncResult())->withFetchFailed();
        }

        $result = new NcSyncResult();

        foreach ($response['hits'] as $hit) {
            $result = $this->processHit($hit, $dryRun, $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $hit
     */
    private function processHit(array $hit, bool $dryRun, NcSyncResult $result): NcSyncResult
    {
        $supportingMappers = $this->mappers->mappersFor($hit);

        if ($supportingMappers === []) {
            return $result->withSkipped();
        }

        foreach ($supportingMappers as $mapper) {
            $result = $this->applyMapper($mapper, $hit, $dryRun, $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $hit
     */
    private function applyMapper(NcHitToEntityMapperInterface $mapper, array $hit, bool $dryRun, NcSyncResult $result): NcSyncResult
    {
        $entityType = $mapper->entityType();
        $fields = $mapper->map($hit);
        $dedupField = $mapper->dedupField();

        $storage = $this->entityTypeManager->getStorage($entityType);

        if ($dedupField !== '') {
            if (!array_key_exists($dedupField, $fields)) {
                throw new \LogicException(sprintf(
                    "Mapper %s declares dedupField '%s' but that key is not in map() output",
                    $mapper::class,
                    $dedupField,
                ));
            }

            if ($fields[$dedupField] !== '' && $fields[$dedupField] !== null) {
                $existing = $storage->getQuery()
                    ->condition($dedupField, $fields[$dedupField])
                    ->execute();

                if ($existing !== []) {
                    return $result->withSkipped();
                }
            }
        }

        if ($dryRun) {
            return $result->withCreated();
        }

        try {
            $entity = $storage->create($fields);
            $storage->save($entity);
            return $result->withCreated();
        } catch (\LogicException $e) {
            // Contract violation — rethrow so mapper bugs surface loudly.
            throw $e;
        } catch (\Throwable $e) {
            $source = ($dedupField !== '' && isset($fields[$dedupField])) ? (string) $fields[$dedupField] : '(no dedup key)';
            error_log(sprintf(
                'NcSyncService: failed to create %s from %s: %s',
                $entityType,
                $source,
                $e->getMessage(),
            ));
            return $result->withFailed();
        }
    }
}
