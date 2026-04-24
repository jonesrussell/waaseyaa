<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Storage;

use Waaseyaa\Api\CodifiedContext\CodifiedContextSessionRow;
use Waaseyaa\Api\CodifiedContext\CodifiedContextSessionStoreInterface;
use Waaseyaa\Telescope\CodifiedContext\CodifiedContextEntry;

/**
 * Adapts {@see CodifiedContextStoreInterface} to the API-layer read contract so {@see \Waaseyaa\Api\Controller\CodifiedContextController}
 * does not depend on Telescope types directly.
 */
final class CodifiedContextSessionStoreAdapter implements CodifiedContextSessionStoreInterface
{
    public function __construct(
        private readonly CodifiedContextStoreInterface $inner,
    ) {}

    public function queryBySession(string $sessionId, int $limit = 100, int $offset = 0): array
    {
        return array_map(
            static fn(CodifiedContextEntry $e): CodifiedContextSessionRow => self::mapEntry($e),
            $this->inner->queryBySession($sessionId, $limit, $offset),
        );
    }

    public function queryByEventType(string $eventType, int $limit = 50, int $offset = 0): array
    {
        return array_map(
            static fn(CodifiedContextEntry $e): CodifiedContextSessionRow => self::mapEntry($e),
            $this->inner->queryByEventType($eventType, $limit, $offset),
        );
    }

    private static function mapEntry(CodifiedContextEntry $e): CodifiedContextSessionRow
    {
        return new CodifiedContextSessionRow(
            id: $e->id,
            type: $e->type,
            data: $e->data,
            sessionId: $e->sessionId,
            createdAt: $e->createdAt,
        );
    }
}
